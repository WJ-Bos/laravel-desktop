<script setup lang="ts">
import Button from 'primevue/button';
import { comboLabel } from '@/composables/useShortcuts';
import { openModule, workspace } from '@/composables/useWorkspace';
</script>

<template>
    <div class="erp-watermark">
        <p class="erp-watermark-title">No panels open</p>
        <p class="erp-watermark-text">
            Pick a headend, gateway or meter in the navigator, or press
            <kbd>{{ comboLabel('mod+k') }}</kbd> to find one by serial or name.
        </p>
        <div class="erp-watermark-actions">
            <Button
                label="Open dashboard"
                icon="pi pi-gauge"
                size="small"
                @click="openModule('dashboard')"
            />
            <Button
                label="Open meters"
                icon="pi pi-bolt"
                size="small"
                severity="secondary"
                outlined
                @click="openModule('meters')"
            />
            <Button
                label="Search"
                icon="pi pi-search"
                size="small"
                severity="secondary"
                text
                @click="workspace.paletteOpen = true"
            />
        </div>
    </div>
</template>
