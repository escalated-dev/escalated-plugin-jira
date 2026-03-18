import type { HttpClient } from '@escalated-dev/plugin-sdk'

export interface JiraSettings {
  jira_url?: string
  api_email?: string
  api_token?: string
  default_project?: string
  default_issue_type?: string
  auto_create?: boolean
  sync_direction?: 'escalated_to_jira' | 'jira_to_escalated' | 'bidirectional'
  field_mapping?: Array<{ escalated_field: string; jira_field: string }>
}

export interface JiraResponse {
  ok: boolean
  error?: string
  [key: string]: unknown
}

export interface JiraIssueFields {
  summary?: string
  status?: { name: string }
  priority?: { name: string }
  assignee?: { displayName: string } | null
  description?: unknown
  issuetype?: { name: string }
  project?: { key: string }
}

export interface JiraIssue {
  id: string
  key: string
  fields: JiraIssueFields
}

export interface JiraTransition {
  id: string
  name: string
  to: { name: string }
}

/**
 * Jira REST API v3 client using ctx.http (Basic Auth).
 * Translates from PHP Services/JiraClient.php.
 */
export class JiraClient {
  private readonly authHeader: string

  constructor(
    private readonly http: HttpClient,
    private readonly settings: JiraSettings,
  ) {
    const credentials = Buffer.from(`${settings.api_email}:${settings.api_token}`).toString('base64')
    this.authHeader = `Basic ${credentials}`
  }

  private get baseUrl(): string {
    return (this.settings.jira_url ?? '').replace(/\/$/, '')
  }

  private isConfigured(): boolean {
    return !!(this.settings.jira_url && this.settings.api_email && this.settings.api_token)
  }

  async request(method: 'GET' | 'POST' | 'PUT' | 'DELETE', path: string, body?: unknown): Promise<JiraResponse> {
    if (!this.isConfigured()) {
      throw new Error('Jira connection is not configured.')
    }

    const url = `${this.baseUrl}${path}`
    const headers: Record<string, string> = {
      Authorization: this.authHeader,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    }

    let response
    switch (method) {
      case 'GET':
        response = await this.http.get(url, { headers, timeout: 15_000 })
        break
      case 'POST':
        response = await this.http.post(url, { headers, json: body, timeout: 15_000 })
        break
      case 'PUT':
        response = await this.http.put(url, { headers, json: body, timeout: 15_000 })
        break
      case 'DELETE':
        response = await this.http.delete(url, { headers, timeout: 15_000 })
        break
    }

    if (response.status >= 400) {
      const errBody = (await response.json().catch(() => ({}))) as Record<string, unknown>
      const errorMessages = (errBody.errorMessages as string[] | undefined) ?? [String(errBody.message ?? response.status)]
      throw new Error(errorMessages.join('; '))
    }

    const data = (await response.json().catch(() => ({}))) as Record<string, unknown>
    return { ok: true, ...data }
  }

  async testConnection(): Promise<{ success: boolean; message: string }> {
    try {
      const res = await this.request('GET', '/rest/api/3/myself')
      const displayName = (res as unknown as { displayName: string }).displayName ?? 'Unknown'
      return { success: true, message: `Connected as ${displayName}` }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Connection failed'
      return { success: false, message: msg }
    }
  }

  async createIssue(ticket: { subject?: string; description?: string }): Promise<JiraResponse> {
    const project = this.settings.default_project ?? ''
    const issueType = this.settings.default_issue_type ?? 'Task'

    if (!project) {
      throw new Error('No default Jira project configured.')
    }

    return this.request('POST', '/rest/api/3/issue', {
      fields: {
        project: { key: project },
        issuetype: { name: issueType },
        summary: ticket.subject ?? 'Escalated Ticket',
        description: {
          type: 'doc',
          version: 1,
          content: [
            {
              type: 'paragraph',
              content: [{ type: 'text', text: ticket.description ?? '' }],
            },
          ],
        },
      },
    })
  }

  async getIssue(issueKey: string): Promise<JiraResponse> {
    return this.request('GET', `/rest/api/3/issue/${issueKey}`)
  }

  async searchIssues(jql: string, max = 10): Promise<JiraResponse> {
    return this.request('GET', `/rest/api/3/search?jql=${encodeURIComponent(jql)}&maxResults=${max}`)
  }

  async getTransitions(issueKey: string): Promise<JiraTransition[]> {
    const res = await this.request('GET', `/rest/api/3/issue/${issueKey}/transitions`)
    return (res as unknown as { transitions: JiraTransition[] }).transitions ?? []
  }

  async transitionIssue(issueKey: string, transitionId: string): Promise<JiraResponse> {
    return this.request('POST', `/rest/api/3/issue/${issueKey}/transitions`, {
      transition: { id: transitionId },
    })
  }

  async transitionToStatus(issueKey: string, targetStatusName: string): Promise<JiraResponse> {
    const transitions = await this.getTransitions(issueKey)
    const match = transitions.find(
      (t) => t.to.name.toLowerCase() === targetStatusName.toLowerCase(),
    )

    if (!match) {
      throw new Error(`No transition found to status '${targetStatusName}'`)
    }

    return this.transitionIssue(issueKey, match.id)
  }
}
