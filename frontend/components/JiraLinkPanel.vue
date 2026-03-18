<template>
    <div :class="['w-full', dark ? 'text-gray-200' : 'text-gray-800']">

        <!-- ================================================================= -->
        <!-- Panel header                                                      -->
        <!-- ================================================================= -->
        <div class="flex items-center justify-between mb-3">
            <h3 :class="['text-sm font-semibold', dark ? 'text-gray-300' : 'text-gray-700']">
                Jira Issues
            </h3>
            <div class="flex items-center gap-1.5">
                <button
                    @click="showLinkSearch = true"
                    :class="btnSmallClass"
                    title="Link existing issue"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </button>
                <button
                    @click="showCreateForm = true"
                    :class="btnSmallClass"
                    title="Create new issue"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Empty state                                                       -->
        <!-- ================================================================= -->
        <div
            v-if="linkedIssues.length === 0 && !showLinkSearch && !showCreateForm"
            :class="['text-center py-6 space-y-3', dark ? 'text-gray-500' : 'text-gray-400']"
        >
            <svg class="w-8 h-8 mx-auto opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            <p class="text-sm">No linked Jira issues.</p>
            <p class="text-xs leading-relaxed">
                Link existing Jira issues or create new ones directly from this ticket.
            </p>
            <div class="flex items-center justify-center gap-2 pt-1">
                <button
                    @click="showLinkSearch = true"
                    :class="[
                        'px-3 py-1.5 text-xs font-medium rounded-md border transition-colors',
                        dark ? 'border-gray-600 text-gray-400 hover:text-gray-300 hover:border-gray-500' : 'border-gray-300 text-gray-600 hover:text-gray-700 hover:border-gray-400',
                    ]"
                >
                    Link Issue
                </button>
                <button
                    @click="showCreateForm = true"
                    :class="[
                        'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                        dark ? 'bg-indigo-600 hover:bg-indigo-500 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white',
                    ]"
                >
                    Create Issue
                </button>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Linked issues list                                                -->
        <!-- ================================================================= -->
        <div v-if="linkedIssues.length > 0" class="space-y-2 mb-3">
            <div
                v-for="issue in linkedIssues"
                :key="issue.jira_issue_key"
                :class="[
                    'rounded-lg border p-3 space-y-2 transition-colors',
                    dark ? 'bg-gray-800 border-gray-700 hover:border-gray-600' : 'bg-white border-gray-200 hover:border-gray-300',
                ]"
            >
                <!-- Issue header: key + unlink -->
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <a
                            :href="getIssueUrl(issue.jira_issue_key)"
                            target="_blank"
                            rel="noopener"
                            :class="['text-sm font-semibold hover:underline', dark ? 'text-indigo-400' : 'text-indigo-600']"
                        >
                            {{ issue.jira_issue_key }}
                        </a>
                        <p :class="['text-sm mt-0.5 line-clamp-2', dark ? 'text-gray-300' : 'text-gray-700']">
                            {{ issue.issue_data?.summary || issue.summary || 'Untitled issue' }}
                        </p>
                    </div>
                    <button
                        @click="handleUnlink(issue.jira_issue_key)"
                        :class="[
                            'p-1 rounded shrink-0 transition-colors',
                            dark ? 'text-gray-500 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-400 hover:text-red-600 hover:bg-red-50',
                        ]"
                        title="Unlink issue"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Issue metadata: status, priority, assignee -->
                <div class="flex items-center flex-wrap gap-2">
                    <!-- Status badge -->
                    <span
                        :class="[
                            'inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full',
                            statusBadgeClass(issue.issue_data?.status || issue.status),
                        ]"
                    >
                        {{ issue.issue_data?.status || issue.status || 'Unknown' }}
                    </span>

                    <!-- Priority icon -->
                    <span
                        v-if="issue.issue_data?.priority || issue.priority"
                        :class="['flex items-center gap-1 text-xs', dark ? 'text-gray-400' : 'text-gray-500']"
                        :title="'Priority: ' + (issue.issue_data?.priority || issue.priority)"
                    >
                        <svg
                            class="w-3 h-3"
                            :class="priorityColorClass(issue.issue_data?.priority || issue.priority)"
                            fill="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path v-if="isPriorityHigh(issue.issue_data?.priority || issue.priority)" d="M12 2L2 22h20L12 2z" />
                            <path v-else-if="isPriorityLow(issue.issue_data?.priority || issue.priority)" d="M12 22L2 2h20L12 22z" />
                            <path v-else d="M4 12h16" stroke="currentColor" stroke-width="3" fill="none" />
                        </svg>
                        {{ issue.issue_data?.priority || issue.priority }}
                    </span>

                    <!-- Assignee -->
                    <span
                        v-if="issue.issue_data?.assignee || issue.assignee"
                        :class="['flex items-center gap-1 text-xs', dark ? 'text-gray-400' : 'text-gray-500']"
                    >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        {{ issue.issue_data?.assignee || issue.assignee }}
                    </span>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Link Issue search dialog                                          -->
        <!-- ================================================================= -->
        <div
            v-if="showLinkSearch"
            :class="['rounded-lg border p-4 space-y-3', dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200']"
        >
            <div class="flex items-center justify-between">
                <h4 :class="['text-sm font-medium', dark ? 'text-gray-300' : 'text-gray-700']">
                    Link Jira Issue
                </h4>
                <button
                    @click="closeLinkSearch"
                    :class="['p-1 rounded transition-colors', dark ? 'text-gray-500 hover:text-gray-300' : 'text-gray-400 hover:text-gray-600']"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <input
                v-model="searchQuery"
                type="text"
                placeholder="Enter issue key (e.g. PROJ-123) or search..."
                :class="[
                    'w-full px-3 py-2 rounded-md border text-sm',
                    dark ? 'bg-gray-700 border-gray-600 text-gray-200 placeholder-gray-500' : 'bg-white border-gray-300 text-gray-800 placeholder-gray-400',
                ]"
                @keydown.enter="handleSearch"
            />

            <!-- Search results -->
            <div v-if="searchResults.length > 0" :class="['max-h-40 overflow-y-auto rounded-md border divide-y', dark ? 'border-gray-600 divide-gray-700' : 'border-gray-200 divide-gray-100']">
                <button
                    v-for="result in searchResults"
                    :key="result.key"
                    @click="handleLinkIssue(result.key)"
                    :class="[
                        'w-full text-left px-3 py-2 flex items-center gap-2 transition-colors',
                        dark ? 'hover:bg-gray-700' : 'hover:bg-gray-50',
                    ]"
                >
                    <span :class="['text-xs font-semibold whitespace-nowrap', dark ? 'text-indigo-400' : 'text-indigo-600']">
                        {{ result.key }}
                    </span>
                    <span :class="['text-sm truncate', dark ? 'text-gray-300' : 'text-gray-700']">
                        {{ result.summary || result.fields?.summary || '' }}
                    </span>
                </button>
            </div>

            <div v-if="searchQuery && searchResults.length === 0 && !isSearching" :class="['text-xs text-center py-2', dark ? 'text-gray-500' : 'text-gray-400']">
                No results. You can enter an exact issue key to link directly.
            </div>

            <div class="flex items-center justify-end gap-2">
                <button
                    @click="closeLinkSearch"
                    :class="[
                        'px-3 py-1.5 text-xs rounded-md border transition-colors',
                        dark ? 'border-gray-600 text-gray-400 hover:text-gray-300' : 'border-gray-300 text-gray-600 hover:text-gray-700',
                    ]"
                >
                    Cancel
                </button>
                <button
                    @click="handleLinkDirect"
                    :disabled="!searchQuery.trim()"
                    :class="[
                        'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                        !searchQuery.trim()
                            ? (dark ? 'bg-gray-700 text-gray-500 cursor-not-allowed' : 'bg-gray-100 text-gray-400 cursor-not-allowed')
                            : (dark ? 'bg-indigo-600 hover:bg-indigo-500 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'),
                    ]"
                >
                    Link
                </button>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Create Issue form                                                 -->
        <!-- ================================================================= -->
        <div
            v-if="showCreateForm"
            :class="['rounded-lg border p-4 space-y-3', dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200']"
        >
            <div class="flex items-center justify-between">
                <h4 :class="['text-sm font-medium', dark ? 'text-gray-300' : 'text-gray-700']">
                    Create Jira Issue
                </h4>
                <button
                    @click="closeCreateForm"
                    :class="['p-1 rounded transition-colors', dark ? 'text-gray-500 hover:text-gray-300' : 'text-gray-400 hover:text-gray-600']"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Project -->
            <div>
                <label :class="['block text-xs font-medium mb-1', dark ? 'text-gray-400' : 'text-gray-500']">
                    Project
                </label>
                <input
                    v-model="createForm.project"
                    type="text"
                    placeholder="e.g. PROJ"
                    :class="[
                        'w-full px-3 py-2 rounded-md border text-sm',
                        dark ? 'bg-gray-700 border-gray-600 text-gray-200 placeholder-gray-500' : 'bg-white border-gray-300 text-gray-800 placeholder-gray-400',
                    ]"
                />
            </div>

            <!-- Issue Type -->
            <div>
                <label :class="['block text-xs font-medium mb-1', dark ? 'text-gray-400' : 'text-gray-500']">
                    Issue Type
                </label>
                <select
                    v-model="createForm.issue_type"
                    :class="[
                        'w-full px-3 py-2 rounded-md border text-sm',
                        dark ? 'bg-gray-700 border-gray-600 text-gray-200' : 'bg-white border-gray-300 text-gray-800',
                    ]"
                >
                    <option value="Bug">Bug</option>
                    <option value="Task">Task</option>
                    <option value="Story">Story</option>
                    <option value="Epic">Epic</option>
                </select>
            </div>

            <!-- Summary -->
            <div>
                <label :class="['block text-xs font-medium mb-1', dark ? 'text-gray-400' : 'text-gray-500']">
                    Summary
                </label>
                <input
                    v-model="createForm.summary"
                    type="text"
                    placeholder="Issue summary"
                    :class="[
                        'w-full px-3 py-2 rounded-md border text-sm',
                        dark ? 'bg-gray-700 border-gray-600 text-gray-200 placeholder-gray-500' : 'bg-white border-gray-300 text-gray-800 placeholder-gray-400',
                    ]"
                />
            </div>

            <!-- Description -->
            <div>
                <label :class="['block text-xs font-medium mb-1', dark ? 'text-gray-400' : 'text-gray-500']">
                    Description
                </label>
                <textarea
                    v-model="createForm.description"
                    rows="3"
                    placeholder="Issue description"
                    :class="[
                        'w-full px-3 py-2 rounded-md border text-sm resize-y',
                        dark ? 'bg-gray-700 border-gray-600 text-gray-200 placeholder-gray-500' : 'bg-white border-gray-300 text-gray-800 placeholder-gray-400',
                    ]"
                ></textarea>
            </div>

            <!-- Error message -->
            <div v-if="createError" class="text-xs text-red-500">
                {{ createError }}
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-2">
                <button
                    @click="closeCreateForm"
                    :class="[
                        'px-3 py-1.5 text-xs rounded-md border transition-colors',
                        dark ? 'border-gray-600 text-gray-400 hover:text-gray-300' : 'border-gray-300 text-gray-600 hover:text-gray-700',
                    ]"
                >
                    Cancel
                </button>
                <button
                    @click="handleCreateIssue"
                    :disabled="isCreating || !createForm.summary.trim()"
                    :class="[
                        'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                        isCreating || !createForm.summary.trim()
                            ? (dark ? 'bg-gray-700 text-gray-500 cursor-not-allowed' : 'bg-gray-100 text-gray-400 cursor-not-allowed')
                            : (dark ? 'bg-indigo-600 hover:bg-indigo-500 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'),
                    ]"
                >
                    {{ isCreating ? 'Creating...' : 'Create Issue' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, inject, onMounted, onUnmounted } from 'vue';

const dark = inject('esc-dark', false);
const jiraService = inject('jira', null);

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

const props = defineProps({
    ticketId: {
        type: [String, Number],
        default: null,
    },
    ticketSubject: {
        type: String,
        default: '',
    },
    ticketDescription: {
        type: String,
        default: '',
    },
});

// ---------------------------------------------------------------------------
// Linked issues state
// ---------------------------------------------------------------------------

const linkedIssues = computed(() => {
    if (!jiraService || !props.ticketId) return [];
    return jiraService.state.links[props.ticketId] || [];
});

// ---------------------------------------------------------------------------
// Link search state
// ---------------------------------------------------------------------------

const showLinkSearch = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
let searchDebounceTimer = null;

function closeLinkSearch() {
    showLinkSearch.value = false;
    searchQuery.value = '';
    searchResults.value = [];
}

async function handleSearch() {
    const q = searchQuery.value.trim();
    if (!q || !jiraService) return;

    isSearching.value = true;
    try {
        const results = await jiraService.searchIssues(q);
        searchResults.value = results;
    } catch (err) {
        console.error('[jira] Search failed:', err);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}

async function handleLinkIssue(issueKey) {
    if (!props.ticketId || !jiraService) return;

    try {
        await jiraService.linkIssue(props.ticketId, issueKey);
        closeLinkSearch();
    } catch (err) {
        console.error('[jira] Link failed:', err);
    }
}

function handleLinkDirect() {
    const key = searchQuery.value.trim().toUpperCase();
    if (key) {
        handleLinkIssue(key);
    }
}

// ---------------------------------------------------------------------------
// Create issue state
// ---------------------------------------------------------------------------

const showCreateForm = ref(false);
const isCreating = ref(false);
const createError = ref('');

const createForm = reactive({
    project: '',
    issue_type: 'Task',
    summary: '',
    description: '',
});

function closeCreateForm() {
    showCreateForm.value = false;
    createError.value = '';
    createForm.project = '';
    createForm.issue_type = 'Task';
    createForm.summary = '';
    createForm.description = '';
}

function prefillCreateForm() {
    // Pre-fill from ticket data
    if (props.ticketSubject) {
        createForm.summary = props.ticketSubject;
    }
    if (props.ticketDescription) {
        createForm.description = props.ticketDescription;
    }

    // Use default project from settings
    if (jiraService?.state?.settings?.default_project) {
        createForm.project = jiraService.state.settings.default_project;
    }
    if (jiraService?.state?.settings?.default_issue_type) {
        createForm.issue_type = jiraService.state.settings.default_issue_type;
    }
}

async function handleCreateIssue() {
    if (!props.ticketId || !jiraService) return;

    isCreating.value = true;
    createError.value = '';

    try {
        await jiraService.createIssue(props.ticketId, {
            project: createForm.project,
            issue_type: createForm.issue_type,
            summary: createForm.summary,
            description: createForm.description,
        });
        closeCreateForm();
    } catch (err) {
        createError.value = err.message || 'Failed to create issue.';
    } finally {
        isCreating.value = false;
    }
}

// ---------------------------------------------------------------------------
// Unlink handler
// ---------------------------------------------------------------------------

async function handleUnlink(issueKey) {
    if (!props.ticketId || !jiraService) return;

    try {
        await jiraService.unlinkIssue(props.ticketId, issueKey);
    } catch (err) {
        console.error('[jira] Unlink failed:', err);
    }
}

// ---------------------------------------------------------------------------
// Issue URL builder
// ---------------------------------------------------------------------------

function getIssueUrl(issueKey) {
    const baseUrl = jiraService?.state?.settings?.jira_url || '';
    if (!baseUrl) return '#';
    return `${baseUrl.replace(/\/$/, '')}/browse/${issueKey}`;
}

// ---------------------------------------------------------------------------
// Status badge styling
// ---------------------------------------------------------------------------

function statusBadgeClass(status) {
    if (!status) return dark ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-500';

    const s = status.toLowerCase();

    if (s === 'done' || s === 'resolved' || s === 'closed') {
        return 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400';
    }
    if (s === 'in progress' || s === 'in review') {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-400';
    }
    // To Do, Open, Backlog, etc.
    return 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400';
}

// ---------------------------------------------------------------------------
// Priority helpers
// ---------------------------------------------------------------------------

function isPriorityHigh(priority) {
    if (!priority) return false;
    const p = priority.toLowerCase();
    return p === 'highest' || p === 'high' || p === 'critical' || p === 'blocker';
}

function isPriorityLow(priority) {
    if (!priority) return false;
    const p = priority.toLowerCase();
    return p === 'lowest' || p === 'low' || p === 'trivial';
}

function priorityColorClass(priority) {
    if (!priority) return dark ? 'text-gray-500' : 'text-gray-400';

    const p = priority.toLowerCase();
    if (p === 'highest' || p === 'critical' || p === 'blocker') return 'text-red-500';
    if (p === 'high') return 'text-orange-500';
    if (p === 'medium' || p === 'normal') return 'text-yellow-500';
    if (p === 'low') return 'text-blue-500';
    if (p === 'lowest' || p === 'trivial') return 'text-green-500';
    return dark ? 'text-gray-500' : 'text-gray-400';
}

// ---------------------------------------------------------------------------
// Small button class
// ---------------------------------------------------------------------------

const btnSmallClass = computed(() => [
    'p-1.5 rounded-md border transition-colors',
    dark
        ? 'border-gray-700 text-gray-400 hover:text-gray-300 hover:border-gray-600 hover:bg-gray-750'
        : 'border-gray-200 text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50',
]);

// ---------------------------------------------------------------------------
// Auto-refresh interval
// ---------------------------------------------------------------------------

let refreshInterval = null;
const REFRESH_INTERVAL_MS = 30000; // 30 seconds

function startAutoRefresh() {
    stopAutoRefresh();
    if (!props.ticketId || !jiraService) return;

    refreshInterval = setInterval(() => {
        jiraService.fetchLinkedIssues(props.ticketId);
    }, REFRESH_INTERVAL_MS);
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

// ---------------------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------------------

onMounted(async () => {
    if (jiraService && props.ticketId) {
        await jiraService.fetchLinkedIssues(props.ticketId);
        startAutoRefresh();
    }

    // Load settings for default project, issue URL, etc.
    if (jiraService && !jiraService.state.settings?.jira_url) {
        await jiraService.fetchSettings();
    }
});

onUnmounted(() => {
    stopAutoRefresh();
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});
</script>
