import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { RecordTransactionDialog } from '@/components/properties/record-transaction-dialog';
import { EventCard } from '@/components/properties/timeline-event-card';
import {
    GROUPS,
    KINDS,
    dayNumber,
    monthKey,
    monthLabel,
    monthShort,
} from '@/components/properties/timeline-kinds';
import type { Group } from '@/components/properties/timeline-kinds';
import type {
    TenancyOption,
    TimelineEvent,
} from '@/components/properties/types';
import { formatRM } from '@/lib/format';
import { cn } from '@/lib/utils';

/** The newest months start open; older ones start folded so a long history stays easy to scan. */
const OPEN_MONTHS = 2;

type Item = { event: TimelineEvent; key: string };
type Day = { date: string; items: Item[] };
type Month = { month: string; days: Day[] };

/** Events arrive newest first, so a month's days and a day's events are already next to each other. */
function groupByMonth(items: Item[]): Month[] {
    const months: Month[] = [];

    for (const item of items) {
        const key = monthKey(item.event.date);
        let month = months[months.length - 1];

        if (!month || month.month !== key) {
            month = { month: key, days: [] };
            months.push(month);
        }

        let day = month.days[month.days.length - 1];

        if (!day || day.date !== item.event.date) {
            day = { date: item.event.date, items: [] };
            month.days.push(day);
        }

        day.items.push(item);
    }

    return months;
}

/** What came in and what was charged in a month, shown on its header. */
function totals(events: TimelineEvent[]) {
    let collected = 0;
    let charges = 0;

    for (const event of events) {
        if (event.type === 'payment') {
            collected += Number(event.amount);
        } else if (KINDS[event.type].group === 'Charges') {
            charges += Number(event.amount);
        }
    }

    return { collected, charges };
}

function DayRow({
    day,
    last,
    openKey,
    onToggle,
}: {
    day: Day;
    last: boolean;
    openKey: string | null;
    onToggle: (key: string) => void;
}) {
    // A day with one event takes its colour; a busy day gets a neutral dot.
    const dot =
        day.items.length === 1
            ? KINDS[day.items[0].event.type].dot
            : 'bg-foreground';

    return (
        <div className="grid grid-cols-[2.5rem_0.75rem_1fr] gap-x-2 sm:grid-cols-[3rem_1rem_1fr] sm:gap-x-3">
            <div className="pt-1 text-right">
                <div className="text-xl leading-none font-medium tabular-nums">
                    {dayNumber(day.date)}
                </div>
                <div className="text-xs text-muted-foreground">
                    {monthShort(day.date)}
                </div>
            </div>

            <div className="relative">
                <span
                    aria-hidden
                    className={cn(
                        'absolute top-0 left-1/2 w-0.5 -translate-x-1/2 bg-border',
                        last ? 'bottom-4' : 'bottom-0',
                    )}
                />
                <span
                    aria-hidden
                    className={cn(
                        'absolute top-2 left-1/2 size-2.5 -translate-x-1/2 rounded-full ring-4 ring-background',
                        dot,
                    )}
                />
            </div>

            {/* Events of the same day sit side by side, and wrap when there is no room. */}
            <div className="flex flex-wrap items-start gap-2 pb-4">
                {day.items.map(({ event, key }) => (
                    <EventCard
                        key={key}
                        event={event}
                        open={openKey === key}
                        onToggle={() => onToggle(key)}
                    />
                ))}
            </div>
        </div>
    );
}

/**
 * Calm feed of one property, newest first: filter chips, then months you can fold,
 * each day a row with its events as soft cards. Click a card to open it in place.
 */
