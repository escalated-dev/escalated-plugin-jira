<?php

namespace Escalated\Plugins\Jira\Support;

/**
 * Manages the ticket_id <-> jira_issue_key mapping stored in a JSON file.
 */
class LinkStore
{
    /**
     * Return all stored links.
     *
     * @return array List of link entries: [ { ticket_id, jira_issue_key, linked_at } ]
     */
    public static function all(): array
    {
        if (!file_exists(Config::LINKS_FILE)) {
            return [];
        }

        $json = file_get_contents(Config::LINKS_FILE);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Save the full links array to disk.
     */
    public static function save(array $links): bool
    {
        $dir = dirname(Config::LINKS_FILE);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode(array_values($links), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents(Config::LINKS_FILE, $json) !== false;
    }

    /**
     * Get all Jira issue keys linked to a given ticket.
     */
    public static function forTicket($ticketId): array
    {
        $links = self::all();

        return array_values(array_filter($links, function ($link) use ($ticketId) {
            return (string) ($link['ticket_id'] ?? '') === (string) $ticketId;
        }));
    }

    /**
     * Get the ticket ID linked to a given Jira issue key.
     */
    public static function forIssue(string $issueKey): ?string
    {
        $links = self::all();

        foreach ($links as $link) {
            if (($link['jira_issue_key'] ?? '') === $issueKey) {
                return (string) $link['ticket_id'];
            }
        }

        return null;
    }

    /**
     * Add a new link between a ticket and a Jira issue.
     *
     * Prevents duplicate links for the same ticket/issue pair.
     */
    public static function add($ticketId, string $issueKey): array
    {
        $links = self::all();

        // Prevent duplicate links
        foreach ($links as $link) {
            if ((string) ($link['ticket_id'] ?? '') === (string) $ticketId
                && ($link['jira_issue_key'] ?? '') === $issueKey) {
                return $link;
            }
        }

        $entry = [
            'ticket_id'      => $ticketId,
            'jira_issue_key' => $issueKey,
            'linked_at'      => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $links[] = $entry;
        self::save($links);

        return $entry;
    }

    /**
     * Remove a link between a ticket and a Jira issue.
     */
    public static function remove($ticketId, string $issueKey): bool
    {
        $links = self::all();
        $originalCount = count($links);

        $links = array_filter($links, function ($link) use ($ticketId, $issueKey) {
            return !((string) ($link['ticket_id'] ?? '') === (string) $ticketId
                && ($link['jira_issue_key'] ?? '') === $issueKey);
        });

        if (count($links) < $originalCount) {
            self::save($links);
            return true;
        }

        return false;
    }
}
