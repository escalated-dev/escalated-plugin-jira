<?php

namespace Escalated\Plugins\Jira\Support;

/**
 * Configuration manager for the Jira Integration plugin.
 *
 * Handles reading, writing, and validating plugin settings stored
 * as a JSON file on disk.
 */
class Config
{
    const VERSION     = '0.1.0';
    const SLUG        = 'jira';
    const CONFIG_DIR  = __DIR__ . '/../config';
    const CONFIG_FILE = __DIR__ . '/../config/settings.json';
    const LINKS_FILE  = __DIR__ . '/../config/links.json';

    /**
     * Return the default settings structure.
     */
    public static function defaults(): array
    {
        return [
            'jira_url'           => '',
            'api_email'          => '',
            'api_token'          => '',
            'default_project'    => '',
            'default_issue_type' => 'Task',
            'auto_create'        => false,
            'sync_direction'     => 'escalated_to_jira',
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
    public static function all(): array
    {
        if (!file_exists(self::CONFIG_FILE)) {
            return self::defaults();
        }

        $json = file_get_contents(self::CONFIG_FILE);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return self::defaults();
        }

        return array_merge(self::defaults(), $data);
    }

    /**
     * Retrieve a single setting by key, with an optional default.
     */
    public static function get(string $key, $default = null)
    {
        $settings = self::all();

        return $settings[$key] ?? $default;
    }

    /**
     * Persist settings to the JSON config file.
     */
    public static function save(array $settings): bool
    {
        $dir = dirname(self::CONFIG_FILE);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents(self::CONFIG_FILE, $json) !== false;
    }

    /**
     * Check whether the minimum required credentials are configured.
     */
    public static function isConfigured(): bool
    {
        $settings = self::all();

        return !empty($settings['jira_url'])
            && !empty($settings['api_email'])
            && !empty($settings['api_token']);
    }

    /**
     * Called when the plugin is activated.
     *
     * Creates default settings and an empty links file if they do not exist.
     */
    public static function onActivate(): void
    {
        if (!file_exists(self::CONFIG_FILE)) {
            self::save(self::defaults());
        }

        if (!file_exists(self::LINKS_FILE)) {
            $dir = dirname(self::LINKS_FILE);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents(self::LINKS_FILE, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Called when the plugin is deactivated.
     *
     * Settings and links are preserved so they survive a disable/enable cycle.
     */
    public static function onDeactivate(): void
    {
        if (function_exists('escalated_broadcast')) {
            escalated_broadcast('plugins', 'jira.deactivated', [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        }
    }
}
