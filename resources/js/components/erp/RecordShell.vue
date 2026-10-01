<script setup lang="ts">
import Button from 'primevue/button';
import Message from 'primevue/message';
import ProgressBar from 'primevue/progressbar';
import { comboLabel } from '@/composables/useShortcuts';

/**
 * Frame for every detail panel: title row with save/revert, unsaved and
 * loading state, then a scrollable form body and an optional side column.
 */
defineProps<{
    title: string;
    subtitle?: string;
    icon: string;
    dirty: boolean;
    saving: boolean;
    loading: boolean;
    isNew: boolean;
    loadError: string | null;
    errorCount: number;
}>();

defineEmits<{
    save: [];
    revert: [];
    retry: [];
}>();
</script>

<template>
    <div class="erp-record">
        <header class="erp-record-head">
            <i :class="icon" class="erp-record-icon" aria-hidden="true" />
            <div class="erp-record-titles">
                <h2 class="erp-record-title">{{ title }}</h2>
                <span v-if="subtitle" class="erp-record-subtitle">{{
                    subtitle
                }}</span>
            </div>
            <slot name="badges" />
            <span class="erp-toolbar-fill" />
            <span v-if="dirty" class="erp-dirty-note" role="status">
                <span class="erp-dirty-dot" aria-hidden="true" /> Unsaved
                changes
            </span>
            <slot name="actions" />
            <Button
                label="Revert"
                icon="pi pi-undo"
                size="small"
                severity="secondary"
                text
                :disabled="!dirty || saving"
                @click="$emit('revert')"
            />
            <Button
                v-tooltip.bottom="`Save (${comboLabel('mod+s')})`"
                :label="isNew ? 'Create' : 'Save'"
                icon="pi pi-save"
                size="small"
                :loading="saving"
                :disabled="(!dirty && !isNew) || loading"
                @click="$emit('save')"
            />
        </header>
        <ProgressBar
            v-if="loading || saving"
            mode="indeterminate"
            class="erp-record-progress"
        />

        <Message v-if="loadError" severity="error" class="erp-record-message">
            {{ loadError }}
            <Button
                label="Try again"
                size="small"
                text
                @click="$emit('retry')"
            />
        </Message>
        <Message
            v-else-if="errorCount > 0"
            severity="warn"
            class="erp-record-message"
            :closable="false"
        >
            {{ errorCount }}
            {{ errorCount === 1 ? 'field needs' : 'fields need' }} attention
            before this can be saved.
        </Message>

        <div v-if="!loadError" class="erp-record-body">
            <div class="erp-record-main">
                <slot />
            </div>
            <aside v-if="$slots.side" class="erp-record-side">
                <slot name="side" />
            </aside>
        </div>
    </div>
</template>
