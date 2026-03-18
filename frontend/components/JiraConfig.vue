<template>
    <div :class="['w-full max-w-3xl mx-auto space-y-8', dark ? 'text-gray-200' : 'text-gray-800']">

        <!-- Header -->
        <div>
            <h2 :class="['text-xl font-semibold', dark ? 'text-white' : 'text-gray-900']">Jira Integration</h2>
            <p :class="['mt-1 text-sm', dark ? 'text-gray-400' : 'text-gray-500']">
                Connect your Jira instance to link issues, sync statuses, and auto-create Jira issues from tickets.
            </p>
        </div>

        <!-- ================================================================= -->
        <!-- Connection Section                                                -->
        <!-- ================================================================= -->
        <section :class="['rounded-lg border p-5 space-y-4', dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200']">
            <h3 :class="['text-sm font-semibold uppercase tracking-wide', dark ? 'text-gray-400' : 'text-gray-500']">
                Connection
            </h3>

            <!-- Jira Site URL -->
            <div>
                <label :class="['block text-sm font-medium mb-1', dark ? 'text-gray-300' : 'text-gray-700']">
                    Jira Site URL
                </label>
                <input
                    v-model="form.jira_url"
                    type="url"
                    placeholder="https://your-org.atlassian.net"
                    :class="inputClass"
                />
                <p :class="['mt-1 text-xs', dark ? 'text-gray-500' : 'text-gray-400']">
                    The base URL of your Jira Cloud or Server instance.
                </p>
            </div>

            <!-- API Email -->
            <div>
                <label :class="['block text-sm font-medium mb-1', dark ? 'text-gray-300' : 'text-gray-700']">
                    API Email
                </label>
                <input
                    v-model="form.api_email"
                    type="email"
                    placeholder="user@example.com"
                    :class="inputClass"
                />
                <p :class="['mt-1 text-xs', dark ? 'text-gray-500' : 'text-gray-400']">
                    The email address associated with your Jira API token.
                </p>
            </div>

            <!-- API Token -->
            <div>
                <label :class="['block text-sm font-medium mb-1', dark ? 'text-gray-300' : 'text-gray-700']">
                    API Token
                </label>
                <div class="relative">
                    <input
                        v-model="form.api_token"
                        :type="showToken ? 'text' : 'password'"
                        placeholder="Enter your Jira API token"
                        :class="inputClass"
                    />
                    <button
                        type="button"
                        @click="showToken = !showToken"
                        :class="[
                            'absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded transition-colors',
                            dark ? 'text-gray-500 hover:text-gray-300' : 'text-gray-400 hover:text-gray-600',
                        ]"
                    >
                        <svg v-if="showToken" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.05 6.05m3.828 3.828L6.05 6.05M17.95 17.95L14.12 14.12m3.83 3.83l2.1 2.1M14.12 14.12l2.1 2.1" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <p :class="['mt-1 text-xs', dark ? 'text-gray-500' : 'text-gray-400']">
                    Generate a token at
                    <a href="https://id.atlassian.com/manage-profile/security/api-tokens" target="_blank" rel="noopener" class="text-indigo-500 hover:underline">
                        Atlassian API Tokens
                    </a>.
                </p>
            </div>

            <!-- Test Connection -->
            <div class="flex items-center gap-3">
                <button
                    @click="handleTestConnection"
                    :disabled="testingConnection || !form.jira_url || !form.api_email || !form.api_token"
                    :class="[
                        'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                        testingConnection || !form.jira_url || !form.api_email || !form.api_token
                            ? (dark ? 'bg-gray-700 text-gray-500 cursor-not-allowed' : 'bg-gray-100 text-gray-400 cursor-not-allowed')
                            : (dark ? 'bg-indigo-600 hover:bg-indigo-500 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'),
                    ]"
                >
                    {{ testingConnection ? 'Testing...' : 'Test Connection' }}
                </button>

                <!-- Status indicator -->
                <div v-if="connectionStatus" class="flex items-center gap-1.5 text-sm">
                    <span
                        :class="[
                            'w-2 h-2 rounded-full',
                            connectionStatus === 'success' ? 'bg-green-500' : 'bg-red-500',
                        ]"
                    ></span>
                    <span :class="connectionStatus === 'success' ? 'text-green-500' : 'text-red-500'">
                        {{ connectionMessage }}
                    </span>
                </div>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- Defaults Section                                                  -->
        <!-- ================================================================= -->
        <section :class="['rounded-lg border p-5 space-y-4', dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200']">
            <h3 :class="['text-sm font-semibold uppercase tracking-wide', dark ? 'text-gray-400' : 'text-gray-500']">
                Defaults
            </h3>

            <!-- Default Project -->
            <div>
                <label :class="['block text-sm font-medium mb-1', dark ? 'text-gray-300' : 'text-gray-700']">
                    Default Project
                </label>
                <input
                    v-model="form.default_project"
                    type="text"
                    placeholder="e.g. PROJ"
                    :class="inputClass"
                />
                <p :class="['mt-1 text-xs', dark ? 'text-gray-500' : 'text-gray-400']">
                    The Jira project key to use when creating new issues. This field will be populated from the Jira API when connected.
                </p>
            </div>

            <!-- Default Issue Type -->
            <div>
                <label :class="['block text-sm font-medium mb-1', dark ? 'text-gray-300' : 'text-gray-700']">
                    Default Issue Type
                </label>
                <select v-model="form.default_issue_type" :class="inputClass">
                    <option value="Bug">Bug</option>
                    <option value="Task">Task</option>
                    <option value="Story">Story</option>
                    <option value="Epic">Epic</option>
                </select>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- Auto-Create Section                                               -->
        <!-- ================================================================= -->
        <section :class="['rounded-lg border p-5 space-y-4', dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200']">
            <div class="flex items-center justify-between">
                <div>
                    <h3 :class="['text-sm font-semibold uppercase tracking-wide', dark ? 'text-gray-400' : 'text-gray-500']">
                        Auto-Create
                    </h3>
                    <p :class="['mt-1 text-sm', dark ? 'text-gray-400' : 'text-gray-500']">
                        Automatically create a Jira issue whenever a new ticket is created in Escalated.
                    </p>
                </div>
                <button
                    @click="form.auto_create = !form.auto_create"
                    :class="[
                        'relative inline-flex h-6 w-11 items-center rounded-full transition-colors shrink-0',
                        form.auto_create
                            ? 'bg-indigo-600'
                            : (dark ? 'bg-gray-600' : 'bg-gray-300'),
                    ]"
                    role="switch"
                    :aria-checked="form.auto_create"
                >
                    <span
                        :class="[
                            'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                            form.auto_create ? 'translate-x-6' : 'translate-x-1',
                        ]"
                    ></span>
                </button>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- Sync Direction Section                                            -->
        <!-- ================================================================= -->
        <section :class="['rounded-lg border p-5 space-y-4', dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200']">
            <h3 :class="['text-sm font-semibold uppercase tracking-wide', dark ? 'text-gray-400' : 'text-gray-500']">
                Sync Direction
            </h3>
            <p :class="['text-sm', dark ? 'text-gray-400' : 'text-gray-500']">
                Choose how status and field changes are synchronized between Escalated and Jira.
            </p>

            <div class="space-y-3">
                <label
                    v-for="option in syncDirectionOptions"
                    :key="option.value"
                    :class="[
                        'flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors',
                        form.sync_direction === option.value
                            ? (dark ? 'border-indigo-500 bg-indigo-500/10' : 'border-indigo-500 bg-indigo-50')
                            : (dark ? 'border-gray-700 hover:border-gray-600' : 'border-gray-200 hover:border-gray-300'),
                    ]"
                >
                    <input
                        type="radio"
                        :value="option.value"
                        v-model="form.sync_direction"
                        class="mt-0.5 text-indigo-600"
                    />
                    <div>
                        <div :class="['text-sm font-medium', dark ? 'text-gray-200' : 'text-gray-800']">
                            {{ option.label }}
                        </div>
                        <div :class="['text-xs mt-0.5', dark ? 'text-gray-500' : 'text-gray-400']">
                            {{ option.description }}
                        </div>
                    </div>
                </label>
            </div>
        </section>

        <!-- ================================================================= -->
        <!-- Field Mapping Section                                             -->
        <!-- ================================================================= -->
        <section :class="['rounded-lg border p-5 space-y-4', dark ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200']">
            <h3 :class="['text-sm font-semibold uppercase tracking-wide', dark ? 'text-gray-400' : 'text-gray-500']">
                Field Mapping
            </h3>
            <p :class="['text-sm', dark ? 'text-gray-400' : 'text-gray-500']">
                Map Escalated ticket fields to their corresponding Jira issue fields.
            </p>

            <div :class="['overflow-hidden rounded-lg border', dark ? 'border-gray-700' : 'border-gray-200']">
                <table class="w-full text-sm">
                    <thead>
                        <tr :class="dark ? 'bg-gray-750 border-b border-gray-700' : 'bg-gray-50 border-b border-gray-200'">
                            <th :class="['px-4 py-2.5 text-left font-medium', dark ? 'text-gray-400' : 'text-gray-500']">
                                Escalated Field
                            </th>
                            <th :class="['px-4 py-2.5 text-center font-medium', dark ? 'text-gray-400' : 'text-gray-500']">
                            </th>
                            <th :class="['px-4 py-2.5 text-left font-medium', dark ? 'text-gray-400' : 'text-gray-500']">
                                Jira Field
                            </th>
                            <th :class="['px-4 py-2.5 text-right font-medium', dark ? 'text-gray-400' : 'text-gray-500']">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(mapping, index) in form.field_mapping"
                            :key="index"
                            :class="[
                                'border-b last:border-b-0 transition-colors',
                                dark ? 'border-gray-700 hover:bg-gray-750' : 'border-gray-100 hover:bg-gray-50',
                            ]"
                        >
                            <td class="px-4 py-2.5">
                                <select
                                    v-model="mapping.escalated_field"
                                    :class="[
                                        'w-full px-2.5 py-1.5 rounded-md border text-sm',
                                        dark ? 'bg-gray-700 border-gray-600 text-gray-200' : 'bg-white border-gray-300 text-gray-800',
                                    ]"
                                >
                                    <option v-for="f in escalatedFields" :key="f.value" :value="f.value">
                                        {{ f.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-2 py-2.5 text-center">
                                <svg :class="['w-4 h-4 mx-auto', dark ? 'text-gray-500' : 'text-gray-400']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </td>
                            <td class="px-4 py-2.5">
                                <select
                                    v-model="mapping.jira_field"
                                    :class="[
                                        'w-full px-2.5 py-1.5 rounded-md border text-sm',
                                        dark ? 'bg-gray-700 border-gray-600 text-gray-200' : 'bg-white border-gray-300 text-gray-800',
                                    ]"
                                >
                                    <option v-for="f in jiraFields" :key="f.value" :value="f.value">
                                        {{ f.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <button
                                    @click="removeMapping(index)"
                                    :class="[
                                        'p-1 rounded transition-colors',
                                        dark ? 'text-gray-500 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-400 hover:text-red-600 hover:bg-red-50',
                                    ]"
                                    title="Remove mapping"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <button
                @click="addMapping"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md border transition-colors',
                    dark ? 'border-gray-600 text-gray-400 hover:text-gray-300 hover:border-gray-500' : 'border-gray-300 text-gray-600 hover:text-gray-700 hover:border-gray-400',
                ]"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Add Mapping
            </button>
        </section>

        <!-- ================================================================= -->
        <!-- Save Button                                                       -->
        <!-- ================================================================= -->
        <div class="flex items-center justify-between pt-2 pb-4">
            <div v-if="saveMessage" :class="['text-sm', saveSuccess ? 'text-green-500' : 'text-red-500']">
                {{ saveMessage }}
            </div>
            <div v-else></div>
            <button
                @click="handleSave"
                :disabled="isSaving"
                :class="[
                    'px-5 py-2 text-sm font-medium rounded-md transition-colors',
                    isSaving
                        ? (dark ? 'bg-gray-700 text-gray-500 cursor-not-allowed' : 'bg-gray-100 text-gray-400 cursor-not-allowed')
                        : 'bg-indigo-600 hover:bg-indigo-700 text-white',
                ]"
            >
                {{ isSaving ? 'Saving...' : 'Save Settings' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, inject, onMounted } from 'vue';

const dark = inject('esc-dark', false);
const jiraService = inject('jira', null);

// ---------------------------------------------------------------------------
// Form state
// ---------------------------------------------------------------------------

const form = reactive({
    jira_url: '',
    api_email: '',
    api_token: '',
    default_project: '',
    default_issue_type: 'Task',
    auto_create: false,
    sync_direction: 'escalated_to_jira',
    field_mapping: [
        { escalated_field: 'subject', jira_field: 'summary' },
        { escalated_field: 'description', jira_field: 'description' },
        { escalated_field: 'priority', jira_field: 'priority' },
        { escalated_field: 'status', jira_field: 'status' },
        { escalated_field: 'assignee', jira_field: 'assignee' },
    ],
});

// ---------------------------------------------------------------------------
// Connection testing
// ---------------------------------------------------------------------------

const showToken = ref(false);
const testingConnection = ref(false);
const connectionStatus = ref(null); // null | 'success' | 'error'
const connectionMessage = ref('');

async function handleTestConnection() {
    testingConnection.value = true;
    connectionStatus.value = null;
    connectionMessage.value = '';

    try {
        const result = jiraService
            ? await jiraService.testConnection({
                  jira_url: form.jira_url,
                  api_email: form.api_email,
                  api_token: form.api_token,
              })
            : { success: false, message: 'Jira service not available.' };

        connectionStatus.value = result.success ? 'success' : 'error';
        connectionMessage.value = result.message || (result.success ? 'Connected successfully.' : 'Connection failed.');
    } catch (err) {
        connectionStatus.value = 'error';
        connectionMessage.value = err.message || 'Connection failed.';
    } finally {
        testingConnection.value = false;
    }
}

// ---------------------------------------------------------------------------
// Sync direction options
// ---------------------------------------------------------------------------

const syncDirectionOptions = [
    {
        value: 'escalated_to_jira',
        label: 'Escalated to Jira',
        description: 'Changes in Escalated are pushed to Jira. Jira changes are not synced back.',
    },
    {
        value: 'jira_to_escalated',
        label: 'Jira to Escalated',
        description: 'Changes in Jira are pushed to Escalated. Escalated changes are not synced to Jira.',
    },
    {
        value: 'bidirectional',
        label: 'Bidirectional',
        description: 'Changes are synced in both directions between Escalated and Jira.',
    },
];

// ---------------------------------------------------------------------------
// Field mapping
// ---------------------------------------------------------------------------

const escalatedFields = [
    { value: 'subject', label: 'Subject' },
    { value: 'description', label: 'Description' },
    { value: 'priority', label: 'Priority' },
    { value: 'status', label: 'Status' },
    { value: 'assignee', label: 'Assignee' },
    { value: 'tags', label: 'Tags' },
    { value: 'category', label: 'Category' },
    { value: 'due_date', label: 'Due Date' },
];

const jiraFields = [
    { value: 'summary', label: 'Summary' },
    { value: 'description', label: 'Description' },
    { value: 'priority', label: 'Priority' },
    { value: 'status', label: 'Status' },
    { value: 'assignee', label: 'Assignee' },
    { value: 'labels', label: 'Labels' },
    { value: 'components', label: 'Components' },
    { value: 'duedate', label: 'Due Date' },
];

function addMapping() {
    form.field_mapping.push({ escalated_field: '', jira_field: '' });
}

function removeMapping(index) {
    form.field_mapping.splice(index, 1);
}

// ---------------------------------------------------------------------------
// Saving
// ---------------------------------------------------------------------------

const isSaving = ref(false);
const saveMessage = ref('');
const saveSuccess = ref(false);

async function handleSave() {
    isSaving.value = true;
    saveMessage.value = '';

    try {
        const payload = {
            jira_url: form.jira_url,
            api_email: form.api_email,
            api_token: form.api_token,
            default_project: form.default_project,
            default_issue_type: form.default_issue_type,
            auto_create: form.auto_create,
            sync_direction: form.sync_direction,
            field_mapping: form.field_mapping.filter(
                (m) => m.escalated_field && m.jira_field,
            ),
        };

        if (jiraService) {
            await jiraService.saveSettings(payload);
        }

        saveSuccess.value = true;
        saveMessage.value = 'Settings saved successfully.';
    } catch (err) {
        saveSuccess.value = false;
        saveMessage.value = err.message || 'Failed to save settings.';
    } finally {
        isSaving.value = false;
        setTimeout(() => {
            saveMessage.value = '';
        }, 4000);
    }
}

// ---------------------------------------------------------------------------
// Input class helper
// ---------------------------------------------------------------------------

const inputClass = computed(() => [
    'w-full px-3 py-2 rounded-md border text-sm transition-colors',
    dark
        ? 'bg-gray-700 border-gray-600 text-gray-200 placeholder-gray-500 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500'
        : 'bg-white border-gray-300 text-gray-800 placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500',
]);

// ---------------------------------------------------------------------------
// Load existing settings on mount
// ---------------------------------------------------------------------------

onMounted(async () => {
    if (jiraService) {
        await jiraService.fetchSettings();
        const s = jiraService.state.settings || {};
        if (s.jira_url !== undefined) form.jira_url = s.jira_url;
        if (s.api_email !== undefined) form.api_email = s.api_email;
        if (s.api_token !== undefined) form.api_token = s.api_token;
        if (s.default_project !== undefined) form.default_project = s.default_project;
        if (s.default_issue_type !== undefined) form.default_issue_type = s.default_issue_type;
        if (s.auto_create !== undefined) form.auto_create = s.auto_create;
        if (s.sync_direction !== undefined) form.sync_direction = s.sync_direction;
        if (Array.isArray(s.field_mapping) && s.field_mapping.length > 0) {
            form.field_mapping = s.field_mapping.map((m) => ({ ...m }));
        }
    }
});
</script>
