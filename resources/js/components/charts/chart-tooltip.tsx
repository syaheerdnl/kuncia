import { formatRM } from '@/lib/format';

type Entry = {
    name?: string | number;
    value?: number | string;
    color?: string;
    dataKey?: string | number;
};

/** Tooltip in text ink; the colored swatch carries identity (never colored text). */
export function MoneyTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: Entry[];
    label?: string | number;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    const total =
        payload.length > 2
            ? payload.reduce((s, p) => s + Number(p.value ?? 0), 0)
            : null;

    return (
        <div className="rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
            <p className="mb-1 font-medium">{label}</p>
            {payload.map((p) => (
                <div
                    key={String(p.dataKey)}
                    className="flex items-center justify-between gap-4"
                >
                    <span className="flex items-center gap-1.5 text-muted-foreground">
                        <span
                            className="size-2.5 rounded-sm"
                            style={{ background: p.color }}
                        />
                        {p.name}
                    </span>
                    <span className="font-medium tabular-nums">
                        {formatRM(p.value)}
                    </span>
                </div>
            ))}
            {total !== null && (
                <div className="mt-1 flex justify-between border-t pt-1 font-medium">
                    <span>Total</span>
                    <span className="tabular-nums">{formatRM(total)}</span>
                </div>
            )}
        </div>
    );
}

export const axisProps = {
    tickLine: false,
    axisLine: false,
    tick: { fill: 'var(--viz-axis)', fontSize: 12 },
} as const;

export const shortRM = (v: number) =>
    v >= 1000 ? `RM${(v / 1000).toFixed(1)}k` : `RM${v}`;
