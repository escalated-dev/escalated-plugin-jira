# TODO: Escalated Plugin - Jira Integration

## Backend
- [ ] Jira Cloud OAuth2 / API token authentication
- [ ] Jira Server/Data Center support (basic auth + personal access tokens)
- [ ] Jira project and issue type fetching API
- [ ] Issue creation endpoint (map ticket fields to Jira fields)
- [ ] Issue linking model and migration (ticket_id, jira_issue_key, jira_project)
- [ ] Bi-directional status sync via Jira webhooks
- [ ] Jira webhook receiver for issue updates
- [ ] Field mapping configuration (Escalated fields <-> Jira fields)
- [ ] Comment sync between ticket replies and Jira comments
- [ ] Attachment sync support
- [ ] Jira issue search/browse API proxy

## Frontend
- [ ] Jira connection setup wizard (OAuth flow or API token entry)
- [ ] Jira issue link panel in ticket sidebar (show linked issues with status)
- [ ] Create Jira issue dialog with project/type/priority selection
- [ ] Link existing Jira issue search dialog
- [ ] Jira field mapping configuration UI
- [ ] Jira issue status badge component
- [ ] Jira project selector component
- [ ] Linked issues column in ticket list view

## Integration
- [ ] Auto-create Jira issue rules (on ticket creation, on escalation)
- [ ] Sync Jira issue status changes back to ticket status
- [ ] Sync ticket resolution to Jira issue transition
- [ ] Display Jira issue details in ticket timeline
- [ ] Bulk link/unlink Jira issues

## Configuration
- [ ] Jira instance URL and authentication credentials
- [ ] Default project and issue type for auto-creation
- [ ] Field mapping rules (priority mapping, status mapping)
- [ ] Sync direction preferences (one-way or bi-directional)
- [ ] Webhook secret configuration
- [ ] Auto-creation trigger rules
