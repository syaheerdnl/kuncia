import { useState } from 'react';
import {
    Bar,
    CartesianGrid,
    ComposedChart,
    Legend,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { axisProps, shortRM } from '@/components/charts/chart-tooltip';
import type { TimelineRow } from '@/components/properties/types';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';

/** What was billed, in stacking order. Colors are the validated viz slots. */
const GROUPS = [
    { key: 'rent', name: 'Rent', color: 'var(--viz-1)' },
    { key: 'utilities', name: 'Utilities', color: 'var(--viz-4)' },
    { key: 'other', name: 'Other charges', color: 'var(--viz-7)' },
    { key: 'late_fee', name: 'Late fees', color: 'var(--viz-8)' },
] as const;

const RECENT = 12;

function TimelineTooltip({
    active,
    payload,
}: {
    active?: boolean;
    payload?: { payload: TimelineRow }[];
}) {
    const row = payload?.[0]?.payload;

    if (!active || !row) {
        return null;
    }

    const status = [
        row.paid > 0 && `${row.paid} paid`,
        row.unpaid > 0 && `${row.unpaid} unpaid`,
        row.overdue > 0 && `${row.overdue} overdue`,
    ].filter(Boolean);

    return (
        <div className="min-w-48 rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
            <p className="mb-1 font-medium">{row.label}</p>
            {GROUPS.filter((g) => row[g.key] > 0).map((g) => (
                <div
                    key={g.key}
                    className="flex items-center justify-between gap-4"
                >
                    <span className="flex items-center gap-1.5 text-muted-foreground">
                        <span
                            className="size-2.5 rounded-sm"
                            style={{ background: g.color }}
                        />
                        {g.name}
                    </span>
                    <span className="font-medium tabular-nums">
                        {formatRM(row[g.key])}
                    </span>
                </div>
            ))}
            <div className="mt-1 flex justify-between border-t pt-1 font-medium">
                <span>Billed</span>
                <span className="tabular-nums">{formatRM(row.billed)}</span>
            </div>
            <div className="flex items-center justify-between gap-4">
                <span className="flex items-center gap-1.5 text-muted-foreground">
                    <span
                        className="size-2.5 rounded-full"
                        style={{ background: 'var(--viz-collected)' }}
                    />
                    Collected
                </span>
                <span className="font-medium tabular-nums">
                    {formatRM(row.collected)}
                </span>
            </div>
            {status.length > 0 && (
                <p className="mt-1 text-muted-foreground">
                    {status.join(' · ')}
                </p>
            )}
        </div>
    );
}

/**
 * Money history of one property: stacked bars show what was billed each month
 * by type, and the line shows what actually came in that month.
 */
export function PropertyTimelineChart({ rows }: { rows: TimelineRow[] }) {
    const [showAll, setShowAll] = useState(false);

    if (rows.length === 0) {
        return (
            <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                No invoices yet. The payment timeline appears once this property
                has invoices.
            </div>
        );
    }

    const canToggle = rows.length > RECENT;
    const data = showAll ? rows : rows.slice(-RECENT);

    const sum = (key: 'billed' | 'collected' | 'late_fee') =>
        data.reduce((total, r) => total + r[key], 0);

    const stats = [
        { label: 'Billed', value: sum('billed') },
        { label: 'Collected', value: sum('collected') },
        { label: 'Late fees', value: sum('late_fee') },
    ];

    return (
        <div className="rounded-xl border">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                <div>
                    <h2 className="font-semibold">Payment timeline</h2>
                    <p className="text-xs text-muted-foreground">
                        Billed each month by type, and what came in
                    </p>
                </div>
                {canToggle && (
                    <div className="flex gap-1">
                        <Button
                            size="sm"
                            variant={showAll ? 'outline' : 'secondary'}
                            onClick={() => setShowAll(false)}
                        >
                            Last {RECENT} months
                        </Button>
                        <Button
                            size="sm"
                            variant={showAll ? 'secondary' : 'outline'}
                            onClick={() => setShowAll(true)}
                        >
                            All
                        </Button>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-3 gap-2 p-4 pb-0 sm:gap-4">
                {stats.map((s) => (
                    <div key={s.label} className="min-w-0">
                        <p className="text-xs text-muted-foreground">
                            {s.label}
                        </p>
                        <p className="text-sm font-semibold tabular-nums sm:text-lg">
                            {formatRM(s.value)}
                        </p>
                    </div>
                ))}
            </div>

            <div
                className="h-72 w-full p-4"
                role="img"
                aria-label={`Payment timeline, ${data.length} months, from ${data[0].label} to ${data[data.length - 1].label}`}
            >
                <ResponsiveContainer>
                    <ComposedChart
                        data={data}
                        margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                    >
                        <CartesianGrid
                            vertical={false}
                            stroke="var(--viz-grid)"
                        />
                        <XAxis dataKey="label" {...axisProps} minTickGap={16} />
                        <YAxis
                            {...axisProps}
                            width={56}
                            tickFormatter={shortRM}
                        />
                        <Tooltip
                            content={<TimelineTooltip />}
                            cursor={{ fill: 'var(--muted)', opacity: 0.5 }}
                        />
                        <Legend
                            iconType="square"
                            iconSize={10}
                            wrapperStyle={{
                                fontSize: 12,
                                color: 'var(--muted-foreground)',
                            }}
                        />
                        {GROUPS.map((g) => (
                            <Bar
                                key={g.key}
                                dataKey={g.key}
                                name={g.name}
                                stackId="billed"
                                fill={g.color}
                                maxBarSize={36}
                            />
                        ))}
                        <Line
                            dataKey="collected"
                            name="Collected"
                            type="linear"
                            stroke="var(--viz-collected)"
                            strokeWidth={2}
                            dot={{ r: 3, fill: 'var(--viz-collected)' }}
                            activeDot={{ r: 5 }}
                        />
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
