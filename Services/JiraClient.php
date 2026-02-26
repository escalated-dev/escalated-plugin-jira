<?php

namespace Escalated\Plugins\Jira\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Escalated\Plugins\Jira\Support\Config;

/**
 * Jira REST API v3 client.
 *
 * All methods are static and use Laravel's Http facade for real HTTP
 * requests authenticated via Basic Auth (email + API token).
 */
class JiraClient
{
    /**
     * Send an authenticated request to the Jira REST API.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $path   API path, e.g. "/rest/api/3/issue"
     * @param array  $body   Request body (query params for GET, JSON body for POST/PUT)
     *
     * @return array Normalised response with 'ok' boolean flag
     */
    public static function request(string $method, string $path, array $body = []): array
    {
        $settings = Config::all();
        $jiraUrl  = rtrim($settings['jira_url'] ?? '', '/');
        $email    = $settings['api_email'] ?? '';
        $token    = $settings['api_token'] ?? '';

        if ($jiraUrl === '' || $email === '' || $token === '') {
            return ['ok' => false, 'error' => 'Jira connection is not configured.'];
        }

        $url = $jiraUrl . $path;

        try {
            $http = Http::withBasicAuth($email, $token)
                ->acceptJson()
                ->contentType('application/json')
                ->timeout(15);

            $response = match (strtoupper($method)) {
                'GET'    => $http->get($url, $body),
                'PUT'    => $http->put($url, $body),
                'DELETE' => $http->delete($url),
                default  => $http->post($url, $body),
            };

            $data = $response->json();

            if ($response->failed()) {
                $errors = $data['errorMessages'] ?? [$data['message'] ?? $response->body()];
                Log::warning('Jira API error', [
                    'path'   => $path,
                    'status' => $response->status(),
                    'errors' => $errors,
                ]);
                return ['ok' => false, 'error' => implode('; ', $errors), 'http_code' => $response->status()];
            }

            return array_merge(['ok' => true], is_array($data) ? $data : []);
        } catch (\Exception $e) {
            Log::error('Jira API exception', ['path' => $path, 'message' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify that the stored credentials can reach Jira.
     */
    public static function testConnection(): array
    {
        $response = self::request('GET', '/rest/api/3/myself');

        if (!($response['ok'] ?? false)) {
            return ['success' => false, 'message' => $response['error'] ?? 'Connection failed'];
        }

        return ['success' => true, 'message' => 'Connected as ' . ($response['displayName'] ?? 'Unknown')];
    }

    /**
     * Create a Jira issue from an Escalated ticket.
     */
    public static function createIssue(array $ticket): array
    {
        $settings  = Config::all();
        $project   = $settings['default_project'] ?? '';
        $issueType = $settings['default_issue_type'] ?? 'Task';

        if ($project === '') {
            return ['ok' => false, 'error' => 'No default Jira project configured.'];
        }

        return self::request('POST', '/rest/api/3/issue', [
            'fields' => [
                'project'   => ['key' => $project],
                'issuetype' => ['name' => $issueType],
                'summary'   => $ticket['subject'] ?? 'Escalated Ticket',
                'description' => [
                    'type'    => 'doc',
                    'version' => 1,
                    'content' => [[
                        'type'    => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $ticket['description'] ?? '']],
                    ]],
                ],
            ],
        ]);
    }

    /**
     * Fetch a Jira issue by its key (e.g. "PROJ-123").
     */
    public static function getIssue(string $issueKey): array
    {
        return self::request('GET', "/rest/api/3/issue/{$issueKey}");
    }

    /**
     * Search Jira issues using JQL.
     */
    public static function searchIssues(string $jql, int $max = 10): array
    {
        return self::request('GET', '/rest/api/3/search?jql=' . urlencode($jql) . '&maxResults=' . $max);
    }

    /**
     * Fetch available transitions for an issue.
     */
    public static function getTransitions(string $issueKey): array
    {
        return self::request('GET', "/rest/api/3/issue/{$issueKey}/transitions");
    }

    /**
     * Execute a specific transition on an issue.
     */
    public static function transitionIssue(string $issueKey, string $transitionId): array
    {
        return self::request('POST', "/rest/api/3/issue/{$issueKey}/transitions", [
            'transition' => ['id' => $transitionId],
        ]);
    }

    /**
     * Transition an issue to a named target status.
     *
     * Fetches available transitions, finds the one whose target status matches
     * (case-insensitive), and executes it.
     */
    public static function transitionToStatus(string $issueKey, string $targetStatusName): array
    {
        $transitionsResponse = self::getTransitions($issueKey);

        if (!($transitionsResponse['ok'] ?? false)) {
            return $transitionsResponse;
        }

        $transitions = $transitionsResponse['transitions'] ?? [];
        foreach ($transitions as $transition) {
            $toName = $transition['to']['name'] ?? '';
            if (strcasecmp($toName, $targetStatusName) === 0) {
                return self::transitionIssue($issueKey, (string) $transition['id']);
            }
        }

        return ['ok' => false, 'error' => "No transition found to status '{$targetStatusName}'"];
    }
}
