<?php

namespace Escalated\Plugins\Jira\Handlers;

use Escalated\Plugins\Jira\Support\Config;
use Escalated\Plugins\Jira\Support\LinkStore;

/**
 * Handles incoming Jira webhook payloads and syncs changes to Escalated.
 */
class WebhookHandler
{
    /**
     * Process an incoming Jira webhook payload.
     *
     * Checks sync direction, extracts the issue key, finds the linked
     * Escalated ticket, and processes any changelog items (status and
     * assignee changes).
     */
    public static function handle(array $payload): void
    {
        $settings      = Config::all();
        $syncDirection = $settings['sync_direction'] ?? 'escalated_to_jira';

        // Only process inbound syncs if direction allows it
        if ($syncDirection === 'escalated_to_jira') {
            return;
        }

        $event    = $payload['webhookEvent'] ?? $payload['issue_event_type_name'] ?? '';
        $issue    = $payload['issue'] ?? [];
        $issueKey = $issue['key'] ?? '';

        if ($issueKey === '') {
            return;
        }

        // Find the linked Escalated ticket
        $ticketId = LinkStore::forIssue($issueKey);

        if ($ticketId === null) {
            return;
        }

        // Handle issue updated events
        if (in_array($event, ['jira:issue_updated', 'issue_updated'], true)) {
            $changelog = $payload['changelog'] ?? [];
            $items     = $changelog['items'] ?? [];

            foreach ($items as $change) {
                $field = $change['field'] ?? '';

                // Sync status changes
                if ($field === 'status') {
                    $newStatus = $change['toString'] ?? '';

                    if (function_exists('escalated_update_ticket')) {
                        $mappedStatus = self::mapStatusFromJira($newStatus);
                        if ($mappedStatus !== null) {
                            escalated_update_ticket($ticketId, ['status' => $mappedStatus]);
                        }
                    }
                }

                // Sync assignee changes
                if ($field === 'assignee') {
                    $newAssignee = $change['to'] ?? '';

                    if (function_exists('escalated_update_ticket') && $newAssignee !== '') {
                        if (function_exists('escalated_find_agent_by_jira_id')) {
                            $agentId = escalated_find_agent_by_jira_id($newAssignee);
                            if ($agentId !== null) {
                                escalated_update_ticket($ticketId, ['assignee_id' => $agentId]);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Map a Jira status name to an Escalated ticket status.
     *
     * @return string|null Escalated status or null if no mapping exists
     */
    public static function mapStatusFromJira(string $jiraStatus): ?string
    {
        $map = [
            'To Do'       => 'open',
            'Open'        => 'open',
            'In Progress' => 'in_progress',
            'In Review'   => 'in_progress',
            'Done'        => 'resolved',
            'Resolved'    => 'resolved',
            'Closed'      => 'closed',
        ];

        return $map[$jiraStatus] ?? null;
    }

    /**
     * Map an Escalated ticket status to a Jira status name.
     *
     * @return string|null Jira status name or null if no mapping exists
     */
    public static function mapStatusToJira(string $escalatedStatus): ?string
    {
        $map = [
            'open'        => 'To Do',
            'pending'     => 'To Do',
            'in_progress' => 'In Progress',
            'resolved'    => 'Done',
            'closed'      => 'Done',
        ];

        return $map[$escalatedStatus] ?? null;
    }
}
