import { cn } from '@/lib/utils';

const tones = [
    'bg-teal-100 text-teal-700 dark:bg-teal-900/60 dark:text-teal-300',
    'bg-sky-100 text-sky-700 dark:bg-sky-900/60 dark:text-sky-300',
    'bg-violet-100 text-violet-700 dark:bg-violet-900/60 dark:text-violet-300',
    'bg-rose-100 text-rose-700 dark:bg-rose-900/60 dark:text-rose-300',
    'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300',
    'bg-lime-100 text-lime-700 dark:bg-lime-900/60 dark:text-lime-300',
];

/** Coloured initials; the same name always gets the same colour. */
export function InitialsAvatar({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    const initials = name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase())
        .join('');
    let hash = 7;

    for (let i = 0; i < name.length; i++) {
        hash = (hash * 31 + name.charCodeAt(i)) >>> 0;
    }

    return (
        <span
            aria-hidden="true"
            className={cn(
                'grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold',
                tones[hash % tones.length],
                className,
            )}
        >
            {initials}
        </span>
    );
}
