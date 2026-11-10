import { Link } from '@inertiajs/react';
import {
    Banknote,
    ExternalLink,
    FileDown,
    HandCoins,
    LogIn,
    LogOut,
    PiggyBank,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import type {
    PaymentEvent,
    TimelineEvent,
} from '@/components/properties/types';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatRM } from '@/lib/format';
import { cn } from '@/lib/utils';

const PAGE = 10;

const KINDS: Record<
    TimelineEvent['type'],
    { title: string; color: string; icon: LucideIcon }
> = {
    payment: {
        title: 'Payment received',
        color: 'bg-teal-700',
        icon: Banknote,
    },
    deposit_received: {
        title: 'Deposit received',
        color: 'bg-violet-700',
        icon: PiggyBank,
    },
    paid_to_tenant: {
        title: 'Paid to tenant',
        color: 'bg-orange-700',
        icon: HandCoins,
    },
    tenancy_start: {
        title: 'Tenant moved in',
        color: 'bg-blue-700',
        icon: LogIn,
    },
    tenancy_end: {
        title: 'Tenant moved out',
        color: 'bg-red-700',
        icon: LogOut,
    },
};

const formatDate = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

/** The lines under a banner. */
function Details({ event }: { event: TimelineEvent }) {
    const who = `${event.tenant} · ${event.unit}`;

    if (event.type === 'payment') {
        return (
            <>
                <p className="text-lg font-semibold tabular-nums">
                    {formatRM(event.amount)}
                </p>
                <p>{who}</p>
                <p className="text-muted-foreground">
                    {event.period} invoice · {event.method}
                    {event.reference ? ` · ${event.reference}` : ''}
                </p>
                <p className="pt-0.5 text-xs font-medium text-teal-700 underline-offset-2 group-hover:underline dark:text-teal-400">
                    View invoice
                </p>
            </>
        );
    }

    if (event.type === 'deposit_received' || event.type === 'paid_to_tenant') {
        return (
            <>
                <p className="text-lg font-semibold tabular-nums">
                    {formatRM(event.amount)}
                </p>
                <p>{who}</p>
                {event.note && (
                    <p className="text-muted-foreground">{event.note}</p>
                )}
            </>
        );
    }

    return (
        <>
            <p>{who}</p>
            <p className="text-muted-foreground">
                {event.type === 'tenancy_start'
                    ? `Rent ${formatRM(event.amount)}/month · Deposit ${formatRM(event.deposit)}`
                    : `Deposit ${formatRM(event.deposit)} · ${event.deposit_status}`}
            </p>
        </>
    );
}

function Event({
    event,
    side,
    onOpenInvoice,
}: {
    event: TimelineEvent;
    side: 'left' | 'right';
    onOpenInvoice: (payment: PaymentEvent) => void;
}) {
    const kind = KINDS[event.type];
    const Icon = kind.icon;
    const left = side === 'left';

    const content = (
        <>
            {/* Arrow-shaped banner pointing at the line */}
            <div
                className={cn(
                    'flex items-center gap-2 py-2 pr-4 pl-7 text-sm font-bold tracking-wide text-white uppercase',
                    '[clip-path:polygon(0_50%,14px_0,100%_0,100%_100%,14px_100%)]',
                    kind.color,
                    left &&
                        'md:justify-end md:pr-7 md:pl-4 md:[clip-path:polygon(0_0,calc(100%-14px)_0,100%_50%,calc(100%-14px)_100%,0_100%)]',
                )}
            >
                <Icon className="size-4 shrink-0" aria-hidden />
                {kind.title}
            </div>
            <p className="text-xs font-medium text-muted-foreground">
                {formatDate(event.date)}
            </p>
            <Details event={event} />
        </>
    );

    return (
        <li className="relative pb-8 pl-12 last:pb-0 md:grid md:grid-cols-2 md:pl-0">
            {/* The dot on the line */}
            <span
                aria-hidden
                className={cn(
                    'absolute top-2.5 left-4 size-4 -translate-x-1/2 rounded-full ring-4 ring-background md:left-1/2',
                    kind.color,
                )}
            />

            <div
                className={cn(
                    'text-sm',
                    left
                        ? 'md:col-start-1 md:pr-12 md:text-right'
                        : 'md:col-start-2 md:pl-12',
                )}
            >
                {event.type === 'payment' ? (
                    <button
                        type="button"
                        onClick={() => onOpenInvoice(event)}
                        aria-haspopup="dialog"
                        aria-label={`View invoice ${event.invoice.invoice_no}, payment of ${formatRM(event.amount)} on ${formatDate(event.date)}`}
                        className={cn(
                            'group block w-full cursor-pointer space-y-1.5 rounded-md text-left outline-none',
                            'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                            left && 'md:text-right',
                        )}
                    >
                        {content}
                    </button>
                ) : (
                    <div className="space-y-1.5">{content}</div>
                )}
            </div>
        </li>
    );
}

