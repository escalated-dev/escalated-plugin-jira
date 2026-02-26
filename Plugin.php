<?php

/**
 * Jira Integration Plugin for Escalated
 *
 * Links Escalated tickets to Jira issues, syncs status changes,
 * and optionally auto-creates Jira issues from new tickets.
 * Supports bidirectional sync via webhooks.
 */

// Prevent direct access
if (!defined('ESCALATED_LOADED')) {
    exit('Direct access not allowed.');
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define('ESC_JIRA_VERSION', '0.1.0');
define('ESC_JIRA_SLUG', 'jira');
define('ESC_JIRA_CONFIG_FILE', __DIR__ . '/config/settings.json');
define('ESC_JIRA_LINKS_FILE', __DIR__ . '/config/links.json');

// ---------------------------------------------------------------------------
// Settings helpers
// ---------------------------------------------------------------------------

/**
 * Return the default settings structure.
 */
function esc_jira_default_settings(): array
{
    return [
        'jira_url'           => '',
        'api_email'          => '',
        'api_token'          => '',
        'default_project'    => '',
        'default_issue_type' => 'Task',
        'auto_create'        => false,
        'sync_direction'     => 'escalated_to_jira', // escalated_to_jira | jira_to_escalated | bidirectional
        'field_mapping'      => [
            ['escalated_field' => 'subject',     'jira_field' => 'summary'],
            ['escalated_field' => 'description', 'jira_field' => 'description'],
            ['escalated_field' => 'priority',    'jira_field' => 'priority'],
            ['escalated_field' => 'status',      'jira_field' => 'status'],
            ['escalated_field' => 'assignee',    'jira_field' => 'assignee'],
        ],
    ];
}

/**
 * Read the current settings from the JSON config file.
 */
function esc_jira_get_settings(): array
{
    if (!file_exists(ESC_JIRA_CONFIG_FILE)) {
        return esc_jira_default_settings();
    }

    $json = file_get_contents(ESC_JIRA_CONFIG_FILE);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        return esc_jira_default_settings();
    }

    return array_merge(esc_jira_default_settings(), $data);
}

/**
 * Persist settings to the JSON config file.
 */
function esc_jira_save_settings(array $settings): bool
{
    $dir = dirname(ESC_JIRA_CONFIG_FILE);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Never persist the raw API token in plain text logs, but we do store it
    // in the config file (ideally this would be encrypted at rest).
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return file_put_contents(ESC_JIRA_CONFIG_FILE, $json) !== false;
}

// ---------------------------------------------------------------------------
// Link storage: ticket_id <-> jira_issue_key mapping
// ---------------------------------------------------------------------------

/**
 * Return all stored links.
 *
 * @return array List of link entries: [ { ticket_id, jira_issue_key, linked_at } ]
 */
function esc_jira_get_links(): array
{
    if (!file_exists(ESC_JIRA_LINKS_FILE)) {
        return [];
    }

    $json = file_get_contents(ESC_JIRA_LINKS_FILE);
    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}

/**
 * Save the full links array.
 */
