import { Link } from '@inertiajs/react';
import { Ban, Check, Clock, Plus, TriangleAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Fragment, useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import { RentMonthDialog } from '@/components/properties/rent-month-dialog';
import type { RentMonth, RentTenancy } from '@/components/properties/types';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';
import { cn } from '@/lib/utils';

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/** Every status has an icon too, so it never depends on colour alone. */
const STATUS: Record<
    RentMonth['status'],
    { label: string; icon: LucideIcon; cell: string }
> = {
    paid: {
        label: 'Paid',
        icon: Check,
        cell: 'border-teal-600/25 bg-teal-500/15 text-teal-800 dark:text-teal-300',
    },
    unpaid: {
        label: 'Unpaid',
        icon: Clock,
        cell: 'border-amber-600/30 bg-amber-500/15 text-amber-800 dark:text-amber-300',
    },
    overdue: {
        label: 'Overdue',
        icon: TriangleAlert,
        cell: 'border-red-600/30 bg-red-500/15 text-red-800 dark:text-red-300',
    },
    void: {
        label: 'Voided',
        icon: Ban,
        cell: 'border-slate-500/25 bg-slate-500/10 text-slate-600 dark:text-slate-400',
    },
    missing: {
        label: 'No invoice yet',
        icon: Plus,
        cell: 'border-dashed border-red-500/60 text-red-700 hover:bg-red-500/10 dark:text-red-300',
    },
};

const cellBase =
    'flex h-8 items-center justify-center rounded-md border text-xs outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring';

/** Group a tenancy's months by year, so each year is one row of twelve. */
function byYear(months: RentMonth[]) {
    const years = new Map<string, Map<number, RentMonth>>();

    for (const m of months) {
        const year = m.month.slice(0, 4);
        const index = Number(m.month.slice(5, 7)) - 1;
        years.set(year, (years.get(year) ?? new Map()).set(index, m));
    }

    return [...years.entries()];
}

/**
 * Rent month by month for each tenancy on the property. A dashed month has no invoice at all,
 * which is what the monthly job never notices for a tenancy added late: click it to catch it up.
 */
export function RentCalendar({
    propertyId,
    tenancies,
}: {
    propertyId: number;
    tenancies: RentTenancy[];
}) {
    const [open, setOpen] = useState(false);
    const [target, setTarget] = useState({ tenancyId: 0, month: '' });

    if (tenancies.length === 0) {
        return null;
    }

    const withGaps = tenancies.filter((t) => t.missing.length > 0);
    const gapCount = withGaps.reduce((n, t) => n + t.missing.length, 0);

    const openFor = (tenancyId: number, month: string) => {
        setTarget({ tenancyId, month });
        setOpen(true);
    };

    return (
        <div className="rounded-xl border">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                <div>
                    <h2 className="font-semibold">Rent by month</h2>
                    <p className="text-xs text-muted-foreground">
                        Every month since move-in. A dashed month has no invoice
                        yet.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    {(['paid', 'unpaid', 'overdue', 'missing'] as const).map(
                        (s) => {
                            const Icon = STATUS[s].icon;

                            return (
                                <span
                                    key={s}
                                    className="inline-flex items-center gap-1"
                                >
                                    <span
                                        className={cn(
                                            'flex size-4 items-center justify-center rounded border',
                                            STATUS[s].cell,
                                        )}
                                    >
                                        <Icon className="size-3" aria-hidden />
                                    </span>
                                    {STATUS[s].label}
                                </span>
                            );
                        },
                    )}
                </div>
            </div>

            <div className="space-y-5 p-4">
                {gapCount > 0 && (
                    <div
                        role="status"
                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm"
                    >
                        <div>
                            <p className="font-medium text-red-800 dark:text-red-300">
                                {gapCount} month{gapCount === 1 ? '' : 's'} with
                                no rent recorded
                            </p>
                            {withGaps.map((t) => (
                                <p key={t.id} className="text-muted-foreground">
                                    {t.label}:{' '}
                                    {t.missing.map((m) => m.label).join(', ')}
                                </p>
                            ))}
                        </div>
                        <Button
                            size="sm"
                            onClick={() =>
                                openFor(
                                    withGaps[0].id,
                                    withGaps[0].missing[0].month,
                                )
                            }
                        >
                            <Plus /> Add a missed month
                        </Button>
                    </div>
                )}

                {tenancies.map((t) => (
                    <div key={t.id} className="space-y-2">
                        <p className="text-sm font-medium">
                            {t.label}{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                · rent {formatRM(t.rent)}
                            </span>
                        </p>

                        <div className="grid grid-cols-[2.5rem_repeat(12,minmax(0,1fr))] items-center gap-1 text-center">
                            <span />
                            {MONTHS.map((m) => (
                                <span
                                    key={m}
                                    className="text-[10px] text-muted-foreground"
                                >
                                    <span className="sm:hidden">{m[0]}</span>
                                    <span className="hidden sm:inline">
                                        {m}
                                    </span>
                                </span>
                            ))}

                            {byYear(t.months).map(([year, row]) => (
                                <Fragment key={year}>
                                    <span className="pr-1 text-right text-xs text-muted-foreground tabular-nums">
                                        {year}
                                    </span>
                                    {MONTHS.map((name, i) => {
                                        const m = row.get(i);

                                        if (!m) {
                                            return (
                                                <span
                                                    key={name}
                                                    aria-hidden
                                                    className="h-8"
                                                />
                                            );
                                        }

                                        const s = STATUS[m.status];
                                        const Icon = s.icon;
                                        const label = `${m.label}: ${s.label.toLowerCase()}`;

                                        if (m.invoice_id === null) {
                                            return (
                                                <button
                                                    key={name}
                                                    type="button"
                                                    title={`${label}. Click to add it.`}
                                                    aria-label={`${label}. Add this month.`}
                                                    onClick={() =>
                                                        openFor(t.id, m.month)
                                                    }
                                                    className={cn(
                                                        cellBase,
                                                        s.cell,
                                                    )}
                                                >
                                                    <Icon
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                </button>
                                            );
                                        }

                                        return (
                                            <Link
                                                key={name}
                                                href={InvoiceController.show(
                                                    m.invoice_id,
                                                )}
                                                title={`${label}. Open the invoice.`}
                                                aria-label={`${label}. Open the invoice.`}
                                                className={cn(cellBase, s.cell)}
                                            >
                                                <Icon
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                            </Link>
                                        );
                                    })}
                                </Fragment>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {withGaps.length > 0 && (
                <RentMonthDialog
                    // A new target remounts it, so the form starts on the month that was clicked.
                    key={`${target.tenancyId}-${target.month}`}
                    propertyId={propertyId}
                    tenancies={withGaps}
                    open={open}
                    onOpenChange={setOpen}
                    initial={target}
                />
            )}
        </div>
    );
}
