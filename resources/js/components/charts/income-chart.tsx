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

type Row = Record<string, number | string>;

const SLOTS = 8; // validated categorical slots; never cycled

/** Monthly income stacked by property. More than 8 properties fold into "Other". */
export function IncomeChart({
    properties,
    rows,
}: {
    properties: string[];
    rows: Row[];
}) {
    const keep =
        properties.length > SLOTS ? properties.slice(0, SLOTS - 1) : properties;
    const folded = properties.slice(keep.length);
    const series = folded.length ? [...keep, 'Other'] : keep;

    const data = rows.map((r) => ({
        ...r,
        ...(folded.length
            ? { Other: folded.reduce((s, p) => s + Number(r[p] ?? 0), 0) }
            : {}),
    }));

    return (
        <div className="h-72 w-full">
            <ResponsiveContainer>
                <BarChart
                    data={data}
                    margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                    barCategoryGap="24%"
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
                    {series.map((name, i) => (
                        <Bar
                            key={name}
                            dataKey={name}
                            name={name}
                            stackId="income"
                            fill={`var(--viz-${i + 1})`}
                            // 2px surface gap between stacked segments
                            stroke="var(--background)"
                            strokeWidth={2}
                            radius={i === series.length - 1 ? [4, 4, 0, 0] : 0}
                            maxBarSize={36}
                        />
                    ))}
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
