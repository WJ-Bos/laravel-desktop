import type { AnyRecord, EntityKey } from '@/types/erp';

/**
 * Registry of everything that can be opened in the workspace. Menus, the
 * navigator, the command palette and shortcuts all read from here.
 */

export type ModuleId = 'dashboard' | 'reports' | EntityKey;

export interface ModuleDef {
    id: ModuleId;
    title: string;
    icon: string;
    group: 'Overview' | 'Network' | 'Data' | 'Analysis';
    keywords: string;
}

export const modules: Record<ModuleId, ModuleDef> = {
    dashboard: {
        id: 'dashboard',
        title: 'Dashboard',
        icon: 'pi pi-gauge',
        group: 'Overview',
        keywords: 'kpi overview home network load',
    },
    headends: {
        id: 'headends',
        title: 'Headends',
        icon: 'pi pi-server',
        group: 'Network',
        keywords: 'head-end hes collector site',
    },
    gateways: {
        id: 'gateways',
        title: 'Gateways',
        icon: 'pi pi-sitemap',
        group: 'Network',
        keywords: 'concentrator dcu gateway plc rf',
    },
    meters: {
        id: 'meters',
        title: 'Meters',
        icon: 'pi pi-bolt',
        group: 'Network',
        keywords: 'meter device ct vt single three phase',
    },
    readings: {
        id: 'readings',
        title: 'Readings',
        icon: 'pi pi-chart-line',
        group: 'Data',
        keywords: 'raw interval half-hourly load profile kwh kva voltage',
    },
    events: {
        id: 'events',
        title: 'Events',
        icon: 'pi pi-bell',
        group: 'Data',
        keywords: 'alarms tamper power failure sag comms',
    },
    reports: {
        id: 'reports',
        title: 'Reports',
        icon: 'pi pi-chart-bar',
        group: 'Analysis',
        keywords: 'consumption demand completeness power quality',
    },
};

export interface EntityDef<K extends EntityKey = EntityKey> {
    key: K;
    singular: string;
    plural: string;
    icon: string;
    endpoint: string;
    /** Whether rows can be created, edited, duplicated and deleted. */
    editable: boolean;
    title: (record: AnyRecord) => string;
    ref: (record: AnyRecord) => string;
}

type R = Record<string, unknown>;

export const entities: { [K in EntityKey]: EntityDef<K> } = {
    headends: {
        key: 'headends',
        singular: 'Headend',
        plural: 'Headends',
        icon: modules.headends.icon,
        endpoint: '/api/headends',
        editable: true,
        title: (r) => `${(r as unknown as R).code} ${(r as unknown as R).name}`,
        ref: (r) => String((r as unknown as R).code),
    },
    gateways: {
        key: 'gateways',
        singular: 'Gateway',
        plural: 'Gateways',
        icon: modules.gateways.icon,
        endpoint: '/api/gateways',
        editable: true,
        title: (r) => `Gateway ${(r as unknown as R).serial}`,
        ref: (r) => String((r as unknown as R).serial),
    },
    meters: {
        key: 'meters',
        singular: 'Meter',
        plural: 'Meters',
        icon: modules.meters.icon,
        endpoint: '/api/meters',
        editable: true,
        title: (r) => `Meter ${(r as unknown as R).serial}`,
        ref: (r) =>
            `${(r as unknown as R).serial} (${(r as unknown as R).name})`,
    },
    readings: {
        key: 'readings',
        singular: 'Reading',
        plural: 'Readings',
        icon: modules.readings.icon,
        endpoint: '/api/readings',
        editable: false,
        title: (r) =>
            `Reading ${(r as unknown as R).meter_serial} ${(r as unknown as R).read_at}`,
        ref: (r) =>
            `${(r as unknown as R).meter_serial} @ ${(r as unknown as R).read_at}`,
    },
    events: {
        key: 'events',
        singular: 'Event',
        plural: 'Events',
        icon: modules.events.icon,
        endpoint: '/api/events',
        editable: false,
        title: (r) => `Event ${(r as unknown as R).type}`,
        ref: (r) =>
            `${(r as unknown as R).type} on ${(r as unknown as R).meter_serial}`,
    },
};

export const searchTypeToEntity: Record<string, EntityKey> = {
    headend: 'headends',
    gateway: 'gateways',
    meter: 'meters',
};

export const meterTypeIcons: Record<string, string> = {
    single_phase: 'pi pi-home',
    three_phase: 'pi pi-building',
    ct: 'pi pi-sliders-h',
    ct_vt: 'pi pi-bolt',
    din_rail: 'pi pi-th-large',
};

/** Human labels for status values used in tags. */
export const statusLabels: Record<string, string> = {
    no_comms: 'no comms',
};
