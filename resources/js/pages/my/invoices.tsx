import { Head } from '@inertiajs/react';
import MyInvoiceController from '@/actions/App/Http/Controllers/Tenant/MyInvoiceController';
import Heading from '@/components/heading';
import { InvoiceTable } from '@/components/invoices/invoice-table';
import type { InvoiceRow } from '@/components/invoices/types';
import { formatRM } from '@/lib/format';

export default function MyInvoices({ invoices }: { invoices: InvoiceRow[] }) {
    const due = invoices
        .filter((i) => i.status === 'unpaid' || i.status === 'overdue')
        .reduce((sum, i) => sum + Number(i.total), 0);

    return (
        <>
            <Head title="My invoices" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="My invoices"
                    description="Your monthly rent bills"
                />
                <div className="max-w-xs rounded-xl border p-4">
                    <p className="text-xs text-muted-foreground">Amount due</p>
                    <p
                        className={`mt-1 text-2xl font-semibold ${due > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600'}`}
                    >
                        {formatRM(due)}
                    </p>
                </div>
                <InvoiceTable
                    invoices={invoices}
                    href={(id) => MyInvoiceController.show(id)}
                    showTenant={false}
                />
            </div>
        </>
    );
}

MyInvoices.layout = {
    breadcrumbs: [{ title: 'My invoices', href: MyInvoiceController.index() }],
};
