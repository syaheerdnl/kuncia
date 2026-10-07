import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    FileText,
    Home,
    Wrench,
} from 'lucide-react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import MyInvoiceController from '@/actions/App/Http/Controllers/Tenant/MyInvoiceController';
import MyMaintenanceController from '@/actions/App/Http/Controllers/Tenant/MyMaintenanceController';
import { BilledCollectedChart } from '@/components/charts/billed-collected-chart';
import { InvoiceTable } from '@/components/invoices/invoice-table';
import type { InvoiceRow } from '@/components/invoices/types';
import { TicketTable } from '@/components/maintenance/ticket-table';
import type { TicketRow } from '@/components/maintenance/types';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';
import { dashboard } from '@/routes';

type LandlordProps = {
    view: 'landlord';
    cards: {
        units: number;
        occupied: number;
        occupancy: number;
        collected_month: number;
        outstanding: number;
        open_tickets: number;
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

function LandlordDashboard({
    cards,
    trend,
    overdue,
    endingLeases,
    tickets,
}: LandlordProps) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Stat
                    label="Occupancy"
                    value={`${cards.occupancy}%`}
                    sub={`${cards.occupied} of ${cards.units} units`}
                />
                <Stat
                    label="Collected this month"
                    value={formatRM(cards.collected_month)}
                />
                <Stat
                    label="Outstanding"
                    value={formatRM(cards.outstanding)}
                    sub="unpaid + overdue"
                />
                <Stat
                    label="Open tickets"
                    value={cards.open_tickets}
                    sub="open + in progress"
                />
            </div>

            <div className="rounded-xl border p-4">
                <h2 className="font-semibold">Billed vs collected</h2>
                <p className="mb-3 text-xs text-muted-foreground">
                    Last 6 months · billed by invoice month, collected by
                    payment date
                </p>
                <BilledCollectedChart data={trend} />
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-xl border">
                    <h2 className="flex items-center gap-2 border-b p-4 font-semibold">
                        <AlertTriangle className="size-4 text-red-600" />{' '}
                        Overdue invoices
                    </h2>
                    {overdue.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            Nothing overdue.
                        </p>
                    ) : (
                        <ul className="divide-y text-sm">
                            {overdue.map((o) => (
                                <li
                                    key={o.id}
                                    className="flex items-center justify-between gap-2 px-4 py-2"
                                >
                                    <Link
                                        href={InvoiceController.show(o.id)}
                                        className="hover:underline"
                                    >
                                        <span className="font-medium">
                                            {o.tenant}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            · {o.unit} · {o.days} days late
                                        </span>
                                    </Link>
                                    <span className="font-medium tabular-nums">
                                        {formatRM(o.total)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="rounded-xl border">
                    <h2 className="flex items-center gap-2 border-b p-4 font-semibold">
                        <CalendarClock className="size-4" /> Leases ending in 60
                        days
                    </h2>
                    {endingLeases.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            No leases ending soon.
                        </p>
                    ) : (
                        <ul className="divide-y text-sm">
                            {endingLeases.map((l) => (
                                <li
                                    key={l.id}
                                    className="flex items-center justify-between px-4 py-2"
                                >
                                    <Link
                                        href={TenantController.show(
                                            l.tenant_id,
                                        )}
                                        className="hover:underline"
                                    >
                                        <span className="font-medium">
                                            {l.tenant}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            · {l.unit}
                                        </span>
                                    </Link>
                                    <span>{l.end_date}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="font-semibold">Open maintenance</h2>
                    <Link
                        href={MaintenanceController.index()}
                        className="text-sm text-muted-foreground hover:underline"
                    >
                        View all
                    </Link>
                </div>
                <TicketTable tickets={tickets} empty="No open tickets." />
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
