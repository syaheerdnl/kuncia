import { cn } from '@/lib/utils';

const styles: Record<string, string> = {
    // units / tenancies
    vacant: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    occupied: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    maintenance: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    ended: 'bg-muted text-muted-foreground',
    terminated: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    // invoices
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    unpaid: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    overdue: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    void: 'bg-muted text-muted-foreground line-through',
};

export function StatusBadge({ status, className }: { status: string; className?: string }) {
    return (
        <span className={cn('rounded-md px-2 py-0.5 text-xs font-medium capitalize', styles[status] ?? 'bg-muted', className)}>
            {status.replace('_', ' ')}
        </span>
    );
}