function esc_jira_save_links(array $links): bool
{
    $dir = dirname(ESC_JIRA_LINKS_FILE);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $json = json_encode(array_values($links), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return file_put_contents(ESC_JIRA_LINKS_FILE, $json) !== false;
}

/**
 * Get all Jira issue keys linked to a given ticket.
 */
function esc_jira_get_links_for_ticket($ticket_id): array
{
    $links = esc_jira_get_links();

    return array_values(array_filter($links, function ($link) use ($ticket_id) {
        return (string) ($link['ticket_id'] ?? '') === (string) $ticket_id;
    }));
}

/**
 * Get the ticket ID linked to a given Jira issue key.
 */
function esc_jira_get_ticket_for_issue(string $issue_key): ?string
{
    $links = esc_jira_get_links();

    foreach ($links as $link) {
        if (($link['jira_issue_key'] ?? '') === $issue_key) {
            return (string) $link['ticket_id'];
        }
    }

    return null;
}

/**
 * Add a new link between a ticket and a Jira issue.
 */
function esc_jira_add_link($ticket_id, string $jira_issue_key): array
{
    $links = esc_jira_get_links();

    // Prevent duplicate links
    foreach ($links as $link) {
        if ((string) ($link['ticket_id'] ?? '') === (string) $ticket_id
            && ($link['jira_issue_key'] ?? '') === $jira_issue_key) {
            return $link;
        }
    }

    $entry = [
        'ticket_id'      => $ticket_id,
        'jira_issue_key' => $jira_issue_key,
        'linked_at'      => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    $links[] = $entry;
    esc_jira_save_links($links);

    return $entry;
}

/**
 * Remove a link between a ticket and a Jira issue.
 */
function esc_jira_remove_link($ticket_id, string $jira_issue_key): bool
{
    $links = esc_jira_get_links();
    $original_count = count($links);

    $links = array_filter($links, function ($link) use ($ticket_id, $jira_issue_key) {
        return !((string) ($link['ticket_id'] ?? '') === (string) $ticket_id
            && ($link['jira_issue_key'] ?? '') === $jira_issue_key);
    });

    if (count($links) < $original_count) {
        esc_jira_save_links($links);
        return true;
    }

    return false;
}

// ---------------------------------------------------------------------------
// Jira REST API helper (stub)
// ---------------------------------------------------------------------------

/**
 * Make a request to the Jira REST API using basic auth (email + API token).
 *
 * @param string $method  HTTP method (GET, POST, PUT, DELETE)
 * @param string $path    API path, e.g. "/rest/api/3/issue"
 * @param array  $body    Request body (for POST/PUT)
 * @param array  $settings Plugin settings (auto-loaded if null)
 *
 * @return array Decoded JSON response
 */
function esc_jira_api_request(string $method, string $path, array $body = [], ?array $settings = null): array
{
    if ($settings === null) {
        $settings = esc_jira_get_settings();
    }

    $jira_url  = rtrim($settings['jira_url'] ?? '', '/');
    $api_email = $settings['api_email'] ?? '';
    $api_token = $settings['api_token'] ?? '';

    if (empty($jira_url) || empty($api_email) || empty($api_token)) {
        return ['error' => 'Jira connection is not configured.'];
    }

    $url = $jira_url . $path;

    // TODO: Implement Jira API call
    // The implementation would use cURL or wp_remote_request:
    //
    // $headers = [
    //     'Authorization' => 'Basic ' . base64_encode($api_email . ':' . $api_token),
    //     'Content-Type'  => 'application/json',
    //     'Accept'        => 'application/json',
    // ];
    //
    // $ch = curl_init($url);
    // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(
    //     fn($k, $v) => "$k: $v",
    //     array_keys($headers),
    //     array_values($headers)
    // ));
    //
    // if (!empty($body) && in_array($method, ['POST', 'PUT'])) {
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    // }
    //
    // $response = curl_exec($ch);
    // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch);
    //
    // return json_decode($response, true) ?: [];

    return ['error' => 'Jira API call not yet implemented.'];
}

/**
 * Test the Jira connection by fetching server info.
 *
 * @return array { success: bool, message: string }
 */
function esc_jira_test_connection(?array $settings = null): array
{
    $response = esc_jira_api_request('GET', '/rest/api/3/serverInfo', [], $settings);

    // TODO: Implement Jira API call
    if (isset($response['error'])) {
        return ['success' => false, 'message' => $response['error']];
    }

    return [
        'success' => true,
        'message' => 'Connected to ' . ($response['serverTitle'] ?? 'Jira'),
    ];
}

/**
 * Create a Jira issue from an Escalated ticket.
 *
 * @param array $ticket Escalated ticket data
 * @param array|null $settings Plugin settings
 *
 * @return array Created issue data or error
 */
function esc_jira_create_issue(array $ticket, ?array $settings = null): array
{
    if ($settings === null) {
        $settings = esc_jira_get_settings();
    }

    $project    = $settings['default_project'] ?? '';
    $issue_type = $settings['default_issue_type'] ?? 'Task';

    if (empty($project)) {
        return ['error' => 'No default Jira project configured.'];
    }

    $body = [
        'fields' => [
            'project'   => ['key' => $project],
            'issuetype' => ['name' => $issue_type],
            'summary'   => $ticket['subject'] ?? 'Escalated Ticket',
            'description' => [
                'type'    => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type'    => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $ticket['description'] ?? '',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    // TODO: Implement Jira API call
    $response = esc_jira_api_request('POST', '/rest/api/3/issue', $body, $settings);

    if (isset($response['key'])) {
        // Link the new issue to the ticket
        esc_jira_add_link($ticket['id'], $response['key']);
    }

    return $response;
}

/**
 * Fetch a Jira issue by key.
 *
 * @param string $issue_key e.g. "PROJ-123"
 *
 * @return array Issue data or error
 */
function esc_jira_get_issue(string $issue_key): array
{
    // TODO: Implement Jira API call
    return esc_jira_api_request('GET', "/rest/api/3/issue/{$issue_key}");
}

/**
 * Search Jira issues using JQL.
 *
 * @param string $jql JQL query string
 * @param int    $max Maximum results
 *
 * @return array Search results or error
 */
function esc_jira_search_issues(string $jql, int $max = 10): array
{
    // TODO: Implement Jira API call
    return esc_jira_api_request('GET', '/rest/api/3/search?jql=' . urlencode($jql) . '&maxResults=' . $max);
}

/**
 * Transition a Jira issue to a new status.
 *
 * @param string $issue_key  Jira issue key
 * @param string $transition_id  Transition ID
 *
 * @return array Response or error
 */
function esc_jira_transition_issue(string $issue_key, string $transition_id): array
{
    $body = [
        'transition' => ['id' => $transition_id],
    ];

    // TODO: Implement Jira API call
    return esc_jira_api_request('POST', "/rest/api/3/issue/{$issue_key}/transitions", $body);
}

// ---------------------------------------------------------------------------
// Webhook handler: Jira -> Escalated
// ---------------------------------------------------------------------------

/**
 * Handle incoming Jira webhooks.
 *
 * Jira sends webhooks for events like issue_updated, jira:issue_updated, etc.
 * This handler processes status changes and syncs them back to Escalated.
 *
 * @param array $payload The decoded JSON webhook payload from Jira
 */
function esc_jira_handle_webhook(array $payload): void
{
    $settings = esc_jira_get_settings();
    $sync_direction = $settings['sync_direction'] ?? 'escalated_to_jira';

    // Only process inbound syncs if direction allows it
    if ($sync_direction === 'escalated_to_jira') {
        return;
    }

    $event = $payload['webhookEvent'] ?? $payload['issue_event_type_name'] ?? '';
    $issue = $payload['issue'] ?? [];
    $issue_key = $issue['key'] ?? '';

    if (empty($issue_key)) {
        return;
    }

    // Find the linked Escalated ticket
    $ticket_id = esc_jira_get_ticket_for_issue($issue_key);

    if ($ticket_id === null) {
        return;
    }

    // Handle issue updated events
    if (in_array($event, ['jira:issue_updated', 'issue_updated'])) {
        $changelog = $payload['changelog'] ?? [];
        $items     = $changelog['items'] ?? [];

        foreach ($items as $change) {
            $field = $change['field'] ?? '';

            // Sync status changes
            if ($field === 'status') {
                $new_status = $change['toString'] ?? '';

                if (function_exists('escalated_update_ticket')) {
                    $mapped_status = esc_jira_map_status_from_jira($new_status);
                    if ($mapped_status) {
                        escalated_update_ticket($ticket_id, ['status' => $mapped_status]);
                    }
                }
            }

            // Sync assignee changes
            if ($field === 'assignee') {
                $new_assignee = $change['to'] ?? '';

                if (function_exists('escalated_update_ticket') && !empty($new_assignee)) {
                    // TODO: Map Jira user to Escalated agent
                    // escalated_update_ticket($ticket_id, ['assignee_id' => $mapped_agent_id]);
                }
            }
        }
    }
}

/**
 * Map a Jira status name to an Escalated ticket status.
 *
 * @param string $jira_status Jira status name (e.g. "To Do", "In Progress", "Done")
 *
 * @return string|null Escalated status or null if no mapping
 */
function esc_jira_map_status_from_jira(string $jira_status): ?string
{
    $map = [
        'To Do'       => 'open',
        'Open'        => 'open',
        'In Progress' => 'in_progress',
        'In Review'   => 'in_progress',
        'Done'        => 'resolved',
        'Closed'      => 'closed',
        'Resolved'    => 'resolved',
    ];

    return $map[$jira_status] ?? null;
}

/**
 * Map an Escalated ticket status to a Jira status name.
 *
 * @param string $escalated_status Escalated status
 *
 * @return string|null Jira status name or null
 */
function esc_jira_map_status_to_jira(string $escalated_status): ?string
{
    $map = [
        'open'        => 'To Do',
        'pending'     => 'To Do',
        'in_progress' => 'In Progress',
        'resolved'    => 'Done',
        'closed'      => 'Done',
    ];

    return $map[$escalated_status] ?? null;
}

// ---------------------------------------------------------------------------
// Page component: Jira link panel in ticket sidebar
// ---------------------------------------------------------------------------

escalated_add_page_component('ticket.show', 'sidebar', [
    'component' => 'JiraLinkPanel',
    'props'     => [
        'pluginSlug' => ESC_JIRA_SLUG,
    ],
    'order'     => 30,
]);

// ---------------------------------------------------------------------------
// Page component: Jira settings panel in admin integrations
// ---------------------------------------------------------------------------

escalated_add_page_component('admin.settings', 'integrations', [
    'component' => 'JiraConfig',
    'props'     => [
        'pluginSlug' => ESC_JIRA_SLUG,
    ],
    'order'     => 10,
]);

// ---------------------------------------------------------------------------
// Action: auto-create Jira issue when a ticket is created (if configured)
// ---------------------------------------------------------------------------

escalated_add_action('ticket.created', function ($ticket) {
    $settings = esc_jira_get_settings();

    if (empty($settings['auto_create'])) {
        return;
    }

    if (empty($settings['jira_url']) || empty($settings['api_email']) || empty($settings['api_token'])) {
        return;
    }

    if (empty($settings['default_project'])) {
        return;
    }

    // Create the Jira issue
    $result = esc_jira_create_issue($ticket, $settings);

    if (isset($result['key'])) {
        // Broadcast that a Jira issue was auto-created
        if (function_exists('escalated_broadcast')) {
            escalated_broadcast('ticket.' . $ticket['id'], 'jira.issue.created', [
                'ticket_id'      => $ticket['id'],
                'jira_issue_key' => $result['key'],
            ]);
        }
    }
}, 10);

// ---------------------------------------------------------------------------
// Filter: add "Link to Jira" and "Create Jira Issue" to ticket actions
// ---------------------------------------------------------------------------

escalated_add_filter('ticket.actions', function (array $actions, $ticket = null) {
    $settings = esc_jira_get_settings();

    // Only show Jira actions if the plugin is configured
    if (empty($settings['jira_url'])) {
        return $actions;
    }

    $actions[] = [
        'id'    => 'jira-link-issue',
        'label' => 'Link to Jira',
        'icon'  => 'link',
        'group' => 'integrations',
        'order' => 10,
    ];

    $actions[] = [
        'id'    => 'jira-create-issue',
        'label' => 'Create Jira Issue',
        'icon'  => 'external-link',
        'group' => 'integrations',
        'order' => 11,
    ];

    return $actions;
}, 10);

// ---------------------------------------------------------------------------
// Action: sync status changes from Escalated to Jira
// ---------------------------------------------------------------------------

escalated_add_action('ticket.status.changed', function ($ticket_id, $new_status, $old_status) {
    $settings = esc_jira_get_settings();
    $sync_direction = $settings['sync_direction'] ?? 'escalated_to_jira';

    // Only sync outbound if direction allows it
    if ($sync_direction === 'jira_to_escalated') {
        return;
    }

    $links = esc_jira_get_links_for_ticket($ticket_id);

    if (empty($links)) {
        return;
    }

    $jira_status = esc_jira_map_status_to_jira($new_status);

    if ($jira_status === null) {
        return;
    }

    // TODO: For each linked issue, transition to the mapped status
    // This requires fetching available transitions first, then finding
    // the transition that leads to the target status.
    foreach ($links as $link) {
        $issue_key = $link['jira_issue_key'];
        // TODO: Implement Jira API call
        // $transitions = esc_jira_api_request('GET', "/rest/api/3/issue/{$issue_key}/transitions");
        // Find matching transition and execute it
    }
}, 10);

// ---------------------------------------------------------------------------
// Action: handle incoming Jira webhook
// ---------------------------------------------------------------------------

escalated_add_action('webhook.jira', function ($payload) {
    esc_jira_handle_webhook($payload);
}, 10);

// ---------------------------------------------------------------------------
// Menu item registration
// ---------------------------------------------------------------------------

escalated_register_menu_item([
    'id'         => 'jira-settings',
    'label'      => 'Jira Integration',
    'icon'       => 'link',
    'route'      => '/settings/integrations/jira',
    'parent'     => 'settings.integrations',
    'order'      => 10,
    'capability' => 'manage_settings',
]);

// ---------------------------------------------------------------------------
// Activation hook
// ---------------------------------------------------------------------------

escalated_add_action('escalated_plugin_activated_jira', function () {
    // Create default settings if none exist
    if (!file_exists(ESC_JIRA_CONFIG_FILE)) {
        esc_jira_save_settings(esc_jira_default_settings());
    }

    // Create empty links file if none exists
    if (!file_exists(ESC_JIRA_LINKS_FILE)) {
        esc_jira_save_links([]);
    }
}, 10);

// ---------------------------------------------------------------------------
// Deactivation hook
// ---------------------------------------------------------------------------

escalated_add_action('escalated_plugin_deactivated_jira', function () {
    // Settings and links are preserved so they survive a disable/enable cycle.
    // To fully remove data, the user should uninstall the plugin.

    // Broadcast deactivation
    if (function_exists('escalated_broadcast')) {
        escalated_broadcast('plugins', 'jira.deactivated', [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }
}, 10);
