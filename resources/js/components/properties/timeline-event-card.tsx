import { Link, router } from '@inertiajs/react';
import { ChevronDown, ExternalLink, FileDown, Trash2 } from 'lucide-react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import TenancyTransactionController from '@/actions/App/Http/Controllers/TenancyTransactionController';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { KINDS, formatDate } from '@/components/properties/timeline-kinds';
import type {
    InvoiceEvent,
    TimelineEvent,
} from '@/components/properties/types';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';
import { cn } from '@/lib/utils';

const opensInvoice = (event: TimelineEvent): event is InvoiceEvent =>
    'invoice' in event;

/** The two lines under the title: who, and what it was. */
function Lines({ event }: { event: TimelineEvent }) {
    let note: string | null = null;

    switch (event.type) {
        case 'payment':
            note = `${event.period} invoice · ${event.method}${event.reference ? ` · ${event.reference}` : ''}`;
            break;
        case 'late_fee':
        case 'utility_charge':
        case 'extra_charge':
            note = `${event.description} · ${event.period} invoice`;
            break;
        case 'deposit_received':
        case 'paid_to_tenant':
            note = event.note;
            break;
        case 'tenancy_start':
            note = `Rent ${formatRM(event.amount)}/month · Deposit ${formatRM(event.deposit)}`;
            break;
        case 'tenancy_end':
            note = `Deposit ${formatRM(event.deposit)} · ${event.deposit_status}`;
            break;
    }

    return (
        <>
            <span className="mt-2 block">
                {event.tenant} · {event.unit}
            </span>
            {note && (
                <span className="block text-muted-foreground">{note}</span>
            )}
        </>
    );
}

/** What opens inside a card: the invoice, the tenant, or the delete option. */
function Detail({ event }: { event: TimelineEvent }) {
    if (opensInvoice(event)) {
        const { invoice } = event;

        return (
            <div className="mt-3 space-y-3 border-t border-current/10 pt-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-medium">{invoice.invoice_no}</span>
                    <StatusBadge status={invoice.status} />
                </div>
                <p className="text-xs text-muted-foreground">
                    {invoice.period} · due {formatDate(invoice.due_date)}
                </p>

                <ul className="divide-y rounded-lg border bg-background/70">
                    {invoice.items.map((item, i) => (
                        <li
                            key={`${item.description}-${i}`}
                            className="flex justify-between gap-4 px-3 py-1.5"
                        >
                            <span>{item.description}</span>
                            <span className="tabular-nums">
                                {formatRM(item.amount)}
                            </span>
                        </li>
                    ))}
                    <li className="flex justify-between gap-4 px-3 py-1.5 font-semibold">
                        <span>Total</span>
                        <span className="tabular-nums">
                            {formatRM(invoice.total)}
                        </span>
                    </li>
                </ul>

                <div className="space-y-1">
                    <p className="text-xs font-medium text-muted-foreground">
                        Payments received
                    </p>
                    {invoice.payments.length === 0 && (
                        <p className="text-muted-foreground">
                            No payments yet.
                        </p>
                    )}
                    {invoice.payments.map((p, i) => (
                        <p
                            key={`${p.paid_at}-${i}`}
                            className="flex justify-between gap-4"
                        >
                            <span className="text-muted-foreground">
                                {formatDate(p.paid_at)} · {p.method}
                                {p.reference ? ` · ${p.reference}` : ''}
                            </span>
                            <span className="font-medium tabular-nums">
                                {formatRM(p.amount)}
                            </span>
                        </p>
                    ))}
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button size="sm" asChild>
                        <Link href={InvoiceController.show(invoice.id)}>
                            <ExternalLink /> Open full invoice
                        </Link>
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        {/* Plain link: a file download, not an Inertia visit */}
                        <a href={InvoiceController.pdf.url(invoice.id)}>
                            <FileDown /> Download PDF
                        </a>
                    </Button>
                </div>
            </div>
        );
    }

    if (event.type === 'tenancy_start' || event.type === 'tenancy_end') {
        return (
            <div className="mt-3 border-t border-current/10 pt-3">
                <Button size="sm" variant="outline" asChild>
                    <Link href={TenantController.show(event.tenant_id)}>
                        <ExternalLink /> Open tenant page
                    </Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="mt-3 border-t border-current/10 pt-3">
            <ConfirmDialog
                trigger={
                    <Button type="button" variant="outline" size="sm">
                        <Trash2 /> Delete entry
                    </Button>
                }
                title="Delete this entry?"
                description={`${KINDS[event.type].title} on ${formatDate(event.date)}. It disappears from the timeline. This cannot be undone.`}
                onConfirm={() =>
                    router.delete(
                        TenancyTransactionController.destroy.url(event.id),
                        { preserveScroll: true },
                    )
                }
            />
        </div>
    );
}

/** One soft tinted card. Click it to open the details inside it. */
export function EventCard({
    event,
    open,
    onToggle,
}: {
    event: TimelineEvent;
    open: boolean;
    onToggle: () => void;
}) {
    const kind = KINDS[event.type];
    const Icon = kind.icon;
    const hasAmount =
        event.type !== 'tenancy_start' && event.type !== 'tenancy_end';

    return (
        <div
            className={cn(
                'min-w-0 flex-1 basis-56 rounded-xl border p-3 text-sm transition-colors',
                kind.card,
            )}
        >
            <button
                type="button"
                aria-expanded={open}
                onClick={onToggle}
                className="block w-full rounded-md text-left outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
            >
                <span className="flex items-center gap-2">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-background">
                        <Icon className={cn('size-4', kind.text)} aria-hidden />
                    </span>
                    <span className={cn('flex-1 font-medium', kind.text)}>
                        {kind.title}
                    </span>
                    {hasAmount && (
                        <span className="font-semibold tabular-nums">
                            {formatRM(event.amount)}
                        </span>
                    )}
                    <ChevronDown
                        className={cn(
                            'size-4 shrink-0 text-muted-foreground transition-transform',
                            open && 'rotate-180',
                        )}
                        aria-hidden
                    />
                </span>
                <Lines event={event} />
            </button>

            {open && <Detail event={event} />}
        </div>
    );
}
