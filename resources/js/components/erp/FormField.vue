<script setup lang="ts">
/**
 * Desktop-style form row: label on the left, control on the right and the
 * server's validation message directly under the control.
 */
defineProps<{
    label: string;
    inputId: string;
    error?: string | null;
    required?: boolean;
    hint?: string;
    wide?: boolean;
}>();
</script>

<template>
    <div
        class="erp-field"
        :class="{ 'erp-field--invalid': error, 'erp-field--wide': wide }"
    >
        <label :for="inputId" class="erp-field-label">
            {{ label
            }}<span v-if="required" class="erp-required" aria-hidden="true"
                >*</span
            >
        </label>
        <div class="erp-field-control">
            <slot />
            <small
                v-if="error"
                :id="`${inputId}-error`"
                class="erp-field-error"
                role="alert"
            >
                <i class="pi pi-times-circle" aria-hidden="true" /> {{ error }}
            </small>
            <small v-else-if="hint" class="erp-field-hint">{{ hint }}</small>
        </div>
    </div>
</template>
