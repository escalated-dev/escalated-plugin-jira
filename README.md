# @escalated-dev/plugin-jira

Jira integration plugin for Escalated, built with the TypeScript Plugin SDK.

Links Escalated tickets to Jira issues, syncs status changes in both directions, and can auto-create Jira issues when new tickets arrive.

## Features

- Auto-create a Jira issue on ticket creation (configurable)
- Sync ticket status changes to linked Jira issues
- Receive Jira webhook events and sync status back to Escalated
- Link / unlink existing Jira issues to any ticket
- JQL issue search and direct issue-key linking
- Configurable sync direction (Escalated → Jira, Jira → Escalated, bidirectional)
- Field mapping configuration
- Ticket sidebar panel showing linked issues with status, priority, and assignee
- Admin settings page with connection test

## Configuration

| Field | Type | Description |
|-------|------|-------------|
| `jira_url` | url | Base URL of your Jira instance. Required. |
| `api_email` | text | Email address for Jira API token auth. Required. |
| `api_token` | password | Jira API token. Required. |
| `default_project` | text | Project key used when auto-creating issues. |
| `default_issue_type` | select | Default issue type: Bug, Task, Story, Epic. |
| `auto_create` | boolean | Auto-create a Jira issue for every new ticket. |
| `sync_direction` | select | `escalated_to_jira`, `jira_to_escalated`, or `bidirectional`. |
| `field_mapping` | json | Array of `{ escalated_field, jira_field }` pairs. |

## Action Hooks

| Hook | Description |
|------|-------------|
| `ticket.created` | If `auto_create` is enabled, creates a Jira issue and stores the link in `ctx.store`. |
| `ticket.status.changed` | Transitions linked Jira issue(s) to the matching status (if syncing to Jira). |
| `webhook.jira` | Processes incoming Jira events and updates Escalated tickets (if syncing from Jira). |

## Filter Hooks

| Hook | Priority | Description |
|------|----------|-------------|
| `ticket.actions` | 10 | Adds "Link to Jira" and "Create Jira Issue" actions to the ticket action menu. |

## Endpoints

| Method | Path | Capability | Description |
|--------|------|------------|-------------|
| GET | `/settings` | `manage_settings` | Get plugin configuration. |
| PUT | `/settings` | `manage_settings` | Save plugin configuration. |
| POST | `/test-connection` | `manage_settings` | Test Jira credentials. |
| GET | `/links?ticket_id=` | `escalated-agent` | Get Jira issues linked to a ticket. |
| POST | `/links` | `escalated-agent` | Link a Jira issue to a ticket. |
| DELETE | `/links` | `escalated-agent` | Unlink a Jira issue from a ticket. |
| POST | `/issues` | `escalated-agent` | Create a new Jira issue and link it. |
| GET | `/issues/search?q=` | `escalated-agent` | Search Jira issues by JQL or issue key. |
| GET | `/issues/:key` | `escalated-agent` | Fetch a single Jira issue. |

## Webhooks

| Method | Path | Description |
|--------|------|-------------|
| POST | `/webhook` | Receives Jira webhook events (issue updated, transitioned, etc.). |

Configure this URL in Jira → Project Settings → Webhooks:
```
https://your-escalated-domain.com/webhooks/plugins/jira/webhook
```

## Storage

Uses `ctx.store` with collection `jira_links`. Each record:

```json
{
  "ticket_id": 42,
  "jira_issue_key": "PROJ-123",
  "linked_at": "2026-03-18T12:00:00Z"
}
```

This replaces the PHP `Support/LinkStore.php` JSON file approach.

## Package structure

```
escalated-plugin-jira-sdk/
├── package.json
├── tsconfig.json
├── .gitignore
├── src/
│   ├── index.ts       # definePlugin() — backend
│   └── client.ts      # JiraClient (REST API v3, Basic Auth, ctx.http)
├── frontend/
│   ├── index.js       # defineEscalatedPlugin() — Vue frontend
│   └── components/
│       ├── JiraConfig.vue
│       └── JiraLinkPanel.vue
└── README.md
```

## Migration from PHP

| PHP | TypeScript SDK |
|-----|----------------|
| `Plugin.php` action/filter registrations | `actions` and `filters` in `src/index.ts` |
| `Services/JiraClient.php` | `src/client.ts` (uses `ctx.http` instead of Laravel Http facade) |
| `Support/Config.php` JSON file | `ctx.config` |
| `Support/LinkStore.php` JSON file | `ctx.store` collection `jira_links` |
| `Handlers/EventHandler.php` | `actions['ticket.created']`, `actions['ticket.status.changed']` |
| `Handlers/WebhookHandler.php` | `webhooks['POST /webhook']` + `actions['webhook.jira']` |