export function PropertyTimeline({
    events,
    propertyId,
    tenancies,
}: {
    events: TimelineEvent[];
    propertyId: number;
    tenancies: TenancyOption[];
}) {
    const [hidden, setHidden] = useState<Partial<Record<Group, boolean>>>({});
    // Months the person opened or folded by hand; the rest follow the default.
    const [folded, setFolded] = useState<Record<string, boolean>>({});
    const [openKey, setOpenKey] = useState<string | null>(null);

    const items: Item[] = events.map((event, i) => ({ event, key: String(i) }));
    const shown = items.filter(({ event }) => !hidden[KINDS[event.type].group]);
    const months = groupByMonth(shown);

    const isFolded = (month: string, index: number) =>
        folded[month] ?? index >= OPEN_MONTHS;
    const anyFolded = months.some((m, i) => isFolded(m.month, i));

    const count = (group: Group) =>
        events.filter((e) => KINDS[e.type].group === group).length;

    return (
        <div className="rounded-xl border">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                <div>
                    <h2 className="font-semibold">Payment timeline</h2>
                    <p className="text-xs text-muted-foreground">
                        Payments, charges, deposits and tenants, newest first.
                        Click a card to see more.
                    </p>
                </div>
                {tenancies.length > 0 && (
                    <RecordTransactionDialog
                        propertyId={propertyId}
                        tenancies={tenancies}
                    />
                )}
            </div>

            {events.length === 0 ? (
                <p className="p-10 text-center text-sm text-muted-foreground">
                    Nothing yet. Payments and tenancy changes appear here once
                    this property has a tenant.
                </p>
            ) : (
                <div className="space-y-4 p-4 md:p-6">
                    <div className="flex flex-wrap items-center gap-2">
                        {GROUPS.map((group) => {
                            const on = !hidden[group];

                            return (
                                <button
                                    key={group}
                                    type="button"
                                    aria-pressed={on}
                                    onClick={() =>
                                        setHidden((h) => ({
                                            ...h,
                                            [group]: on,
                                        }))
                                    }
                                    className={cn(
                                        'rounded-full border px-3 py-1 text-xs transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                        on
                                            ? 'border-transparent bg-secondary font-medium text-secondary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    {group}{' '}
                                    <span className="tabular-nums opacity-70">
                                        {count(group)}
                                    </span>
                                </button>
                            );
                        })}
                        <button
                            type="button"
                            onClick={() =>
                                setFolded(
                                    Object.fromEntries(
                                        months.map((m) => [
                                            m.month,
                                            !anyFolded,
                                        ]),
                                    ),
                                )
                            }
                            className="ml-auto text-xs text-muted-foreground underline-offset-2 hover:underline"
                        >
                            {anyFolded ? 'Open all months' : 'Fold all months'}
                        </button>
                    </div>

                    {months.length === 0 && (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            Nothing matches these filters.
                        </p>
                    )}

                    {months.map((month, mi) => {
                        const folded = isFolded(month.month, mi);
                        const sum = totals(
                            events.filter(
                                (e) => monthKey(e.date) === month.month,
                            ),
                        );

                        return (
                            <section key={month.month}>
                                <button
                                    type="button"
                                    aria-expanded={!folded}
                                    onClick={() =>
                                        setFolded((f) => ({
                                            ...f,
                                            [month.month]: !folded,
                                        }))
                                    }
                                    className="mb-4 flex w-full flex-wrap items-center gap-x-3 gap-y-0.5 border-b py-2 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <ChevronDown
                                        className={cn(
                                            'size-4 shrink-0 text-muted-foreground transition-transform',
                                            folded && '-rotate-90',
                                        )}
                                        aria-hidden
                                    />
                                    <span className="font-medium">
                                        {monthLabel(month.month)}
                                    </span>
                                    <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                                        {sum.collected > 0 &&
                                            `Collected ${formatRM(sum.collected)}`}
                                        {sum.collected > 0 &&
                                            sum.charges > 0 &&
                                            ' · '}
                                        {sum.charges > 0 &&
                                            `Charges ${formatRM(sum.charges)}`}
                                    </span>
                                </button>

                                {!folded &&
                                    month.days.map((day, di) => (
                                        <DayRow
                                            key={day.date}
                                            day={day}
                                            last={di === month.days.length - 1}
                                            openKey={openKey}
                                            onToggle={(key) =>
                                                setOpenKey((k) =>
                                                    k === key ? null : key,
                                                )
                                            }
                                        />
                                    ))}
                            </section>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
