import { defineEscalatedPlugin } from '@escalated-dev/escalated';
import JiraLinkPanel from './components/JiraLinkPanel.vue';

export default defineEscalatedPlugin({
    name: 'Jira Integration',
    slug: 'jira',
    version: '0.1.0',
    description: 'Jira issue linking, sync status, and create Jira issues from tickets',

    extensions: {
        sidebarPanels: [
            {
                id: 'jira-link-panel',
                title: 'Jira Issues',
                component: JiraLinkPanel,
                icon: 'link',
            },
        ],
        ticketActions: [
            {
                id: 'jira-create-issue',
                label: 'Create Jira Issue',
                icon: 'external-link',
                handler: (ticket) => {
                    // Open Jira issue creation dialog
                },
            },
            {
                id: 'jira-link-issue',
                label: 'Link Jira Issue',
                icon: 'link',
                handler: (ticket) => {
                    // Open Jira issue search and link dialog
                },
            },
        ],
        settingsPanels: [
            {
                id: 'jira-settings',
                title: 'Jira Integration',
                component: JiraLinkPanel,
                icon: 'jira',
                category: 'integrations',
            },
        ],
        ticketListColumns: [
            {
                id: 'jira-issue-key',
                label: 'Jira Issue',
                width: 120,
                sortable: true,
            },
        ],
    },

    hooks: {
        'ticket.created': (ticket) => {
            // Auto-create Jira issue if configured
        },
        'ticket.resolved': (ticket) => {
            // Sync resolution status to linked Jira issues
        },
    },

    setup(context) {
        context.provide('jira', {
            // Jira service will be provided here
        });
    },
});
