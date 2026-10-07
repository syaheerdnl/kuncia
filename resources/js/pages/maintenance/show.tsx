import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import MyMaintenanceController from '@/actions/App/Http/Controllers/Tenant/MyMaintenanceController';
import InputError from '@/components/input-error';
import type { TicketDetail } from '@/components/maintenance/types';
import type { Option } from '@/components/properties/types';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Props = {
    ticket: TicketDetail;
    nextStatuses: Option[];
    canAssign: boolean;
    staff: { id: number; name: string }[];
    priorities: Option[];
};

const UNASSIGNED = 'none';

export default function MaintenanceShow({
    ticket,
    nextStatuses,
    canAssign,
    staff,
    priorities,
}: Props) {
    const { auth } = usePage().props;
    const back =
        auth.role === 'tenant'
            ? MyMaintenanceController.index()
            : auth.role === 'maintenance'
              ? TaskController.index()
              : MaintenanceController.index();
    const [assignee, setAssignee] = useState(
        ticket.assigned_to ? String(ticket.assigned_to) : UNASSIGNED,
    );
    const [target, setTarget] = useState(nextStatuses[0]?.value ?? '');

    return (
        <>
            <Head title={ticket.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Link
                    href={back}
                    className="flex w-fit items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft className="size-4" /> Back
                </Link>

                <div className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-semibold">
                            {ticket.title}
                        </h1>
                        <StatusBadge status={ticket.status} />
                        <StatusBadge status={ticket.priority} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        #{ticket.id} · {ticket.unit} · reported by{' '}
                        {ticket.tenant} on {ticket.created_at}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <div className="rounded-xl border p-4">
                            <h2 className="mb-2 font-semibold">Description</h2>
                            <p className="text-sm whitespace-pre-line">
                                {ticket.description}
                            </p>
                        </div>

                        {ticket.photos.length > 0 && (
                            <div className="rounded-xl border p-4">
                                <h2 className="mb-3 font-semibold">Photos</h2>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {ticket.photos.map((p) => (
                                        <a
                                            key={p.id}
                                            href={p.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <img
                                                src={p.url}
                                                alt={p.name}
                                                className="aspect-square w-full rounded-lg object-cover"
                                            />
                                        </a>
                                    ))}
                                </div>
                            </div>
                        )}

                        {ticket.resolution_note && (
                            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                                <h2 className="mb-1 font-semibold">Fix note</h2>
                                <p className="text-sm">
                                    {ticket.resolution_note}
                                </p>
                                {ticket.resolved_at && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Resolved {ticket.resolved_at}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border p-4 text-sm">
                            <p className="text-xs text-muted-foreground">
                                Assigned to
                            </p>
                            <p className="font-medium">
                                {ticket.assignee ?? 'Unassigned'}
                            </p>
                        </div>

                        {canAssign && (
                            <Form
                                {...MaintenanceController.assign.form(
                                    ticket.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-3 rounded-xl border p-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <h2 className="font-semibold">
                                            Assign
                                        </h2>
                                        <div className="grid gap-2">
                                            <Label>Staff</Label>
                                            <Select
                                                value={assignee}
                                                onValueChange={setAssignee}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={UNASSIGNED}
                                                    >
                                                        Unassigned
                                                    </SelectItem>
                                                    {staff.map((s) => (
                                                        <SelectItem
                                                            key={s.id}
                                                            value={String(s.id)}
                                                        >
                                                            {s.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {/* "none" is not a user id, so send an empty value instead */}
                                            <input
                                                type="hidden"
                                                name="assigned_to"
                                                value={
                                                    assignee === UNASSIGNED
                                                        ? ''
                                                        : assignee
                                                }
                                            />
                                            <InputError
                                                message={errors.assigned_to}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Priority</Label>
                                            <Select
                                                name="priority"
                                                defaultValue={ticket.priority}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {priorities.map((p) => (
                                                        <SelectItem
                                                            key={p.value}
                                                            value={p.value}
                                                        >
                                                            {p.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Button
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            Save
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}

                        {nextStatuses.length > 0 && (
                            <Form
                                {...MaintenanceController.updateStatus.form(
                                    ticket.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-3 rounded-xl border p-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <h2 className="font-semibold">
                                            Update status
                                        </h2>
                                        <Select
                                            name="status"
                                            value={target}
                                            onValueChange={setTarget}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {nextStatuses.map((s) => (
                                                    <SelectItem
                                                        key={s.value}
                                                        value={s.value}
                                                    >
                                                        {s.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                        {(target === 'resolved' ||
                                            (target === 'closed' &&
                                                ticket.status !==
                                                    'resolved')) && (
                                            <div className="grid gap-2">
                                                <Label htmlFor="note">
                                                    {target === 'resolved'
                                                        ? 'What was fixed?'
                                                        : 'Why close it? (e.g. fixed by our own contractor)'}
                                                </Label>
                                                <textarea
                                                    id="note"
                                                    name="note"
                                                    rows={3}
                                                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                                />
                                                <InputError
                                                    message={errors.note}
                                                />
                                            </div>
                                        )}
                                        <Button
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            Update
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
