import { Head, router } from '@inertiajs/react';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
import Heading from '@/components/heading';
import { AddStaffDialog } from '@/components/maintenance/add-staff-dialog';
import { TicketTable } from '@/components/maintenance/ticket-table';
import type { TicketRow } from '@/components/maintenance/types';
import type { Option } from '@/components/properties/types';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Filters = { status: string; priority: string; property: string };

type Props = {
    tickets: TicketRow[];
    filters: Filters;
    properties: { id: number; name: string }[];
    staff: { id: number; name: string; email: string; phone: string | null }[];
    statuses: Option[];
    priorities: Option[];
};

const ALL = 'all';

export default function MaintenanceIndex({ tickets, filters, properties, staff, statuses, priorities }: Props) {
    const apply = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };
        const query = Object.fromEntries(Object.entries(next).filter(([, v]) => v && v !== ALL));
        router.get(MaintenanceController.index.url(), query, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCount = tickets.filter((t) => t.status === 'open' || t.status === 'in_progress').length;

    return (
        <>
            <Head title="Maintenance" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title="Maintenance" description={`${openCount} ticket(s) need attention`} />
                    <AddStaffDialog />
                </div>

                <div className="flex flex-wrap gap-3">
                    <Select value={filters.status || ALL} onValueChange={(v) => apply({ status: v })}>
                        <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All statuses</SelectItem>
                            {statuses.map((s) => <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>)}
                        </SelectContent>
                    </Select>
                    <Select value={filters.priority || ALL} onValueChange={(v) => apply({ priority: v })}>
                        <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All priorities</SelectItem>
                            {priorities.map((p) => <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>)}
                        </SelectContent>
                    </Select>
                    <Select value={filters.property || ALL} onValueChange={(v) => apply({ property: v })}>
                        <SelectTrigger className="w-56"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>All properties</SelectItem>
                            {properties.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
                        </SelectContent>
                    </Select>
                    {(filters.status || filters.priority || filters.property) && (
                        <Button variant="ghost" onClick={() => router.get(MaintenanceController.index.url())}>Clear</Button>
                    )}
                </div>

                <TicketTable tickets={tickets} empty="No tickets match." />

                <div className="rounded-xl border p-4">
                    <h2 className="mb-2 font-semibold">Your maintenance staff</h2>
                    {staff.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No staff yet. Add someone to assign tickets to.</p>
                    ) : (
                        <ul className="grid gap-1 text-sm sm:grid-cols-2">
                            {staff.map((s) => (
                                <li key={s.id}>
                                    <span className="font-medium">{s.name}</span>{' '}
                                    <span className="text-muted-foreground">· {s.email} · {s.phone}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

MaintenanceIndex.layout = {
    breadcrumbs: [{ title: 'Maintenance', href: MaintenanceController.index() }],
};
