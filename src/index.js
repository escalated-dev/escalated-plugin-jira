import { defineEscalatedPlugin } from '@escalated-dev/escalated';
import JiraLinkPanel from './components/JiraLinkPanel.vue';
import JiraConfig from './components/JiraConfig.vue';

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
                order: 30,
            },
        ],
        ticketActions: [
            {
                id: 'jira-create-issue',
                label: 'Create Jira Issue',
                icon: 'external-link',
                handler: (ticket, context) => {
                    const jiraService = context?.$escalated?.inject?.('jira');
                    if (jiraService) {
                        jiraService.openCreateDialog(ticket);
                    }
                },
            },
            {
                id: 'jira-link-issue',
                label: 'Link Jira Issue',
                icon: 'link',
                handler: (ticket, context) => {
                    const jiraService = context?.$escalated?.inject?.('jira');
                    if (jiraService) {
                        jiraService.openLinkDialog(ticket);
                    }
                },
            },
        ],
        settingsPanels: [
            {
                id: 'jira-settings',
                title: 'Jira Integration',
                component: JiraConfig,
                icon: 'link',
                category: 'integrations',
            },
        ],
        ticketListColumns: [
            {
                id: 'jira-issue-key',
                label: 'Jira Issue',
                width: 120,
                sortable: true,
                render: (ticket, context) => {
                    const jiraService = context?.$escalated?.inject?.('jira');
                    if (!jiraService) return '';
                    const links = jiraService.state.links[ticket?.id] || [];
                    if (links.length === 0) return '';
                    return links.map((l) => l.jira_issue_key).join(', ');
                },
            },
        ],
    },

    hooks: {
        /**
         * When a ticket is created, auto-create a Jira issue if configured.
         * The actual auto-creation is handled server-side via Plugin.php.
         * On the frontend we just refresh links if the ticket view is open.
         */
        'ticket.created': (ticket, context) => {
            const jiraService = context?.$escalated?.inject?.('jira');
            if (!jiraService) return;

            const settings = jiraService.state.settings;
            if (settings?.auto_create && ticket?.id) {
                // Delay to let the backend auto-create complete
                setTimeout(() => {
                    jiraService.fetchLinkedIssues(ticket.id);
                }, 2000);
            }
        },

        /**
         * Sync resolution status to linked Jira issues.
         * The actual sync is handled server-side. On the frontend,
         * we refresh the linked issue data to reflect the change.
         */
        'ticket.resolved': (ticket, context) => {
            const jiraService = context?.$escalated?.inject?.('jira');
            if (!jiraService || !ticket?.id) return;

            jiraService.fetchLinkedIssues(ticket.id);
        },

        /**
         * When ticket status changes, refresh linked issues to show
         * any server-side synced status changes.
         */
        'ticket.status.changed': (ticket, context) => {
            const jiraService = context?.$escalated?.inject?.('jira');
            if (!jiraService || !ticket?.id) return;

            jiraService.fetchLinkedIssues(ticket.id);
        },

        /**
         * Extend ticket sidebar panels.
         */
        'ticket.sidebar.panels': (panels, ticket) => {
            return [
                ...panels,
                {
                    id: 'jira-linked-issues',
                    title: 'Jira Issues',
                    component: JiraLinkPanel,
                    icon: 'link',
                    order: 30,
                    props: {
                        ticketId: ticket?.id || null,
                    },
                },
            ];
        },

        /**
         * Extend admin settings navigation.
         */
        'admin.settings.nav': (items) => {
            return [
                ...items,
                {
                    id: 'jira',
                    label: 'Jira Integration',
                    icon: 'link',
                    section: 'integrations',
                    order: 10,
                },
            ];
        },

        /**
         * Enrich ticket serialization with Jira link data.
         */
        'ticket.serialize': (ticketData, context) => {
            const jiraService = context?.$escalated?.inject?.('jira');
            if (!jiraService) return ticketData;

            const links = jiraService.state.links[ticketData?.id] || [];

            return {
                ...ticketData,
                _jiraIssues: links,
                _hasJiraLinks: links.length > 0,
            };
        },
    },

    setup(context) {
        const { reactive, ref } = context.vue || {};
        const _reactive = reactive || ((o) => o);
        const _ref = ref || ((v) => ({ value: v }));

        // ------------------------------------------------------------------
        // Reactive state
        // ------------------------------------------------------------------
        const state = _reactive({
            settings: {},
            links: {},        // { [ticketId]: [ { ticket_id, jira_issue_key, issue_data } ] }
            loading: false,
            connected: false,
        });

        const saving = _ref(false);

        // Dialog state for create/link modals (shared across components)
        const dialogs = _reactive({
            createOpen: false,
            linkOpen: false,
            activeTicket: null,
        });

        // ------------------------------------------------------------------
        // API helpers
        // ------------------------------------------------------------------
        const apiBase = () => {
            if (context.route) {
                return context.route('plugins.jira.api');
            }
            return '/api/plugins/jira';
        };

        async function apiRequest(path, options = {}) {
            const url = `${apiBase()}${path}`;
            const headers = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            };

            if (options.body && typeof options.body === 'object') {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }

            // TODO: Implement Jira API call
            const response = await fetch(url, { ...options, headers });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || `API request failed: ${response.status}`);
            }

            return response.json();
        }

        // ------------------------------------------------------------------
        // Settings operations
        // ------------------------------------------------------------------

        async function fetchSettings() {
            try {
                const data = await apiRequest('/settings');
                state.settings = data || {};
            } catch (err) {
                console.error('[jira] Failed to fetch settings:', err);
                state.settings = {};
            }
        }

        async function saveSettings(newSettings) {
            saving.value = true;
            try {
                const data = await apiRequest('/settings', {
                    method: 'PUT',
                    body: newSettings,
                });
                state.settings = data || newSettings;
                return data;
            } catch (err) {
                console.error('[jira] Failed to save settings:', err);
                throw err;
            } finally {
                saving.value = false;
            }
        }

        async function testConnection(connectionSettings) {
            try {
                const data = await apiRequest('/test-connection', {
                    method: 'POST',
                    body: connectionSettings || {},
                });
                state.connected = data?.success || false;
                return data;
            } catch (err) {
                console.error('[jira] Connection test failed:', err);
                state.connected = false;
                return { success: false, message: err.message };
            }
        }

        // ------------------------------------------------------------------
        // Link operations
        // ------------------------------------------------------------------

        async function fetchLinkedIssues(ticketId) {
            if (!ticketId) return;
            try {
                const data = await apiRequest(`/links?ticket_id=${ticketId}`);
                state.links[ticketId] = Array.isArray(data) ? data : data.links || [];
            } catch (err) {
                console.error('[jira] Failed to fetch links:', err);
                state.links[ticketId] = [];
            }
        }

        async function linkIssue(ticketId, issueKey) {
            try {
                const data = await apiRequest('/links', {
                    method: 'POST',
                    body: { ticket_id: ticketId, jira_issue_key: issueKey },
                });
                if (!state.links[ticketId]) {
                    state.links[ticketId] = [];
                }
                state.links[ticketId].push(data);
                return data;
            } catch (err) {
                console.error('[jira] Failed to link issue:', err);
                throw err;
            }
        }

        async function unlinkIssue(ticketId, issueKey) {
            try {
                await apiRequest('/links', {
                    method: 'DELETE',
                    body: { ticket_id: ticketId, jira_issue_key: issueKey },
                });
                if (state.links[ticketId]) {
                    state.links[ticketId] = state.links[ticketId].filter(
                        (l) => l.jira_issue_key !== issueKey,
                    );
                }
            } catch (err) {
                console.error('[jira] Failed to unlink issue:', err);
                throw err;
            }
        }

        // ------------------------------------------------------------------
        // Issue operations
        // ------------------------------------------------------------------

        async function createIssue(ticketId, issueData) {
            saving.value = true;
            try {
                const data = await apiRequest('/issues', {
                    method: 'POST',
                    body: { ticket_id: ticketId, ...issueData },
                });
                if (data?.key && ticketId) {
                    if (!state.links[ticketId]) {
                        state.links[ticketId] = [];
                    }
                    state.links[ticketId].push({
                        ticket_id: ticketId,
                        jira_issue_key: data.key,
                        issue_data: data,
                    });
                }
                return data;
            } catch (err) {
                console.error('[jira] Failed to create issue:', err);
                throw err;
            } finally {
                saving.value = false;
            }
        }

        async function searchIssues(query) {
            try {
                const data = await apiRequest(
                    `/issues/search?q=${encodeURIComponent(query)}`,
                );
                return Array.isArray(data) ? data : data.issues || [];
            } catch (err) {
                console.error('[jira] Search failed:', err);
                return [];
            }
        }

        async function getIssue(issueKey) {
            try {
                return await apiRequest(`/issues/${issueKey}`);
            } catch (err) {
                console.error('[jira] Failed to get issue:', err);
                return null;
            }
        }

        // ------------------------------------------------------------------
        // Dialog helpers
        // ------------------------------------------------------------------

        function openCreateDialog(ticket) {
            dialogs.activeTicket = ticket;
            dialogs.createOpen = true;
        }

        function openLinkDialog(ticket) {
            dialogs.activeTicket = ticket;
            dialogs.linkOpen = true;
        }

        function closeDialogs() {
            dialogs.createOpen = false;
            dialogs.linkOpen = false;
            dialogs.activeTicket = null;
        }

        // ------------------------------------------------------------------
        // Provide the Jira service to child components
        // ------------------------------------------------------------------
        context.provide('jira', {
            state,
            saving,
            dialogs,
            fetchSettings,
            saveSettings,
            testConnection,
            fetchLinkedIssues,
            linkIssue,
            unlinkIssue,
            createIssue,
            searchIssues,
            getIssue,
            openCreateDialog,
            openLinkDialog,
            closeDialogs,
        });
    },
});