/** Quick look at the invoice behind a payment, with a way to open it in full. */
function InvoiceDialog({
    payment,
    open,
    onOpenChange,
}: {
    payment: PaymentEvent | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const invoice = payment?.invoice;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                {payment && invoice && (
                    <>
                        <DialogHeader>
                            <DialogTitle className="flex flex-wrap items-center gap-2">
                                {invoice.invoice_no}
                                <StatusBadge status={invoice.status} />
                            </DialogTitle>
                            <DialogDescription>
                                {invoice.period} · {payment.tenant} ·{' '}
                                {payment.unit} · due{' '}
                                {formatDate(invoice.due_date)}
                            </DialogDescription>
                        </DialogHeader>

                        <ul className="divide-y rounded-lg border text-sm">
                            {invoice.items.map((item, i) => (
                                <li
                                    key={`${item.description}-${i}`}
                                    className="flex justify-between gap-4 px-3 py-2"
                                >
                                    <span>{item.description}</span>
                                    <span className="tabular-nums">
                                        {formatRM(item.amount)}
                                    </span>
                                </li>
                            ))}
                            <li className="flex justify-between gap-4 px-3 py-2 font-semibold">
                                <span>Total</span>
                                <span className="tabular-nums">
                                    {formatRM(invoice.total)}
                                </span>
                            </li>
                        </ul>

                        <div className="space-y-1 text-sm">
                            <p className="text-xs font-medium text-muted-foreground">
                                Payments received
                            </p>
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

                        <div className="flex flex-wrap gap-2 pt-1">
                            <Button asChild>
                                <Link href={InvoiceController.show(invoice.id)}>
                                    <ExternalLink /> Open full invoice
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                {/* Plain link: a file download, not an Inertia visit */}
                                <a href={InvoiceController.pdf.url(invoice.id)}>
                                    <FileDown /> Download PDF
                                </a>
                            </Button>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * Vertical timeline of one property, newest first: a line down the middle with
 * events alternating left and right (one column on a phone). A payment opens a
 * popup with its invoice.
 */
export function PropertyTimeline({ events }: { events: TimelineEvent[] }) {
    const [shown, setShown] = useState(PAGE);
    // The popup keeps its last payment while it fades out, so it does not go blank.
    const [selected, setSelected] = useState<PaymentEvent | null>(null);
    const [open, setOpen] = useState(false);

    const visible = events.slice(0, shown);
    const older = events.length - visible.length;

    return (
        <div className="rounded-xl border">
            <div className="border-b p-4">
                <h2 className="font-semibold">Payment timeline</h2>
                <p className="text-xs text-muted-foreground">
                    Payments, deposits and tenants moving in or out, newest
                    first. Click a payment to see its invoice.
                </p>
            </div>

            {events.length === 0 ? (
                <p className="p-10 text-center text-sm text-muted-foreground">
                    Nothing yet. Payments and tenancy changes appear here once
                    this property has a tenant.
                </p>
            ) : (
                <div className="p-4 md:p-6">
                    <ol
                        className="relative"
                        aria-label="Payment timeline, newest first"
                    >
                        {/* Grey track with the dark line, as in a printed timeline */}
                        <span
                            aria-hidden
                            className="absolute inset-y-0 left-4 w-9 -translate-x-1/2 rounded-2xl bg-muted md:left-1/2"
                        />
                        <span
                            aria-hidden
                            className="absolute inset-y-2 left-4 w-1 -translate-x-1/2 rounded bg-foreground/80 md:left-1/2"
                        />
                        {visible.map((event, i) => (
                            <Event
                                key={`${event.type}-${event.date}-${event.tenant}-${i}`}
                                event={event}
                                side={i % 2 === 0 ? 'right' : 'left'}
                                onOpenInvoice={(payment) => {
                                    setSelected(payment);
                                    setOpen(true);
                                }}
                            />
                        ))}
                    </ol>

                    {older > 0 && (
                        <div className="mt-6 flex justify-center">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShown((n) => n + PAGE)}
                            >
                                Show older events ({older} more)
                            </Button>
                        </div>
                    )}
                </div>
            )}

            <InvoiceDialog
                payment={selected}
                open={open}
                onOpenChange={setOpen}
            />
        </div>
    );
}
