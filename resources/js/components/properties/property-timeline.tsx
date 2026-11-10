import { Banknote, LogIn, LogOut } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { TimelineEvent } from '@/components/properties/types';
import { Button } from '@/components/ui/button';
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
}: {
    event: TimelineEvent;
    side: 'left' | 'right';
}) {
    const kind = KINDS[event.type];
    const Icon = kind.icon;
    const left = side === 'left';

    return (
        <li
            className={cn(
                'relative pb-8 pl-12 last:pb-0 md:grid md:grid-cols-2 md:pl-0',
            )}
        >
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
                    'space-y-1.5 text-sm',
                    left
                        ? 'md:col-start-1 md:pr-12 md:text-right'
                        : 'md:col-start-2 md:pl-12',
                )}
            >
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
            </div>
        </li>
    );
}

/**
 * Vertical timeline of one property, newest first: a line down the middle with
 * events alternating left and right (one column on a phone).
 */
export function PropertyTimeline({ events }: { events: TimelineEvent[] }) {
    const [shown, setShown] = useState(PAGE);
    const visible = events.slice(0, shown);
    const older = events.length - visible.length;

    return (
        <div className="rounded-xl border">
            <div className="border-b p-4">
                <h2 className="font-semibold">Payment timeline</h2>
                <p className="text-xs text-muted-foreground">
                    Payments received and tenants moving in or out, newest first
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
        </div>
    );
}
