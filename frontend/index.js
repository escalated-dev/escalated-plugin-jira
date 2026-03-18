import { defineEscalatedPlugin } from '@escalated-dev/escalated'
import JiraLinkPanel from './components/JiraLinkPanel.vue'
import JiraConfig from './components/JiraConfig.vue'

export default defineEscalatedPlugin({
    name: 'Jira Integration',
    slug: 'jira',
    version: '0.1.0',
    description: 'Link Escalated tickets to Jira issues, sync status, and auto-create issues',

    components: {
        JiraLinkPanel,
        JiraConfig,
    },

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
                    const jiraService = context?.$escalated?.inject?.('jira')
                    if (jiraService) jiraService.openCreateDialog(ticket)
                },
            },
            {
                id: 'jira-link-issue',
                label: 'Link Jira Issue',
                icon: 'link',
                handler: (ticket, context) => {
                    const jiraService = context?.$escalated?.inject?.('jira')
                    if (jiraService) jiraService.openLinkDialog(ticket)
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
        menuItems: [
            {
                id: 'jira-settings',
                label: 'Jira Integration',
                icon: 'link',
                route: '/settings/integrations/jira',
                parent: 'settings.integrations',
                order: 10,
                capability: 'manage_settings',
            },
        ],
    },

    hooks: {
        'ticket.created': (ticket, context) => {
            const jiraService = context?.$escalated?.inject?.('jira')
            if (!jiraService) return
            const settings = jiraService.state.settings
            if (settings?.auto_create && ticket?.id) {
                setTimeout(() => jiraService.fetchLinkedIssues(ticket.id), 2000)
            }
        },

        'ticket.status.changed': (ticket, context) => {
            const jiraService = context?.$escalated?.inject?.('jira')
            if (!jiraService || !ticket?.id) return
            jiraService.fetchLinkedIssues(ticket.id)
        },

        'ticket.sidebar.panels': (panels, ticket) => {
            return [
                ...panels,
                {
                    id: 'jira-linked-issues',
                    title: 'Jira Issues',
                    component: JiraLinkPanel,
                    icon: 'link',
                    order: 30,
                    props: { ticketId: ticket?.id ?? null },
                },
            ]
        },
    },

    setup(context) {
        const { reactive, ref } = context.vue || {}
        const _reactive = reactive || ((o) => o)
        const _ref = ref || ((v) => ({ value: v }))

        const state = _reactive({
            settings: {},
            links: {},
            loading: false,
            connected: false,
        })

        const saving = _ref(false)

        const dialogs = _reactive({
            createOpen: false,
            linkOpen: false,
            activeTicket: null,
        })

        const apiBase = () => '/api/plugins/jira'

        async function apiRequest(path, options = {}) {
            const url = `${apiBase()}${path}`
            const headers = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            }
            if (options.body && typeof options.body === 'object') {
                headers['Content-Type'] = 'application/json'
                options.body = JSON.stringify(options.body)
            }
            const response = await fetch(url, { ...options, headers })
            if (!response.ok) {
                const error = await response.json().catch(() => ({}))
                throw new Error(error.message || `API request failed: ${response.status}`)
            }
            return response.json()
        }

        async function fetchSettings() {
            try {
                const data = await apiRequest('/settings')
                state.settings = data || {}
            } catch (err) {
                console.error('[jira] Failed to fetch settings:', err)
            }
        }

        async function saveSettings(newSettings) {
            saving.value = true
            try {
                const data = await apiRequest('/settings', { method: 'PUT', body: newSettings })
                state.settings = data || newSettings
                return data
            } catch (err) {
                console.error('[jira] Failed to save settings:', err)
                throw err
            } finally {
                saving.value = false
            }
        }

        async function testConnection(connectionSettings) {
            try {
                const data = await apiRequest('/test-connection', {
                    method: 'POST',
                    body: connectionSettings || {},
                })
                state.connected = data?.success || false
                return data
            } catch (err) {
                state.connected = false
                return { success: false, message: err.message }
            }
        }

        async function fetchLinkedIssues(ticketId) {
            if (!ticketId) return
            try {
                const data = await apiRequest(`/links?ticket_id=${ticketId}`)
                state.links[ticketId] = Array.isArray(data) ? data : data.links || []
            } catch (err) {
                console.error('[jira] Failed to fetch links:', err)
                state.links[ticketId] = []
            }
        }

        async function linkIssue(ticketId, issueKey) {
            const data = await apiRequest('/links', {
                method: 'POST',
                body: { ticket_id: ticketId, jira_issue_key: issueKey },
            })
            if (!state.links[ticketId]) state.links[ticketId] = []
            state.links[ticketId].push(data)
            return data
        }

        async function unlinkIssue(ticketId, issueKey) {
            await apiRequest('/links', {
                method: 'DELETE',
                body: { ticket_id: ticketId, jira_issue_key: issueKey },
            })
            if (state.links[ticketId]) {
                state.links[ticketId] = state.links[ticketId].filter(
                    (l) => l.jira_issue_key !== issueKey,
                )
            }
        }

        async function createIssue(ticketId, issueData) {
            saving.value = true
            try {
                const data = await apiRequest('/issues', {
                    method: 'POST',
                    body: { ticket_id: ticketId, ...issueData },
                })
                if (data?.key && ticketId) {
                    if (!state.links[ticketId]) state.links[ticketId] = []
                    state.links[ticketId].push({
                        ticket_id: ticketId,
                        jira_issue_key: data.key,
                        issue_data: data,
                    })
                }
                return data
            } finally {
                saving.value = false
            }
        }

        async function searchIssues(query) {
            try {
                const data = await apiRequest(`/issues/search?q=${encodeURIComponent(query)}`)
                return Array.isArray(data) ? data : data.issues || []
            } catch (err) {
                console.error('[jira] Search failed:', err)
                return []
            }
        }

        async function getIssue(issueKey) {
            try {
                return await apiRequest(`/issues/${issueKey}`)
            } catch {
                return null
            }
        }

        function openCreateDialog(ticket) {
            dialogs.activeTicket = ticket
            dialogs.createOpen = true
        }

        function openLinkDialog(ticket) {
            dialogs.activeTicket = ticket
            dialogs.linkOpen = true
        }

        function closeDialogs() {
            dialogs.createOpen = false
            dialogs.linkOpen = false
            dialogs.activeTicket = null
        }

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
        })
    },
})
