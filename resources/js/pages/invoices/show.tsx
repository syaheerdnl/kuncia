import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Ban, FileDown } from 'lucide-react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import MyInvoiceController from '@/actions/App/Http/Controllers/Tenant/MyInvoiceController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { RecordPaymentDialog } from '@/components/invoices/record-payment-dialog';
import type { InvoiceDetail } from '@/components/invoices/types';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';

type Props = { invoice: InvoiceDetail; canManage: boolean };

export default function InvoiceShow({ invoice, canManage }: Props) {
    const open = invoice.status === 'unpaid' || invoice.status === 'overdue';
    const back = canManage ? InvoiceController.index() : MyInvoiceController.index();

    return (
        <>
            <Head title={invoice.invoice_no} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Link href={back} className="flex w-fit items-center gap-1 text-sm text-muted-foreground hover:underline">
                    <ArrowLeft className="size-4" /> Back to invoices
                </Link>

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">{invoice.invoice_no}</h1>
                            <StatusBadge status={invoice.status} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {invoice.period} · {invoice.tenant} · {invoice.unit}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href={InvoiceController.pdf.url(invoice.id)}>
                                <FileDown /> PDF
                            </a>
                        </Button>
                        {canManage && open && (
                            <>
                                <RecordPaymentDialog invoiceId={invoice.id} outstanding={invoice.outstanding} />
                                {invoice.payments.length === 0 && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button variant="outline">
                                                <Ban /> Void
                                            </Button>
                                        }
                                        title={`Void ${invoice.invoice_no}?`}
                                        description="Use this for invoices issued by mistake. It cannot be undone."
                                        confirmLabel="Void invoice"
                                        onConfirm={() => router.post(InvoiceController.void.url(invoice.id), {}, { preserveScroll: true })}
                                    />
                                )}
                            </>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    {[
                        { label: 'Issued', value: invoice.issue_date },
                        { label: 'Due', value: invoice.due_date },
                        { label: 'Total', value: formatRM(invoice.total) },
                        { label: 'Balance due', value: formatRM(invoice.outstanding) },
                    ].map((s) => (
                        <div key={s.label} className="rounded-xl border p-4">
                            <p className="text-xs text-muted-foreground">{s.label}</p>
                            <p className="mt-1 text-lg font-semibold">{s.value}</p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border">
                        <h2 className="border-b p-4 font-semibold">Items</h2>
                        <table className="w-full text-sm">
                            <tbody>
                                {invoice.items.map((i) => (
                                    <tr key={i.id} className="border-b">
                                        <td className="px-4 py-2">{i.description}</td>
                                        <td className="px-4 py-2 text-right">{formatRM(i.amount)}</td>
                                    </tr>
                                ))}
                                <tr className="font-semibold">
                                    <td className="px-4 py-2 text-right">Total</td>
                                    <td className="px-4 py-2 text-right">{formatRM(invoice.total)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-xl border">
                        <h2 className="border-b p-4 font-semibold">Payments</h2>
                        {invoice.payments.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">No payments yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <tbody>
                                    {invoice.payments.map((p) => (
                                        <tr key={p.id} className="border-b last:border-0">
                                            <td className="px-4 py-2">
                                                <div className="font-medium">{p.method}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {p.paid_at} {p.reference && `· ${p.reference}`}
                                                </div>
                                            </td>
                                            <td className="px-4 py-2 text-right">{formatRM(p.amount)}</td>
                                            <td className="px-4 py-2 text-right text-xs capitalize">{p.status}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
