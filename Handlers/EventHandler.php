<?php

namespace Escalated\Plugins\Jira\Handlers;

use Escalated\Plugins\Jira\Services\JiraClient;
use Escalated\Plugins\Jira\Support\Config;
use Escalated\Plugins\Jira\Support\LinkStore;

/**
 * Handles Escalated-side events and pushes changes to Jira.
 */
class EventHandler
{
    /**
     * Handle the ticket.created event.
     *
     * When auto_create is enabled and the plugin is fully configured,
     * creates a corresponding Jira issue and stores the link.
     */
    public static function onTicketCreated($ticket): void
    {
        $settings = Config::all();

        if (empty($settings['auto_create'])) {
            return;
        }

        if (!Config::isConfigured()) {
            return;
        }

        if (empty($settings['default_project'])) {
            return;
        }

        $result = JiraClient::createIssue($ticket);

        if (!empty($result['key'])) {
            LinkStore::add($ticket['id'], $result['key']);

            if (function_exists('escalated_broadcast')) {
                escalated_broadcast('ticket.' . $ticket['id'], 'jira.issue.created', [
                    'ticket_id'      => $ticket['id'],
                    'jira_issue_key' => $result['key'],
                ]);
            }
        }
    }

    /**
     * Handle the ticket.status.changed event.
     *
     * When the sync direction permits outbound sync, transitions every
     * linked Jira issue to the mapped status.
     */
    public static function onTicketStatusChanged($ticketId, $newStatus, $oldStatus): void
    {
        $settings      = Config::all();
        $syncDirection = $settings['sync_direction'] ?? 'escalated_to_jira';

        if ($syncDirection === 'jira_to_escalated') {
            return;
        }

        $links = LinkStore::forTicket($ticketId);

        if (empty($links)) {
            return;
        }

        $jiraStatus = WebhookHandler::mapStatusToJira($newStatus);

        if ($jiraStatus === null) {
            return;
        }

        foreach ($links as $link) {
            $issueKey = $link['jira_issue_key'] ?? '';
            if ($issueKey !== '') {
                JiraClient::transitionToStatus($issueKey, $jiraStatus);
            }
        }
    }
}
