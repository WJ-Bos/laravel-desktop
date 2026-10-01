import type { VueComponent } from 'dockview-vue';
import type { Component } from 'vue';

/** Root element of a component instance held in a template ref. */
export function elOf(instance: unknown): HTMLElement | null {
    return (instance as { $el?: HTMLElement } | null)?.$el ?? null;
}

/** Focus the first input inside a component (PrimeVue wraps inputs in spans). */
export function focusInside(instance: unknown, select = false): void {
    const root = elOf(instance);
    const input = root?.matches('input, textarea')
        ? (root as HTMLInputElement)
        : root?.querySelector<HTMLInputElement>('input, textarea');

    input?.focus();

    if (select) {
        input?.select();
    }
}

/**
 * dockview-vue types panel components as options-API components; `<script setup>`
 * components work at runtime but need widening for the type checker.
 */
export function dockComponent(component: Component): VueComponent {
    return component as unknown as VueComponent;
}
