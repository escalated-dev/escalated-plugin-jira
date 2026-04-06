# Escalated Plugin: Jira

Links Escalated tickets to Jira issues, syncs status changes bidirectionally, and can auto-create Jira issues when new tickets arrive. Supports JQL search, direct issue-key linking, and configurable field mapping.

## Features

- Auto-create a Jira issue when a new Escalated ticket is created
- Sync ticket status changes to linked Jira issues
- Receive Jira webhook events and sync status back to Escalated tickets
- Link and unlink existing Jira issues to any ticket
- JQL issue search and direct issue-key lookup
- Configurable sync direction (Escalated to Jira, Jira to Escalated, or bidirectional)
- Field mapping between Escalated and Jira fields
- Ticket sidebar panel showing linked issues with live Jira data
- Admin settings page with connection test

## Configuration

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `jira_url` | url | Yes | Base URL of your Jira instance, e.g. `https://your-org.atlassian.net`. |
| `api_email` | text | Yes | Email address associated with your Jira API token. |
| `api_token` | password | Yes | Jira API token. Generate at id.atlassian.com. |
| `default_project` | text | No | Jira project key used when creating issues, e.g. `PROJ`. |
| `default_issue_type` | select | No | Default issue type: Bug, Task, Story, or Epic. Defaults to `Task`. |
| `auto_create` | boolean | No | Automatically create a Jira issue for every new ticket. Defaults to `false`. |
| `sync_direction` | select | No | `escalated_to_jira`, `jira_to_escalated`, or `bidirectional`. Defaults to `escalated_to_jira`. |
| `field_mapping` | json | No | Array of `{ escalated_field, jira_field }` pairs for field synchronization. |

## Admin Pages

- **settings** — Configure Jira credentials, project defaults, sync direction, and field mappings.

## Hooks

### Actions
- `ticket.created` — If `auto_create` is enabled, creates a Jira issue and stores the link.
- `ticket.status.changed` — Transitions linked Jira issues to the matching status when syncing to Jira.
- `webhook.jira` — Processes incoming Jira webhook events and updates Escalated ticket status when syncing from Jira.

### Filters
- `ticket.actions` — Adds "Link to Jira" and "Create Jira Issue" actions to the ticket action menu.

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/settings` | Get plugin configuration. |
| PUT | `/settings` | Save plugin configuration. |
| POST | `/test-connection` | Test Jira credentials. |
| GET | `/links?ticket_id=` | Get Jira issues linked to a ticket, enriched with live Jira data. |
| POST | `/links` | Link a Jira issue to a ticket. |
| DELETE | `/links` | Unlink a Jira issue from a ticket. |
| POST | `/issues` | Create a new Jira issue and link it to a ticket. |
| GET | `/issues/search?q=` | Search Jira issues by text or issue key. |
| GET | `/issues/:key` | Fetch a single Jira issue by key. |

## Webhooks

| Method | Path | Description |
|--------|------|-------------|
| POST | `/webhook` | Receives Jira webhook events (issue updated, transitioned, etc.). |

Configure this URL in Jira under Project Settings > Webhooks:
```
https://your-escalated-domain.com/webhooks/plugins/jira/webhook
```

## Installation

```bash
npm install @escalated-dev/plugin-jira
```

## License

MIT
