const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const currencyWhole = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
});

const compact = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const integer = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });

const decimal = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

const oneDecimal = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
});

/** Energy with an automatic unit step (kWh → MWh → GWh). */
function energy(kwh: number): string {
    const abs = Math.abs(kwh);

    if (abs >= 1_000_000) {
        return `${oneDecimal.format(kwh / 1_000_000)} GWh`;
    }

    if (abs >= 10_000) {
        return `${oneDecimal.format(kwh / 1_000)} MWh`;
    }

    return `${oneDecimal.format(kwh)} kWh`;
}

/** Power/demand with an automatic unit step (kW → MW). */
function power(value: number, unit: 'kW' | 'kVA'): string {
    return Math.abs(value) >= 10_000
        ? `${oneDecimal.format(value / 1_000)} ${unit === 'kW' ? 'MW' : 'MVA'}`
        : `${oneDecimal.format(value)} ${unit}`;
}

export const fmt = {
    energy: (v: number | null | undefined) =>
        v === null || v === undefined ? '' : energy(v),
    power: (v: number | null | undefined, unit: 'kW' | 'kVA' = 'kW') =>
        v === null || v === undefined ? '' : power(v, unit),
    dec1: (v: number | null | undefined) =>
        v === null || v === undefined ? '' : oneDecimal.format(v),
    dec3: (v: number | null | undefined) =>
        v === null || v === undefined ? '' : v.toFixed(3),
    status: (v: string | null | undefined) => (v ? v.replace(/_/g, ' ') : ''),
    money: (v: number | null | undefined) =>
        v === null || v === undefined ? '' : currency.format(v),
    moneyWhole: (v: number | null | undefined) =>
        v === null || v === undefined ? '' : currencyWhole.format(v),
    moneyCompact: (v: number) => `$${compact.format(v)}`,
    int: (v: number | null | undefined) =>
        v === null || v === undefined ? '' : integer.format(v),
    num: (v: number | null | undefined) =>
        v === null || v === undefined ? '' : decimal.format(v),
    compact: (v: number) => compact.format(v),
    pct: (v: number | null | undefined, digits = 1) =>
        v === null || v === undefined ? '' : `${v.toFixed(digits)}%`,
    delta: (v: number | null) =>
        v === null ? 'n/a' : `${v > 0 ? '+' : ''}${v.toFixed(1)}%`,
    date: (v: string | null | undefined) => {
        if (!v) {
            return '';
        }

        const d = new Date(v.length === 10 ? `${v}T00:00:00` : v);

        return d.toLocaleDateString('en-CA');
    },
    dateTime: (v: string | null | undefined) => {
        if (!v) {
            return '';
        }

        const d = new Date(v);

        return `${d.toLocaleDateString('en-CA')} ${d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
    },
    time: (d: Date | null) =>
        d ? d.toLocaleTimeString('en-GB', { hour12: false }) : '—',
    titleCase: (v: string) => v.charAt(0).toUpperCase() + v.slice(1),
};

/** 'YYYY-MM-DD' for a local Date (no timezone shift). */
export function isoDate(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** 'YYYY-MM-DD HH:mm:ss' for a local Date, as Laravel expects. */
export function isoDateTime(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${isoDate(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

export function parseDate(v: string | null | undefined): Date | null {
    if (!v) {
        return null;
    }

    return new Date(v.length === 10 ? `${v}T00:00:00` : v);
}
