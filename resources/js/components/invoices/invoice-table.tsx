import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import type { InvoiceRow } from '@/components/invoices/types';
import { StatusBadge } from '@/components/status-badge';
import { formatRM } from '@/lib/format';

type Props = {
    invoices: InvoiceRow[];
    href: (id: number) => NonNullable<InertiaLinkProps['href']>;
    showTenant?: boolean;
};

export function InvoiceTable({ invoices, href, showTenant = true }: Props) {
    if (invoices.length === 0) {
        return (
            <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-12 text-sm text-muted-foreground">
                <FileText className="size-8" /> No invoices found.
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                    <tr className="border-b">
                        <th className="px-4 py-2 font-medium">Invoice</th>
                        {showTenant && (
                            <th className="px-4 py-2 font-medium">Tenant</th>
                        )}
                        <th className="px-4 py-2 font-medium">Unit</th>
                        <th className="px-4 py-2 font-medium">Due</th>
                        <th className="px-4 py-2 text-right font-medium">
                            Total
                        </th>
                        <th className="px-4 py-2 text-right font-medium">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {invoices.map((i) => (
                        <tr
                            key={i.id}
                            className="border-b last:border-0 hover:bg-muted/50"
                        >
                            <td className="px-4 py-3">
                                <Link
                                    href={href(i.id)}
                                    className="font-medium hover:underline"
                                >
                                    {i.invoice_no}
                                </Link>
                                <div className="text-xs text-muted-foreground">
                                    {i.period}
                                </div>
                            </td>
                            {showTenant && (
                                <td className="px-4 py-3">{i.tenant}</td>
                            )}
                            <td className="px-4 py-3 text-muted-foreground">
                                {i.unit}
                            </td>
                            <td className="px-4 py-3">{i.due_date}</td>
                            <td className="px-4 py-3 text-right">
                                {formatRM(i.total)}
                            </td>
                            <td className="px-4 py-3 text-right">
                                <StatusBadge status={i.status} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
