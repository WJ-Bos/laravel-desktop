<script setup lang="ts">
import Dialog from 'primevue/dialog';
import { comboLabel, isMac } from '@/composables/useShortcuts';
import { workspace } from '@/composables/useWorkspace';

const groups: { title: string; rows: { keys: string[]; action: string }[] }[] =
    [
        {
            title: 'Workspace',
            rows: [
                {
                    keys: ['mod+k'],
                    action: 'Find a headend, gateway, meter or module',
                },
                {
                    keys: ['mod+n', 'alt+n'],
                    action: 'New record in the active module',
                },
                { keys: ['mod+s'], action: 'Save the active form' },
                { keys: ['mod+w', 'alt+w'], action: 'Close the active panel' },
                {
                    keys: ['alt+m'],
                    action: 'Maximize or restore the active panel',
                },
                { keys: ['alt+r'], action: 'Refresh the active panel' },
            ],
        },
        {
            title: 'Navigation',
            rows: [
                { keys: ['alt+1'], action: 'Dashboard' },
                { keys: ['alt+2'], action: 'Meters' },
                { keys: ['alt+3'], action: 'Readings' },
                { keys: ['alt+4'], action: 'Events' },
                { keys: ['alt+5'], action: 'Gateways' },
                { keys: ['alt+6'], action: 'Headends' },
                { keys: ['alt+7'], action: 'Reports' },
            ],
        },
        {
            title: 'View',
            rows: [
                { keys: ['mod+b'], action: 'Show or hide the navigator' },
                { keys: ['mod+shift+l'], action: 'Switch light / dark theme' },
                { keys: ['f1'], action: 'This list' },
            ],
        },
    ];
</script>

<template>
    <Dialog
        v-model:visible="workspace.shortcutsOpen"
        modal
        header="Keyboard shortcuts"
        class="erp-dialog"
        :style="{ width: '34rem' }"
    >
        <div class="erp-shortcuts">
            <section v-for="group in groups" :key="group.title">
                <h3>{{ group.title }}</h3>
                <table>
                    <tbody>
                        <tr v-for="row in group.rows" :key="row.action">
                            <td>
                                <template
                                    v-for="(key, i) in row.keys"
                                    :key="key"
                                >
                                    <span v-if="i > 0" class="erp-muted">
                                        or
                                    </span>
                                    <kbd>{{ comboLabel(key) }}</kbd>
                                </template>
                            </td>
                            <td>{{ row.action }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <p v-if="!isMac" class="erp-muted erp-shortcuts-note">
                Browsers keep Ctrl+N and Ctrl+W for their own windows and tabs,
                so in a normal browser tab use Alt+N and Alt+W. Installed as an
                app window, the Ctrl versions work too.
            </p>
        </div>
    </Dialog>

    <Dialog
        v-model:visible="workspace.aboutOpen"
        modal
        header="About MOL4"
        class="erp-dialog"
        :style="{ width: '28rem' }"
    >
        <p>
            MOL4 is a meter data management desk: headends, gateways and meters,
            their half-hourly readings and events, and the reports built on
            them. Every figure comes from generated demo data.
        </p>
        <p class="erp-muted">
            Laravel, Inertia, Vue 3, PrimeVue, AG Grid Community, dockview and
            Apache ECharts.
        </p>
    </Dialog>
</template>
