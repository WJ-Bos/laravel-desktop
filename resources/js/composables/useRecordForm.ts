import type { DockviewPanelApi } from 'dockview-vue';
import { useToast } from 'primevue/usetoast';
import { computed, reactive, ref, shallowRef, watch } from 'vue';
import type { Ref } from 'vue';
import {
    emitRecordChange,
    openNew,
    setDirty,
    usePanelActions,
} from '@/composables/useWorkspace';
import type { RecordPanelParams } from '@/composables/useWorkspace';
import { ApiError, api } from '@/lib/api';
import { entities } from '@/lib/modules';
import type { EditableEntity, EntityRecordMap } from '@/types/erp';

/**
 * Load / edit / save cycle shared by every detail panel.
 *
 *  - `model` is the editable copy; `dirty` compares it to the last saved
 *    snapshot and drives the dot on the dockview tab and the close prompt.
 *  - Laravel 422 responses land in `errors`, keyed by field
 *    (`items.0.quantity` stays as-is for line-item grids).
 *  - Saving a new record converts the panel in place: its params get the
 *    new id and the tab is retitled.
 */

export interface RecordFormOptions<K extends EditableEntity, M> {
    entity: K;
    panel: DockviewPanelApi;
    params: RecordPanelParams;
    empty: () => M;
    fromRecord: (record: EntityRecordMap[K]) => M;
    toPayload: (model: M) => Record<string, unknown>;
}

export function useRecordForm<K extends EditableEntity, M extends object>(
    options: RecordFormOptions<K, M>,
) {
    const def = entities[options.entity];
    const toast = useToast();
    const panelId = options.panel.id;

    const id = ref<number | null>(options.params.id);
    const record = shallowRef<EntityRecordMap[K] | null>(null);
    const model = ref(options.empty()) as Ref<M>;
    const snapshot = ref(JSON.stringify(model.value));
    const errors = reactive<Record<string, string>>({});
    const loading = ref(false);
    const saving = ref(false);
    const loadError = ref<string | null>(null);

    if (options.params.preset) {
        Object.assign(model.value, options.params.preset);
    }

    const dirty = computed(
        () => JSON.stringify(model.value) !== snapshot.value,
    );
    const isNew = computed(() => id.value === null);

    watch(dirty, (value) => setDirty(panelId, value), { immediate: true });

    function clearErrors(): void {
        for (const key of Object.keys(errors)) {
            delete errors[key];
        }
    }

    function accept(data: EntityRecordMap[K]): void {
        record.value = data;
        model.value = options.fromRecord(data);
        snapshot.value = JSON.stringify(model.value);
    }

    async function load(): Promise<void> {
        // A new record's "saved" state is the blank form (taken before any
        // preset was applied), so presets count as unsaved edits.
        if (id.value === null) {
            return;
        }

        loading.value = true;
        loadError.value = null;

        try {
            const { data } = await api.get<{ data: EntityRecordMap[K] }>(
                `${def.endpoint}/${id.value}`,
            );
            accept(data);
            options.panel.setTitle(def.title(data));
        } catch (e) {
            loadError.value =
                e instanceof ApiError && e.status === 404
                    ? `This ${def.singular.toLowerCase()} no longer exists.`
                    : (e as Error).message;
        } finally {
            loading.value = false;
        }
    }

    async function save(): Promise<boolean> {
        if (saving.value) {
            return false;
        }

        saving.value = true;
        clearErrors();

        try {
            const payload = options.toPayload(model.value);
            const wasNew = id.value === null;
            const { data } = wasNew
                ? await api.post<{ data: EntityRecordMap[K] }>(
                      def.endpoint,
                      payload,
                  )
                : await api.put<{ data: EntityRecordMap[K] }>(
                      `${def.endpoint}/${id.value}`,
                      payload,
                  );

            accept(data);
            id.value = data.id;
            options.panel.setTitle(def.title(data));

            if (wasNew) {
                options.panel.updateParameters({
                    ...options.params,
                    id: data.id,
                    preset: undefined,
                    focus: false,
                });
            }

            toast.add({
                severity: 'success',
                summary: wasNew
                    ? `${def.singular} created`
                    : `${def.singular} saved`,
                detail: def.ref(data),
                life: 2500,
            });
            emitRecordChange('saved', { entity: options.entity, id: data.id });

            return true;
        } catch (e) {
            if (e instanceof ApiError && e.isValidation) {
                for (const [field, messages] of Object.entries(e.errors)) {
                    errors[field] = messages[0];
                }

                const count = Object.keys(e.errors).length;
                toast.add({
                    severity: 'warn',
                    summary: 'Not saved',
                    detail: `Fix the ${count} highlighted ${count === 1 ? 'field' : 'fields'} and save again.`,
                    life: 4000,
                });
            } else {
                toast.add({
                    severity: 'error',
                    summary: 'Save failed',
                    detail: (e as Error).message,
                    life: 6000,
                });
            }

            return false;
        } finally {
            saving.value = false;
        }
    }

    function revert(): void {
        model.value = JSON.parse(snapshot.value) as M;
        clearErrors();
    }

    usePanelActions(panelId, {
        onSave: save,
        onNew: () => openNew(options.entity),
        onRefresh: () => {
            if (!dirty.value) {
                void load();
            }
        },
    });

    return {
        id,
        record,
        model,
        errors,
        loading,
        saving,
        loadError,
        dirty,
        isNew,
        load,
        save,
        revert,
        clearErrors,
    };
}

/** Normalised error lookup, e.g. err('customer_id'). */
export function fieldError(
    errors: Record<string, string>,
    field: string,
): string | null {
    return errors[field] ?? null;
}
