import { Head, Link, router } from '@inertiajs/react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import Heading from '@/components/heading';
import { InvoiceTable } from '@/components/invoices/invoice-table';
import type { InvoiceRow, Paginated } from '@/components/invoices/types';
import type { Option } from '@/components/properties/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRM } from '@/lib/format';

type Filters = { status: string; month: string; property: string };

type Props = {
    invoices: Paginated<InvoiceRow>;
    summary: { outstanding: string; overdue_count: number; collected: string };
    filters: Filters;
    properties: { id: number; name: string }[];
    statuses: Option[];
};

const ALL = 'all';

export default function InvoicesIndex({ invoices, summary, filters, properties, statuses }: Props) {
    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(Object.entries(next).filter(([, v]) => v && v !== ALL));
        router.get(InvoiceController.index.url(), query, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title="Invoices" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading title="Invoices" description="Generated automatically on the 1st of every month" />

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border p-4">
                        <p className="text-xs text-muted-foreground">Outstanding</p>
                        <p className="mt-1 text-lg font-semibold text-amber-600 dark:text-amber-400">{formatRM(summary.outstanding)}</p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-xs text-muted-foreground">Overdue invoices</p>
                        <p className="mt-1 text-lg font-semibold text-red-600 dark:text-red-400">{summary.overdue_count}</p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <p className="text-xs text-muted-foreground">Collected</p>
                        <p className="mt-1 text-lg font-semibold text-emerald-600 dark:text-emerald-400">{formatRM(summary.collected)}</p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Select value={filters.status || ALL} onValueChange={(v) => apply({ status: v })}>
                        <SelectTrigger className="w-40">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All statuses</SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={filters.property || ALL} onValueChange={(v) => apply({ property: v })}>
                        <SelectTrigger className="w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All properties</SelectItem>
                            {properties.map((p) => (
                                <SelectItem key={p.id} value={String(p.id)}>
                                    {p.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input type="month" value={filters.month} onChange={(e) => apply({ month: e.target.value })} className="w-44" />

                    {(filters.status || filters.month || filters.property) && (
                        <Button variant="ghost" onClick={() => router.get(InvoiceController.index.url())}>
                            Clear
                        </Button>
                    )}
                </div>

                <InvoiceTable invoices={invoices.data} href={(id) => InvoiceController.show(id)} />

                {invoices.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            {invoices.from}–{invoices.to} of {invoices.total}
                        </span>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" disabled={!invoices.prev_page_url} asChild={!!invoices.prev_page_url}>
                                {invoices.prev_page_url ? <Link href={invoices.prev_page_url} preserveScroll>Previous</Link> : <span>Previous</span>}
                            </Button>
                            <Button variant="outline" size="sm" disabled={!invoices.next_page_url} asChild={!!invoices.next_page_url}>
                                {invoices.next_page_url ? <Link href={invoices.next_page_url} preserveScroll>Next</Link> : <span>Next</span>}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Invoices', href: InvoiceController.index() }],
};
