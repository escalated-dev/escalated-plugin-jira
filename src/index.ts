import { definePlugin } from '@escalated-dev/plugin-sdk'
import type { PluginContext } from '@escalated-dev/plugin-sdk'
import { JiraClient } from './client'
import type { JiraSettings } from './client'

// ---------------------------------------------------------------------------
// Type helpers
// ---------------------------------------------------------------------------

interface TicketEvent {
  id: string | number
  title?: string
  subject?: string
  status?: string
  priority?: string
  description?: string
}

interface JiraWebhookPayload {
  webhookEvent?: string
  issue?: {
    id?: string
    key?: string
    fields?: {
      summary?: string
      status?: { name: string }
    }
  }
  changelog?: unknown
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const JIRA_LINKS_COLLECTION = 'jira_links'

async function getSettings(ctx: PluginContext): Promise<JiraSettings> {
  return (await ctx.config.all()) as JiraSettings
}

function makeClient(ctx: PluginContext, settings: JiraSettings): JiraClient | null {
  if (!settings.jira_url || !settings.api_email || !settings.api_token) {
    ctx.log.warn('[jira] Not configured — skipping')
    return null
  }
  return new JiraClient(ctx.http, settings)
}

// ---------------------------------------------------------------------------
// Plugin definition
// ---------------------------------------------------------------------------

export default definePlugin({
  name: 'jira',
  version: '0.1.0',
  description: 'Link Escalated tickets to Jira issues, sync status, and auto-create issues',

  config: [
    { name: 'jira_url', label: 'Jira Site URL', type: 'url', required: true,
      help: 'Base URL of your Jira Cloud or Server instance, e.g. https://your-org.atlassian.net' },
    { name: 'api_email', label: 'API Email', type: 'text', required: true,
      help: 'Email address associated with your Jira API token.' },
    { name: 'api_token', label: 'API Token', type: 'password', required: true,
      help: 'Generate at https://id.atlassian.com/manage-profile/security/api-tokens' },
    { name: 'default_project', label: 'Default Project', type: 'text',
      help: 'Jira project key to use when creating issues, e.g. PROJ.' },
    { name: 'default_issue_type', label: 'Default Issue Type', type: 'select',
      default: 'Task',
      options: [
        { value: 'Bug', label: 'Bug' },
        { value: 'Task', label: 'Task' },
        { value: 'Story', label: 'Story' },
        { value: 'Epic', label: 'Epic' },
      ],
    },
    { name: 'auto_create', label: 'Auto-Create Issues', type: 'boolean', default: false,
      help: 'Automatically create a Jira issue when a new Escalated ticket is created.' },
    { name: 'sync_direction', label: 'Sync Direction', type: 'select',
      default: 'escalated_to_jira',
      options: [
        { value: 'escalated_to_jira', label: 'Escalated → Jira' },
        { value: 'jira_to_escalated', label: 'Jira → Escalated' },
        { value: 'bidirectional', label: 'Bidirectional' },
      ],
    },
    { name: 'field_mapping', label: 'Field Mapping', type: 'json',
      default: [
        { escalated_field: 'subject', jira_field: 'summary' },
        { escalated_field: 'description', jira_field: 'description' },
        { escalated_field: 'priority', jira_field: 'priority' },
        { escalated_field: 'status', jira_field: 'status' },
        { escalated_field: 'assignee', jira_field: 'assignee' },
      ],
    },
  ],

  // -------------------------------------------------------------------------
  // Lifecycle
  // -------------------------------------------------------------------------

  onActivate: async (ctx) => {
    ctx.log.info('[jira] Plugin activated')
  },

  onDeactivate: async (ctx) => {
    ctx.log.info('[jira] Plugin deactivated')
  },

  // -------------------------------------------------------------------------
  // Action hooks
  // -------------------------------------------------------------------------

  actions: {
    'ticket.created': async (event, ctx) => {
      const ticket = event as TicketEvent
      const settings = await getSettings(ctx)

      if (!settings.auto_create) return

      const client = makeClient(ctx, settings)
      if (!client) return

      try {
        const res = await client.createIssue({
          subject: ticket.subject ?? ticket.title,
          description: ticket.description,
        })

        const issueKey = (res as unknown as { key: string }).key
        if (!issueKey) return

        // Persist the link using ctx.store (replaces LinkStore.php)
        await ctx.store.insert(JIRA_LINKS_COLLECTION, {
          ticket_id: ticket.id,
          jira_issue_key: issueKey,
          linked_at: new Date().toISOString(),
        })

        // Emit so other plugins (e.g. Slack) can react
        await ctx.emit('jira.issue.created', { ticketId: ticket.id, issueKey })

        ctx.log.info('[jira] Auto-created issue', { ticketId: ticket.id, issueKey })
      } catch (err: unknown) {
        ctx.log.error('[jira] Auto-create failed', { error: err instanceof Error ? err.message : err })
      }
    },

    'ticket.status.changed': async (event, ctx) => {
      const payload = event as { ticket: TicketEvent; newStatus: string }
      const ticket = payload.ticket ?? (event as unknown as TicketEvent)
      const newStatus: string = payload.newStatus ?? (event as unknown as { status: string }).status ?? ''

      const settings = await getSettings(ctx)
      if (settings.sync_direction === 'jira_to_escalated') return

      const client = makeClient(ctx, settings)
      if (!client) return

      // Find all Jira links for this ticket
      const links = await ctx.store.query(JIRA_LINKS_COLLECTION, { ticket_id: ticket.id })

      for (const link of links) {
        const issueKey = (link as Record<string, unknown>).jira_issue_key as string
        if (!issueKey) continue

        try {
          await client.transitionToStatus(issueKey, newStatus)
          ctx.log.info('[jira] Synced status to Jira', { ticketId: ticket.id, issueKey, newStatus })
        } catch (err: unknown) {
          ctx.log.warn('[jira] Status sync failed', {
            ticketId: ticket.id,
            issueKey,
            error: err instanceof Error ? err.message : err,
          })
        }
      }
    },

    // Receives the Jira webhook after the bridge routes it here.
    // The bridge exposes POST /webhooks/plugins/jira/webhook as a public webhook endpoint.
    'webhook.jira': async (event, ctx) => {
      const payload = event as JiraWebhookPayload
      const issueKey = payload.issue?.key

      if (!issueKey) return

      // Find any Escalated ticket linked to this Jira issue
      const links = await ctx.store.query(JIRA_LINKS_COLLECTION, { jira_issue_key: issueKey })
      if (links.length === 0) return

      const settings = await getSettings(ctx)
      if (settings.sync_direction === 'escalated_to_jira') return

      const newStatus = payload.issue?.fields?.status?.name
      if (!newStatus) return

      for (const link of links) {
        const ticketId = (link as Record<string, unknown>).ticket_id as string | number
        if (!ticketId) continue

        try {
          await ctx.tickets.update(ticketId, { status: newStatus.toLowerCase() })
          ctx.log.info('[jira] Synced Jira status to ticket', { ticketId, issueKey, newStatus })
        } catch (err: unknown) {
          ctx.log.warn('[jira] Reverse sync failed', {
            ticketId,
            issueKey,
            error: err instanceof Error ? err.message : err,
          })
        }
      }
    },
  },

  // -------------------------------------------------------------------------
  // Filter hooks
  // -------------------------------------------------------------------------

  filters: {
    'ticket.actions': {
      priority: 10,
      handler: async (actions, ctx) => {
        const settings = await getSettings(ctx)
        if (!settings.jira_url || !settings.api_email || !settings.api_token) {
          return actions
        }

        return [
          ...(actions as unknown[]),
          { id: 'jira-link-issue', label: 'Link to Jira', icon: 'link', group: 'integrations', order: 10 },
          { id: 'jira-create-issue', label: 'Create Jira Issue', icon: 'external-link', group: 'integrations', order: 11 },
        ]
      },
    },
  },

  // -------------------------------------------------------------------------
  // Admin pages
  // -------------------------------------------------------------------------

  pages: [
    {
      route: 'settings',
      component: 'JiraConfig',
      layout: 'admin',
      capability: 'manage_settings',
      menu: {
        label: 'Jira Integration',
        section: 'admin',
        position: 10,
        icon: 'link',
      },
    },
  ],

  // -------------------------------------------------------------------------
  // Component injections
  // -------------------------------------------------------------------------

  components: [
    {
      page: 'ticket.show',
      slot: 'sidebar',
      component: 'JiraLinkPanel',
      props: { pluginSlug: 'jira' },
      order: 30,
      capability: 'escalated-agent',
    },
    {
      page: 'admin.settings',
      slot: 'integrations',
      component: 'JiraConfig',
      props: { pluginSlug: 'jira' },
      order: 10,
      capability: 'manage_settings',
    },
  ],

  // -------------------------------------------------------------------------
  // Data endpoints
  // -------------------------------------------------------------------------

  endpoints: {
    'GET /settings': {
      capability: 'manage_settings',
      handler: async (ctx) => ctx.config.all(),
    },

    'PUT /settings': {
      capability: 'manage_settings',
      handler: async (ctx, req) => {
        await ctx.config.set(req.body as Record<string, unknown>)
        return { success: true }
      },
    },

    'POST /test-connection': {
      capability: 'manage_settings',
      handler: async (ctx, req) => {
        // Allow testing with credentials from the request body (before saving)
        const body = req.body as Partial<JiraSettings>
        const settings: JiraSettings = {
          ...(await getSettings(ctx)),
          ...body,
        }
        const client = makeClient(ctx, settings)
        if (!client) return { success: false, message: 'Jira is not configured.' }
        return client.testConnection()
      },
    },

    // Link management — replaces LinkStore.php
    'GET /links': {
      capability: 'escalated-agent',
      handler: async (ctx, req) => {
        const ticketId = req.query.ticket_id
        if (!ticketId) return []

        const links = await ctx.store.query(JIRA_LINKS_COLLECTION, { ticket_id: ticketId })

        // Optionally enrich with live Jira data
        const settings = await getSettings(ctx)
        const client = makeClient(ctx, settings)

        return Promise.all(
          links.map(async (link) => {
            const l = link as Record<string, unknown>
            if (!client) return l

            try {
              const issueData = await client.getIssue(l.jira_issue_key as string)
              return { ...l, issue_data: issueData }
            } catch {
              return l
            }
          }),
        )
      },
    },

    'POST /links': {
      capability: 'escalated-agent',
      handler: async (ctx, req) => {
        const body = req.body as { ticket_id: string | number; jira_issue_key: string }

        // Prevent duplicates
        const existing = await ctx.store.query(JIRA_LINKS_COLLECTION, {
          ticket_id: body.ticket_id,
          jira_issue_key: body.jira_issue_key,
        })
        if (existing.length > 0) return existing[0]

        const entry = await ctx.store.insert(JIRA_LINKS_COLLECTION, {
          ticket_id: body.ticket_id,
          jira_issue_key: body.jira_issue_key,
          linked_at: new Date().toISOString(),
        })

        await ctx.emit('jira.issue.linked', { ticketId: body.ticket_id, issueKey: body.jira_issue_key })
        return entry
      },
    },

    'DELETE /links': {
      capability: 'escalated-agent',
      handler: async (ctx, req) => {
        const body = req.body as { ticket_id: string | number; jira_issue_key: string }

        const existing = await ctx.store.query(JIRA_LINKS_COLLECTION, {
          ticket_id: body.ticket_id,
          jira_issue_key: body.jira_issue_key,
        })

        for (const link of existing) {
          const l = link as Record<string, unknown>
          await ctx.store.delete(JIRA_LINKS_COLLECTION, String(l.id ?? l.key ?? ''))
        }

        return { success: true }
      },
    },

    // Issue operations
    'POST /issues': {
      capability: 'escalated-agent',
      handler: async (ctx, req) => {
        const body = req.body as {
          ticket_id: string | number
          project?: string
          issue_type?: string
          summary?: string
          description?: string
        }

        const settings = await getSettings(ctx)
        const client = makeClient(ctx, settings)
        if (!client) throw new Error('Jira is not configured.')

        const ticket = await ctx.tickets.find(body.ticket_id)

        // Override defaults with request body
        const overriddenSettings: JiraSettings = {
          ...settings,
          ...(body.project ? { default_project: body.project } : {}),
          ...(body.issue_type ? { default_issue_type: body.issue_type } : {}),
        }
        const clientWithOverrides = new (JiraClient)(ctx.http, overriddenSettings)

        const res = await clientWithOverrides.createIssue({
          subject: body.summary ?? ticket?.title,
          description: body.description ?? '',
        })

        const issueKey = (res as unknown as { key: string }).key
        if (issueKey) {
          await ctx.store.insert(JIRA_LINKS_COLLECTION, {
            ticket_id: body.ticket_id,
            jira_issue_key: issueKey,
            linked_at: new Date().toISOString(),
          })
          await ctx.emit('jira.issue.created', { ticketId: body.ticket_id, issueKey })
        }

        return res
      },
    },

    'GET /issues/search': {
      capability: 'escalated-agent',
      handler: async (ctx, req) => {
        const q = req.query.q ?? ''
        const settings = await getSettings(ctx)
        const client = makeClient(ctx, settings)
        if (!client) return []

        // If query looks like an issue key, fetch directly
        if (/^[A-Z]+-\d+$/.test(q.toUpperCase())) {
          const issue = await client.getIssue(q.toUpperCase()).catch(() => null)
          return issue ? [issue] : []
        }

        const res = await client.searchIssues(`text ~ "${q}" ORDER BY updated DESC`)
        return (res as unknown as { issues: unknown[] }).issues ?? []
      },
    },

    'GET /issues/:key': {
      capability: 'escalated-agent',
      handler: async (ctx, req) => {
        const issueKey = req.params.key
        const settings = await getSettings(ctx)
        const client = makeClient(ctx, settings)
        if (!client) throw new Error('Jira is not configured.')
        return client.getIssue(issueKey)
      },
    },
  },

  // -------------------------------------------------------------------------
  // Webhook — Jira sends events here
  // -------------------------------------------------------------------------

  webhooks: {
    'POST /webhook': async (ctx, req) => {
      const payload = req.body as JiraWebhookPayload

      ctx.log.info('[jira] Webhook received', { event: payload.webhookEvent })

      // Dispatch as internal action so the 'webhook.jira' handler above runs
      await ctx.emit('webhook.jira', payload)

      return { ok: true }
    },
  },
})
