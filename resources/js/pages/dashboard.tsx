import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CircleAlert,
    FileText,
    Home,
    Plus,
    Wallet,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import MyInvoiceController from '@/actions/App/Http/Controllers/Tenant/MyInvoiceController';
import MyMaintenanceController from '@/actions/App/Http/Controllers/Tenant/MyMaintenanceController';
import { BilledCollectedChart } from '@/components/charts/billed-collected-chart';
import { InitialsAvatar } from '@/components/initials-avatar';
import { InvoiceTable } from '@/components/invoices/invoice-table';
import type { InvoiceRow } from '@/components/invoices/types';
import { TicketTable } from '@/components/maintenance/ticket-table';
import type { TicketRow } from '@/components/maintenance/types';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type LandlordProps = {
    view: 'landlord';
    cards: {
        units: number;
        occupied: number;
        occupancy: number;
        collected_month: number;
        outstanding: number;
        overdue_count: number;
        open_tickets: number;
        high_tickets: number;
    };
    trend: {
        month: string;
        label: string;
        billed: number;
        collected: number;
    }[];
    overdue: {
        id: number;
        invoice_no: string;
        tenant: string;
        unit: string;
        total: string;
        days: number;
    }[];
    endingLeases: {
        id: number;
        tenant_id: number;
        tenant: string;
        unit: string;
        end_date: string;
    }[];
    tickets: TicketRow[];
};

type TenantProps = {
    view: 'tenant';
    unit: {
        name: string;
        address: string;
        rent: string;
        due_day: number;
        end_date: string | null;
    } | null;
    amountDue: number;
    nextDue: string | null;
    openInvoices: InvoiceRow[];
    openRequests: number;
};

type StaffProps = { view: 'maintenance'; tickets: TicketRow[] };

type Props = LandlordProps | TenantProps | StaffProps;

function Stat({
    label,
    value,
    sub,
}: {
    label: string;
    value: string | number;
    sub?: string;
}) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-semibold">{value}</p>
            {sub && (
                <p className="mt-0.5 text-xs text-muted-foreground">{sub}</p>
            )}
        </div>
    );
}

const titles = new Set([
    'encik',
    'en',
    'en.',
    'puan',
    'pn',
    'pn.',
    'cik',
    'tuan',
    'haji',
    'hj',
    'hj.',
    'hajah',
    'hjh',
    'hjh.',
    'dr',
    'dr.',
    'datuk',
    "dato'",
    'dato',
    'datin',
    'mr',
    'mr.',
    'mrs',
    'mrs.',
    'ms',
    'ms.',
]);

/** First real name, skipping titles like Encik, Puan, Haji. */
function firstName(full: string): string {
    const words = full
        .replace(/\(.*?\)/g, '')
        .trim()
        .split(/\s+/);

    return words.find((w) => !titles.has(w.toLowerCase())) ?? words[0] ?? '';
}

function greeting(): string {
    const h = new Date().getHours();

    return h < 12 ? 'Good morning' : h < 19 ? 'Good afternoon' : 'Good evening';
}

/** Small ring for occupancy; the number sits beside it. */
function Ring({ percent }: { percent: number }) {
    const r = 15;
    const c = 2 * Math.PI * r;

    return (
        <svg viewBox="0 0 36 36" className="size-14 shrink-0 -rotate-90">
            <circle
                cx="18"
                cy="18"
                r={r}
                fill="none"
                strokeWidth="4"
                className="stroke-muted"
            />
            <circle
                cx="18"
                cy="18"
                r={r}
                fill="none"
                strokeWidth="4"
                strokeLinecap="round"
                className="stroke-primary"
                strokeDasharray={`${(Math.min(percent, 100) / 100) * c} ${c}`}
            />
        </svg>
    );
}

function IconStat({
    label,
    value,
    sub,
    icon: Icon,
    tone,
}: {
    label: string;
    value: string | number;
    sub?: string;
    icon: LucideIcon;
    tone: string;
}) {
    return (
        <div className="rounded-2xl border bg-card p-5">
            <div className="flex items-start justify-between gap-2">
                <p className="text-xs text-muted-foreground">{label}</p>
                <span className={cn('rounded-lg p-1.5', tone)}>
                    <Icon className="size-4" />
                </span>
            </div>
            <p className="mt-2 text-2xl font-bold tabular-nums">{value}</p>
            {sub && <p className="mt-1 text-xs text-muted-foreground">{sub}</p>}
        </div>
    );
}

