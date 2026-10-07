import { Head } from '@inertiajs/react';
import TaskController from '@/actions/App/Http/Controllers/TaskController';
import Heading from '@/components/heading';
import { TicketTable } from '@/components/maintenance/ticket-table';
import type { TicketRow } from '@/components/maintenance/types';

export default function TasksIndex({ tickets }: { tickets: TicketRow[] }) {
    const active = tickets.filter(
        (t) => t.status === 'open' || t.status === 'in_progress',
    ).length;

    return (
        <>
            <Head title="My tasks" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="My tasks"
                    description={`${active} job(s) to do`}
                />
                <TicketTable
                    tickets={tickets}
                    showAssignee={false}
                    empty="Nothing assigned to you yet."
                />
            </div>
        </>
    );
}

TasksIndex.layout = {
    breadcrumbs: [{ title: 'My tasks', href: TaskController.index() }],
};
