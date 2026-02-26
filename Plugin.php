<?php

/**
 * Jira Integration Plugin for Escalated
 *
 * Links Escalated tickets to Jira issues, syncs status changes,
 * and optionally auto-creates Jira issues from new tickets.
 */

if (!defined('ESCALATED_LOADED')) {
    exit('Direct access not allowed.');
}

require_once __DIR__ . '/Support/Config.php';
require_once __DIR__ . '/Support/LinkStore.php';
require_once __DIR__ . '/Services/JiraClient.php';
require_once __DIR__ . '/Handlers/EventHandler.php';
require_once __DIR__ . '/Handlers/WebhookHandler.php';

use Escalated\Plugins\Jira\Support\Config;
use Escalated\Plugins\Jira\Handlers\EventHandler;
use Escalated\Plugins\Jira\Handlers\WebhookHandler;

// -- Actions ---------------------------------------------------------------

escalated_add_action('ticket.created', [EventHandler::class, 'onTicketCreated'], 10);
escalated_add_action('ticket.status.changed', [EventHandler::class, 'onTicketStatusChanged'], 10);
escalated_add_action('webhook.jira', [WebhookHandler::class, 'handle'], 10);

// -- Filters ---------------------------------------------------------------

escalated_add_filter('ticket.actions', function (array $actions, $ticket = null) {
    if (!Config::isConfigured()) {
        return $actions;
    }

    $actions[] = [
        'id' => 'jira-link-issue',  'label' => 'Link to Jira',
        'icon' => 'link',           'group' => 'integrations', 'order' => 10,
    ];
    $actions[] = [
        'id' => 'jira-create-issue','label' => 'Create Jira Issue',
        'icon' => 'external-link',  'group' => 'integrations', 'order' => 11,
    ];

    return $actions;
}, 10);

// -- UI Components ---------------------------------------------------------

escalated_add_page_component('ticket.show', 'sidebar', [
    'component' => 'JiraLinkPanel', 'props' => ['pluginSlug' => Config::SLUG], 'order' => 30,
]);

escalated_add_page_component('admin.settings', 'integrations', [
    'component' => 'JiraConfig', 'props' => ['pluginSlug' => Config::SLUG], 'order' => 10,
]);

// -- Menu ------------------------------------------------------------------

escalated_register_menu_item([
    'id' => 'jira-settings', 'label' => 'Jira Integration', 'icon' => 'link',
    'route' => '/settings/integrations/jira', 'parent' => 'settings.integrations',
    'order' => 10, 'capability' => 'manage_settings',
]);

// -- Lifecycle Hooks -------------------------------------------------------

escalated_add_action('escalated_plugin_activated_jira', [Config::class, 'onActivate'], 10);
escalated_add_action('escalated_plugin_deactivated_jira', [Config::class, 'onDeactivate'], 10);