function LandlordDashboard({
    cards,
    trend,
    overdue,
    endingLeases,
    tickets,
}: LandlordProps) {
    const { auth } = usePage().props;
    const name = firstName(auth.user.name);
    const billedNow = trend.at(-1)?.billed ?? 0;
    const collectedPct = billedNow
        ? Math.round((cards.collected_month / billedNow) * 100)
        : 0;
    const today = new Date().toLocaleDateString('en-MY', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    return (
        <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="text-sm text-muted-foreground">{today}</p>
                    <h1 className="mt-0.5 text-2xl font-bold tracking-tight">
                        {greeting()}, {name}
                    </h1>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={PropertyController.index()}>
                            <Plus /> Add bill
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={InvoiceController.index()}>
                            <Wallet /> Record payment
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="flex items-center gap-4 rounded-2xl border bg-card p-5">
                    <Ring percent={cards.occupancy} />
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Occupancy
                        </p>
                        <p className="text-2xl font-bold">{cards.occupancy}%</p>
                        <p className="text-xs text-muted-foreground">
                            {cards.occupied} of {cards.units} units
                        </p>
                    </div>
                </div>
                <IconStat
                    label="Collected this month"
                    value={formatRM(cards.collected_month)}
                    sub={
                        billedNow
                            ? `of ${formatRM(billedNow)} billed · ${collectedPct}%`
                            : 'nothing billed yet'
                    }
                    icon={Wallet}
                    tone="bg-brand-soft text-brand-strong"
                />
                <IconStat
                    label="Outstanding"
                    value={formatRM(cards.outstanding)}
                    sub={`${cards.overdue_count} overdue invoice${cards.overdue_count === 1 ? '' : 's'}`}
                    icon={CircleAlert}
                    tone="bg-amber-50 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300"
                />
                <IconStat
                    label="Open tickets"
                    value={cards.open_tickets}
                    sub={`${cards.high_tickets} high priority`}
                    icon={Wrench}
                    tone="bg-sky-50 text-sky-600 dark:bg-sky-900/40 dark:text-sky-300"
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
                <div className="rounded-2xl border bg-card p-5">
                    <h2 className="font-semibold">Billed vs collected</h2>
                    <p className="mb-3 text-xs text-muted-foreground">
                        Last 6 months · billed by invoice month, collected by
                        payment date
                    </p>
                    <BilledCollectedChart data={trend} />
                </div>

                <div className="flex flex-col rounded-2xl border bg-card">
                    <div className="flex items-center justify-between border-b p-5">
                        <h2 className="font-semibold">Overdue</h2>
                        <Link
                            href={InvoiceController.index()}
                            className="text-xs font-medium text-brand-strong hover:underline"
                        >
                            View all
                        </Link>
                    </div>
                    {overdue.length === 0 ? (
                        <p className="p-5 text-sm text-muted-foreground">
                            Nothing overdue.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {overdue.map((o) => (
                                <li key={o.id}>
                                    <Link
                                        href={InvoiceController.show(o.id)}
                                        className="flex items-center gap-3 px-5 py-3 hover:bg-muted/50"
                                    >
                                        <InitialsAvatar name={o.tenant} />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {o.tenant}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {o.unit} · {o.days} days late
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold tabular-nums">
                                            {formatRM(o.total)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="mt-auto border-t p-5">
                        <p className="flex items-center gap-1.5 text-[0.7rem] font-semibold tracking-wider text-muted-foreground uppercase">
                            <CalendarClock className="size-3.5" /> Leases ending
                            in 60 days
                        </p>
                        {endingLeases.length === 0 ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                None coming up.
                            </p>
                        ) : (
                            <ul className="mt-3 space-y-3">
                                {endingLeases.map((l) => (
                                    <li key={l.id}>
                                        <Link
                                            href={TenantController.show(
                                                l.tenant_id,
                                            )}
                                            className="flex items-center gap-3"
                                        >
                                            <InitialsAvatar name={l.tenant} />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium hover:underline">
                                                    {l.tenant}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {l.unit}
                                                </p>
                                            </div>
                                            <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                                {l.end_date}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>

            <div className="rounded-2xl border bg-card">
                <div className="flex items-center justify-between p-5">
                    <h2 className="font-semibold">Open maintenance</h2>
                    <Link
                        href={MaintenanceController.index()}
                        className="text-xs font-medium text-brand-strong hover:underline"
                    >
                        View all
                    </Link>
                </div>
                <div className="[&>div]:rounded-none [&>div]:border-x-0 [&>div]:border-b-0">
                    <TicketTable tickets={tickets} empty="No open tickets." />
                </div>
            </div>
        </div>
    );
}

function TenantDashboard({
    unit,
    amountDue,
    nextDue,
    openInvoices,
    openRequests,
}: TenantProps) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            {unit ? (
                <div className="rounded-xl border p-4">
                    <p className="flex items-center gap-1 text-xs text-muted-foreground">
                        <Home className="size-3.5" /> My unit
                    </p>
                    <p className="mt-1 text-lg font-semibold">{unit.name}</p>
                    <p className="text-sm text-muted-foreground">
                        {unit.address}
                    </p>
                    <p className="mt-2 text-sm">
                        Rent {formatRM(unit.rent)} · due day {unit.due_day}
                        {unit.end_date && ` · lease ends ${unit.end_date}`}
                    </p>
                </div>
            ) : (
                <div className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                    You have no active tenancy.
                </div>
            )}

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <Stat
                    label="Amount due"
                    value={formatRM(amountDue)}
                    sub={nextDue ? `next due ${nextDue}` : 'all paid up'}
                />
                <Stat label="Open requests" value={openRequests} />
            </div>

            <div className="flex flex-wrap gap-2">
                <Button asChild>
                    <Link href={MyInvoiceController.index()}>
                        <FileText /> My invoices
                    </Link>
                </Button>
                <Button variant="outline" asChild>
                    <Link href={MyMaintenanceController.index()}>
                        <Wrench /> Report a problem
                    </Link>
                </Button>
            </div>

            {openInvoices.length > 0 && (
                <div className="space-y-3">
                    <h2 className="font-semibold">Unpaid invoices</h2>
                    <InvoiceTable
                        invoices={openInvoices}
                        href={(id) => MyInvoiceController.show(id)}
                        showTenant={false}
                    />
                </div>
            )}
        </div>
    );
}

function StaffDashboard({ tickets }: StaffProps) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Stat
                    label="Jobs to do"
                    value={tickets.length}
                    sub="open + in progress"
                />
            </div>
            <TicketTable
                tickets={tickets}
                showAssignee={false}
                empty="Nothing assigned to you right now."
            />
        </div>
    );
}

export default function Dashboard(props: Props) {
    return (
        <>
            <Head title="Dashboard" />
            {props.view === 'landlord' && <LandlordDashboard {...props} />}
            {props.view === 'tenant' && <TenantDashboard {...props} />}
            {props.view === 'maintenance' && <StaffDashboard {...props} />}
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
