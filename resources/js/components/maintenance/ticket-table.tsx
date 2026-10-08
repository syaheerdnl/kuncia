import { Link } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import MaintenanceController from '@/actions/App/Http/Controllers/MaintenanceController';
import type { TicketRow } from '@/components/maintenance/types';
import { InitialsAvatar } from '@/components/initials-avatar';
import { StatusBadge } from '@/components/status-badge';

type Props = {
    tickets: TicketRow[];
    showTenant?: boolean;
    showAssignee?: boolean;
    empty?: string;
};

export function TicketTable({
    tickets,
    showTenant = true,
    showAssignee = true,
    empty = 'No tickets.',
}: Props) {
    if (tickets.length === 0) {
        return (
            <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-12 text-sm text-muted-foreground">
                <Wrench className="size-8" /> {empty}
            </div>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                    <tr className="border-b">
                        <th className="px-4 py-2 font-medium">Issue</th>
                        <th className="px-4 py-2 font-medium">Unit</th>
                        {showTenant && (
                            <th className="px-4 py-2 font-medium">
                                Reported by
                            </th>
                        )}
                        {showAssignee && (
                            <th className="px-4 py-2 font-medium">Assigned</th>
                        )}
                        <th className="px-4 py-2 font-medium">Priority</th>
                        <th className="px-4 py-2 text-right font-medium">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {tickets.map((t) => (
                        <tr
                            key={t.id}
                            className="border-b last:border-0 hover:bg-muted/50"
                        >
                            <td className="px-4 py-3">
                                <Link
                                    href={MaintenanceController.show(t.id)}
                                    className="font-medium hover:underline"
                                >
                                    {t.title}
                                </Link>
                                <div className="text-xs text-muted-foreground">
                                    #{t.id} · {t.created_at}
                                </div>
                            </td>
                            <td className="px-4 py-3 text-muted-foreground">
                                {t.unit}
                            </td>
                            {showTenant && (
                                <td className="px-4 py-3">
                                    <span className="flex items-center gap-2">
                                        <InitialsAvatar
                                            name={t.tenant}
                                            className="size-7 text-[0.65rem]"
                                        />
                                        {t.tenant}
                                    </span>
                                </td>
                            )}
                            {showAssignee && (
                                <td className="px-4 py-3">
                                    {t.assignee ?? (
                                        <span className="text-muted-foreground">
                                            Unassigned
                                        </span>
                                    )}
                                </td>
                            )}
                            <td className="px-4 py-3">
                                <StatusBadge status={t.priority} />
                            </td>
                            <td className="px-4 py-3 text-right">
                                <StatusBadge status={t.status} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
