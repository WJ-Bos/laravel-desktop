import type { ColDef } from 'ag-grid-community';
import { col, dateTime } from '@/lib/agGrid';
import { fmt } from '@/lib/format';
import type { EntityKey, Lookups } from '@/types/erp';

/**
 * Column layouts and toolbar filters for each module grid. Every sortable
 * or filterable column id here has a matching GridColumn on the server.
 */

export interface ToolbarFilter {
    field: string;
    label: string;
    options: (lookups: Lookups) => { label: string; value: string | number }[];
}

export interface GridConfig {
    columns: ColDef[];
    searchPlaceholder: string;
    toolbarFilter?: ToolbarFilter;
}

const labelled = (values: string[]) =>
    values.map((v) => ({ label: fmt.titleCase(fmt.status(v)), value: v }));

const signal = (dbm: number) => {
    const bars = dbm >= -65 ? 4 : dbm >= -75 ? 3 : dbm >= -85 ? 2 : 1;

    return `${'▮'.repeat(bars)}${'▯'.repeat(4 - bars)} ${dbm} dBm`;
};

export const gridConfigs: Record<EntityKey, GridConfig> = {
    headends: {
        searchPlaceholder: 'Code, name, site, city, IP…',
        toolbarFilter: {
            field: 'status',
            label: 'Status',
            options: (l) => labelled(l.deviceStatuses),
        },
        columns: [
            col.text('code', 'Code', {
                width: 90,
                pinned: 'left',
                cellClass: 'erp-code',
            }),
            col.text('name', 'Headend', {
                flex: 1,
                minWidth: 180,
                sort: 'asc',
            }),
            col.text('site', 'Site', { width: 160 }),
            col.text('city', 'City', { width: 130 }),
            col.status('status', 'Status'),
            col.text('protocol', 'Protocol', { width: 120 }),
            col.text('ip_address', 'IP address', {
                width: 120,
                cellClass: 'erp-code',
            }),
            col.number('poll_interval_min', 'Poll (min)', { width: 95 }),
            col.number('gateways_count', 'Gateways', { width: 95 }),
            col.number('meters_count', 'Meters', { width: 85 }),
            col.number('meters_reporting', 'Reporting', {
                width: 100,
                cellClassRules: {
                    'erp-num-alert': (p) =>
                        (p.data?.meters_reporting ?? 0) <
                        (p.data?.meters_count ?? 0),
                },
            }),
            col.date('last_poll_at', 'Last poll', {
                width: 135,
                valueFormatter: dateTime,
            }),
        ],
    },
    gateways: {
        searchPlaceholder: 'Serial, name, headend…',
        toolbarFilter: {
            field: 'status',
            label: 'Status',
            options: (l) => labelled(l.deviceStatuses),
        },
        columns: [
            col.text('serial', 'Serial', {
                width: 110,
                pinned: 'left',
                cellClass: 'erp-code',
                sort: 'asc',
            }),
            col.text('name', 'Name', { flex: 1, minWidth: 180 }),
            col.text('headend_code', 'Headend', {
                width: 95,
                cellClass: 'erp-code',
            }),
            col.text('headend_name', 'Headend name', { width: 170 }),
            col.status('status', 'Status'),
            col.text('comms', 'Comms', { width: 110 }),
            col.text('firmware', 'Firmware', { width: 95 }),
            col.number('signal_dbm', 'Signal', {
                width: 130,
                valueFormatter: (p) =>
                    typeof p.value === 'number' ? signal(p.value) : '',
                cellClassRules: {
                    'erp-num-alert': (p) =>
                        typeof p.value === 'number' && p.value < -85,
                },
            }),
            col.number('meters_count', 'Meters', { width: 85 }),
            col.date('last_seen_at', 'Last seen', {
                width: 135,
                valueFormatter: dateTime,
            }),
        ],
    },
    meters: {
        searchPlaceholder: 'Serial, name, account, model, gateway…',
        toolbarFilter: {
            field: 'type',
            label: 'Meter type',
            options: (l) =>
                l.meterTypes.map((t) => ({ label: t.label, value: t.value })),
        },
        columns: [
            col.text('serial', 'Serial', {
                width: 115,
                pinned: 'left',
                cellClass: 'erp-code',
                sort: 'asc',
            }),
            col.text('name', 'Name', { flex: 1, minWidth: 200 }),
            col.text('type', 'Type', {
                width: 110,
                filter: false,
                valueFormatter: (p) =>
                    (p.data?.type_label as string | undefined) ?? '',
            }),
            col.status('status', 'Status'),
            col.text('manufacturer', 'Make', { width: 105 }),
            col.text('model', 'Model', { width: 110 }),
            col.text('accuracy_class', 'Class', { width: 70 }),
            col.text('ct_ratio', 'CT', { width: 80 }),
            col.text('vt_ratio', 'VT', { width: 100, hide: true }),
            col.text('tariff', 'Tariff', { width: 120 }),
            col.bool('has_generation', 'PV', { width: 60 }),
            col.text('gateway_serial', 'Gateway', {
                width: 100,
                cellClass: 'erp-code',
            }),
            col.text('headend_code', 'Headend', {
                width: 90,
                cellClass: 'erp-code',
            }),
            col.text('account', 'Account', { width: 105, hide: true }),
            col.decimal('kwh_30d', 'kWh 30 d', 1, { width: 110 }),
            col.date('last_reading_at', 'Last reading', {
                width: 135,
                valueFormatter: dateTime,
                cellClassRules: {
                    'erp-num-alert': (p) =>
                        typeof p.value === 'string' &&
                        Date.now() - new Date(p.value).getTime() > 2 * 3600_000,
                },
            }),
            col.date('installed_at', 'Installed', { hide: true }),
        ],
    },
    readings: {
        searchPlaceholder: 'Meter serial or name, gateway, headend…',
        toolbarFilter: {
            field: 'meter_type',
            label: 'Meter type',
            options: (l) =>
                l.meterTypes.map((t) => ({ label: t.label, value: t.value })),
        },
        columns: [
            col.date('read_at', 'Interval end', {
                width: 140,
                valueFormatter: dateTime,
                pinned: 'left',
                sort: 'desc',
            }),
            col.text('meter_serial', 'Meter', {
                width: 115,
                cellClass: 'erp-code',
            }),
            col.text('meter_name', 'Meter name', { width: 220 }),
            col.text('gateway_serial', 'Gateway', {
                width: 100,
                cellClass: 'erp-code',
                hide: true,
            }),
            col.text('headend_code', 'Headend', {
                width: 85,
                cellClass: 'erp-code',
            }),
            col.decimal('kwh_import', 'kWh imp', 3),
            col.decimal('kwh_export', 'kWh exp', 3),
            col.decimal('kvarh_import', 'kvarh', 3),
            col.decimal('kw', 'Avg kW', 2),
            col.decimal('kva_demand', 'kVA', 2),
            col.decimal('power_factor', 'PF', 3, { width: 80 }),
            col.decimal('voltage_l1', 'V L1', 1, { width: 85 }),
            col.decimal('voltage_l2', 'V L2', 1, { width: 85 }),
            col.decimal('voltage_l3', 'V L3', 1, { width: 85 }),
            col.decimal('current_l1', 'A L1', 2, { width: 85 }),
            col.decimal('current_l2', 'A L2', 2, { width: 85, hide: true }),
            col.decimal('current_l3', 'A L3', 2, { width: 85, hide: true }),
            col.status('quality', 'Quality', { width: 95 }),
        ],
    },
    events: {
        searchPlaceholder: 'Meter, event type, message, headend…',
        toolbarFilter: {
            field: 'severity',
            label: 'Severity',
            options: (l) => labelled(l.severities),
        },
        columns: [
            col.date('occurred_at', 'Occurred', {
                width: 140,
                valueFormatter: dateTime,
                pinned: 'left',
                sort: 'desc',
            }),
            col.status('severity', 'Severity', { width: 95 }),
            col.text('type', 'Event', {
                width: 140,
                valueFormatter: (p) =>
                    fmt.titleCase(fmt.status(p.value as string)),
            }),
            col.text('message', 'Message', { flex: 1, minWidth: 220 }),
            col.text('meter_serial', 'Meter', {
                width: 115,
                cellClass: 'erp-code',
            }),
            col.text('meter_name', 'Meter name', { width: 220 }),
            col.text('headend_code', 'Headend', {
                width: 90,
                cellClass: 'erp-code',
            }),
            col.bool('acknowledged', 'Ack', { width: 65 }),
        ],
    },
};
