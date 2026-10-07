import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import ReportController from '@/actions/App/Http/Controllers/ReportController';
import { IncomeChart } from '@/components/charts/income-chart';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRM } from '@/lib/format';

type Props = {
    year: number;
    years: number[];
    report: { properties: string[]; rows: Record<string, number | string>[]; totals: Record<string, number> };
};

export default function ReportsIndex({ year, years, report }: Props) {
    const grand = Object.values(report.totals).reduce((s, v) => s + v, 0);
    const rowTotal = (r: Record<string, number | string>) => report.properties.reduce((s, p) => s + Number(r[p] ?? 0), 0);

    return (
        <>
            <Head title="Reports" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title="Income report" description={`Rent collected in ${year}: ${formatRM(grand)}`} />
                    <div className="flex gap-2">
                        <Select value={String(year)} onValueChange={(v) => router.get(ReportController.index.url(), { year: v }, { preserveState: true })}>
                            <SelectTrigger className="w-28"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {years.map((y) => <SelectItem key={y} value={String(y)}>{y}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <Button variant="outline" asChild>
                            <a href={ReportController.export.url({ query: { year } })}><Download /> CSV</a>
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <h2 className="font-semibold">Collected per month, by property</h2>
                    <p className="mb-3 text-xs text-muted-foreground">By payment date · hover a bar for the breakdown</p>
                    {report.properties.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Add a property to see income.</p>
                    ) : (
                        <IncomeChart properties={report.properties} rows={report.rows} />
                    )}
                </div>

                {/* Table view: exact numbers + accessible alternative to the chart */}
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm tabular-nums">
                        <thead className="text-left text-xs text-muted-foreground">
                            <tr className="border-b">
                                <th className="px-4 py-2 font-medium">Month</th>
                                {report.properties.map((p) => <th key={p} className="px-4 py-2 text-right font-medium">{p}</th>)}
                                <th className="px-4 py-2 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.map((r) => (
                                <tr key={String(r.month)} className="border-b">
                                    <td className="px-4 py-2">{r.label}</td>
                                    {report.properties.map((p) => <td key={p} className="px-4 py-2 text-right">{formatRM(r[p])}</td>)}
                                    <td className="px-4 py-2 text-right font-medium">{formatRM(rowTotal(r))}</td>
                                </tr>
                            ))}
                            <tr className="font-semibold">
                                <td className="px-4 py-2">Total</td>
                                {report.properties.map((p) => <td key={p} className="px-4 py-2 text-right">{formatRM(report.totals[p])}</td>)}
                                <td className="px-4 py-2 text-right">{formatRM(grand)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: ReportController.index() }],
};
