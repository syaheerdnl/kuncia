import {
    AlarmClock,
    Banknote,
    HandCoins,
    LogIn,
    LogOut,
    PiggyBank,
    Receipt,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { TimelineEvent } from '@/components/properties/types';

/** What the filter chips group the events by. */
export const GROUPS = [
    'Payments',
    'Charges',
    'Deposits and payouts',
    'Tenants',
] as const;

export type Group = (typeof GROUPS)[number];

type Kind = {
    title: string;
    group: Group;
    icon: LucideIcon;
    /** Soft tint for the card, its label and the dot. Full class names so Tailwind finds them. */
    card: string;
    text: string;
    dot: string;
};

export const KINDS: Record<TimelineEvent['type'], Kind> = {
    payment: {
        title: 'Payment received',
        group: 'Payments',
        icon: Banknote,
        card: 'border-teal-600/25 bg-teal-500/10',
        text: 'text-teal-800 dark:text-teal-300',
        dot: 'bg-teal-600',
    },
    late_fee: {
        title: 'Late fee added',
        group: 'Charges',
        icon: AlarmClock,
        card: 'border-red-600/25 bg-red-500/10',
        text: 'text-red-800 dark:text-red-300',
        dot: 'bg-red-600',
    },
    utility_charge: {
        title: 'Utility bill charged',
        group: 'Charges',
        icon: Zap,
        card: 'border-lime-600/25 bg-lime-500/10',
        text: 'text-lime-800 dark:text-lime-300',
        dot: 'bg-lime-600',
    },
    extra_charge: {
        title: 'Extra charge added',
        group: 'Charges',
        icon: Receipt,
        card: 'border-pink-600/25 bg-pink-500/10',
        text: 'text-pink-800 dark:text-pink-300',
        dot: 'bg-pink-600',
    },
    deposit_received: {
        title: 'Deposit received',
        group: 'Deposits and payouts',
        icon: PiggyBank,
        card: 'border-violet-600/25 bg-violet-500/10',
        text: 'text-violet-800 dark:text-violet-300',
        dot: 'bg-violet-600',
    },
    paid_to_tenant: {
        title: 'Paid to tenant',
        group: 'Deposits and payouts',
        icon: HandCoins,
        card: 'border-orange-600/25 bg-orange-500/10',
        text: 'text-orange-800 dark:text-orange-300',
        dot: 'bg-orange-600',
    },
    tenancy_start: {
        title: 'Tenant moved in',
        group: 'Tenants',
        icon: LogIn,
        card: 'border-blue-600/25 bg-blue-500/10',
        text: 'text-blue-800 dark:text-blue-300',
        dot: 'bg-blue-600',
    },
    tenancy_end: {
        title: 'Tenant moved out',
        group: 'Tenants',
        icon: LogOut,
        card: 'border-slate-600/25 bg-slate-500/10',
        text: 'text-slate-800 dark:text-slate-300',
        dot: 'bg-slate-600',
    },
};

const parse = (iso: string) => new Date(`${iso}T00:00:00`);

export const formatDate = (iso: string) =>
    parse(iso).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

export const dayNumber = (iso: string) => parse(iso).getDate();

export const monthShort = (iso: string) =>
    parse(iso).toLocaleDateString('en-MY', { month: 'short' });

/** "2026-09" for grouping. */
export const monthKey = (iso: string) => iso.slice(0, 7);

/** "September 2026" for a month header. */
export const monthLabel = (key: string) =>
    parse(`${key}-01`).toLocaleDateString('en-MY', {
        month: 'long',
        year: 'numeric',
    });
