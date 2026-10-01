/** Grid-backed modules. Headends, gateways and meters are editable; readings and events are read-only. */
export type EntityKey =
    | 'headends'
    | 'gateways'
    | 'meters'
    | 'readings'
    | 'events';

export type EditableEntity = 'headends' | 'gateways' | 'meters';

export type DeviceStatus = 'online' | 'degraded' | 'offline';

export type MeterStatus = 'active' | 'no_comms' | 'fault' | 'decommissioned';

export type MeterType =
    | 'single_phase'
    | 'three_phase'
    | 'ct'
    | 'ct_vt'
    | 'din_rail';

export type Severity = 'info' | 'warning' | 'critical';

export interface Meter {
    id: number;
    gateway_id: number;
    gateway_serial: string | null;
    headend_id: number | null;
    headend_code: string | null;
    serial: string;
    name: string;
    account: string | null;
    type: MeterType;
    type_label: string;
    phases: number;
    manufacturer: string;
    model: string;
    accuracy_class: string;
    ct_ratio: string | null;
    vt_ratio: string | null;
    tariff: string | null;
    has_generation: boolean;
    status: MeterStatus;
    installed_at: string | null;
    last_reading_at: string | null;
    kwh_30d: number | null;
    max_kva_30d: number | null;
    kwh_today: number | null;
    kw_now: number | null;
    updated_at: string | null;
}

export interface Gateway {
    id: number;
    headend_id: number;
    headend_code: string | null;
    headend_name: string | null;
    serial: string;
    name: string;
    comms: string;
    firmware: string | null;
    signal_dbm: number | null;
    status: DeviceStatus;
    last_seen_at: string | null;
    meters_count: number | null;
    meters?: Meter[];
    updated_at: string | null;
}

export interface Headend {
    id: number;
    code: string;
    name: string;
    site: string | null;
    city: string | null;
    protocol: string;
    ip_address: string | null;
    poll_interval_min: number;
    status: DeviceStatus;
    last_poll_at: string | null;
    gateways_count: number | null;
    gateways_online: number | null;
    meters_count: number | null;
    meters_reporting: number | null;
    gateways?: Gateway[];
    updated_at: string | null;
}

export interface Reading {
    id: number;
    meter_id: number;
    meter_serial: string | null;
    meter_name: string | null;
    gateway_serial: string | null;
    headend_code: string | null;
    read_at: string;
    kwh_import: number;
    kwh_export: number;
    kvarh_import: number;
    kvarh_export: number;
    kva_demand: number;
    kw: number;
    power_factor: number | null;
    voltage_l1: number | null;
    voltage_l2: number | null;
    voltage_l3: number | null;
    current_l1: number | null;
    current_l2: number | null;
    current_l3: number | null;
    quality: 'actual' | 'estimated';
}

export interface MeterEvent {
    id: number;
    meter_id: number;
    meter_serial: string | null;
    meter_name: string | null;
    headend_code: string | null;
    occurred_at: string;
    type: string;
    severity: Severity;
    message: string;
    acknowledged: boolean;
}

export interface EntityRecordMap {
    headends: Headend;
    gateways: Gateway;
    meters: Meter;
    readings: Reading;
    events: MeterEvent;
}

export type AnyRecord = EntityRecordMap[EntityKey];

export interface GridMeta {
    total: number;
    start: number;
    end: number;
    lastRow: number | null;
}

export interface GridResponse<T> {
    data: T[];
    meta: GridMeta;
}

export interface Lookups {
    headends: { id: number; code: string; name: string }[];
    gateways: {
        id: number;
        serial: string;
        name: string;
        headend_id: number;
        headend_code: string;
    }[];
    meterTypes: { value: MeterType; label: string }[];
    meterStatuses: MeterStatus[];
    deviceStatuses: DeviceStatus[];
    severities: Severity[];
    manufacturers: string[];
    tariffs: string[];
    comms: string[];
}

export interface NetworkMeter {
    id: number;
    serial: string;
    name: string;
    type: MeterType;
    status: MeterStatus;
}

export interface NetworkGateway {
    id: number;
    serial: string;
    name: string;
    status: DeviceStatus;
    meters: NetworkMeter[];
}

export interface NetworkHeadend {
    id: number;
    code: string;
    name: string;
    city: string;
    status: DeviceStatus;
    gateways: NetworkGateway[];
}

export interface SearchResult {
    type: 'headend' | 'gateway' | 'meter';
    id: number;
    title: string;
    subtitle: string;
}

export interface Kpi {
    value: number | null;
    previous: number | null;
    delta: number | null;
    spark: number[];
}

export interface DashboardData {
    range: {
        from: string;
        to: string;
        previousFrom: string;
        previousTo: string;
        days: number;
        granularity: 'interval' | 'hour' | 'day';
    };
    kpis: {
        energy: Kpi & { export: number };
        peakDemand: Kpi & { at: string | null };
        completeness: Kpi & { missing: number; estimated: number };
        alarms: Kpi & { open: number; critical: number };
    };
    loadProfile: { buckets: string[]; kw: number[]; previousKw: number[] };
    byType: { type: MeterType; label: string; kwh: number; meters: number }[];
    topMeters: {
        id: number;
        serial: string;
        name: string;
        kwh: number;
        max_kva: number;
    }[];
    heatmap: [number, number, number][];
    network: {
        id: number;
        code: string;
        name: string;
        city: string;
        status: DeviceStatus;
        gateways: number;
        gatewaysOnline: number;
        meters: number;
        metersReporting: number;
        kwh: number;
    }[];
    recentEvents: MeterEvent[];
    generatedAt: string;
}

export interface MeterProfile {
    range: {
        from: string;
        to: string;
        days: number;
        granularity: 'interval' | 'hour' | 'day';
    };
    series: {
        buckets: string[];
        kw: number[];
        kwExport: number[];
        kva: number[];
        pf: (number | null)[];
        v1: (number | null)[];
        v2: (number | null)[];
        v3: (number | null)[];
    };
    daily: {
        day: string;
        kwh_import: number;
        kwh_export: number;
        max_kva: number;
        intervals: number;
    }[];
    summary: {
        kwhImport: number;
        kwhExport: number;
        kvarh: number;
        maxKva: number;
        maxKvaAt: string | null;
        avgPf: number | null;
        loadFactor: number | null;
        vMin: number | null;
        vMax: number | null;
        intervals: number;
        expected: number;
        completeness: number;
        estimated: number;
    };
}

export type ReportColumnType =
    | 'text'
    | 'number'
    | 'number3'
    | 'decimal'
    | 'percent'
    | 'kwh'
    | 'kva';

export interface ReportData {
    type: string;
    title: string;
    from: string;
    to: string;
    columns: { field: string; header: string; type: ReportColumnType }[];
    rows: Record<string, string | number>[];
    chart: {
        kind: 'line' | 'bar' | 'pie';
        category: string;
        series: { field: string; name: string }[];
        limit?: number;
        unit?: string;
    };
    summary: { label: string; value: number; type: ReportColumnType }[];
}
