import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import {
    axisProps,
    MoneyTooltip,
    shortRM,
} from '@/components/charts/chart-tooltip';

type Row = { month: string; label: string; billed: number; collected: number };

/** Billed vs collected per month: two series, grouped bars on one axis. */
export function BilledCollectedChart({ data }: { data: Row[] }) {
    return (
        <div className="h-64 w-full">
            <ResponsiveContainer>
                <BarChart
                    data={data}
                    barGap={2}
                    barCategoryGap="28%"
                    margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                >
                    <CartesianGrid vertical={false} stroke="var(--viz-grid)" />
                    <XAxis dataKey="label" {...axisProps} />
                    <YAxis {...axisProps} width={56} tickFormatter={shortRM} />
                    <Tooltip
                        content={<MoneyTooltip />}
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
                    <Bar
                        dataKey="billed"
                        name="Billed"
                        fill="var(--viz-billed)"
                        radius={[4, 4, 0, 0]}
                        maxBarSize={28}
                    />
                    <Bar
                        dataKey="collected"
                        name="Collected"
                        fill="var(--viz-collected)"
                        radius={[4, 4, 0, 0]}
                        maxBarSize={28}
                    />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
