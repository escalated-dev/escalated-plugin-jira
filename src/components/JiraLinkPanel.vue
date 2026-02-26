<template>
    <div class="jira-panel">
        <div class="jira-panel-header">
            <h3>Jira Issues</h3>
            <button class="jira-link-btn" @click="linkIssue">+ Link</button>
        </div>
        <div class="jira-panel-body">
            <div v-if="linkedIssues.length === 0" class="jira-empty">
                <p class="jira-placeholder">No linked Jira issues.</p>
                <p class="jira-info">
                    Link existing Jira issues or create new ones directly from this ticket. Status changes will be
                    synced bi-directionally.
                </p>
            </div>
            <div v-else class="jira-issue-list">
                <div v-for="issue in linkedIssues" :key="issue.key" class="jira-issue-item">
                    <span class="jira-issue-key">{{ issue.key }}</span>
                    <span class="jira-issue-summary">{{ issue.summary }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    ticketId: {
        type: [String, Number],
        default: null,
    },
});

const linkedIssues = ref([]);

const linkIssue = () => {
    // Open Jira issue search dialog
};
</script>

<style scoped>
.jira-panel {
    background: var(--escalated-bg-secondary, #1e1e2e);
    border: 1px solid var(--escalated-border, #2e2e3e);
    border-radius: 8px;
    padding: 16px;
    color: var(--escalated-text, #e0e0e0);
}

.jira-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.jira-panel-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.jira-link-btn {
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 4px;
    border: 1px solid var(--escalated-border, #2e2e3e);
    background: var(--escalated-bg-tertiary, #2e2e3e);
    color: var(--escalated-text, #e0e0e0);
    cursor: pointer;
}

.jira-link-btn:hover {
    background: var(--escalated-bg-hover, #3e3e4e);
}

.jira-empty .jira-placeholder {
    font-size: 13px;
    color: var(--escalated-text-muted, #888);
    margin-bottom: 8px;
}

.jira-empty .jira-info {
    font-size: 12px;
    color: var(--escalated-text-muted, #666);
    line-height: 1.5;
}

.jira-issue-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    border-bottom: 1px solid var(--escalated-border, #2e2e3e);
}

.jira-issue-key {
    font-size: 12px;
    font-weight: 600;
    color: var(--escalated-accent, #6366f1);
    white-space: nowrap;
}

.jira-issue-summary {
    font-size: 13px;
    color: var(--escalated-text, #e0e0e0);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
