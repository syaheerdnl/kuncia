import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/** Brand panel on the left (desktop), form on the right. */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="grid min-h-dvh lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-teal-700 p-12 text-white lg:flex dark:bg-teal-950">
                <div className="absolute -right-24 -bottom-24 size-[420px] rounded-full bg-teal-600/60 dark:bg-teal-900/60" />
                <div className="absolute right-24 bottom-40 size-40 rounded-full bg-teal-500/40 dark:bg-teal-800/40" />

                <Link
                    href={home()}
                    className="relative flex items-center gap-3"
                >
                    <span className="grid size-10 place-items-center rounded-xl bg-white text-teal-700">
                        <AppLogoIcon className="size-6" />
                    </span>
                    <span className="text-2xl font-bold tracking-tight">
                        {name}
                    </span>
                </Link>

                <div className="relative max-w-md">
                    <p className="text-4xl leading-tight font-bold">
                        Rent, bills and repairs in one place.
                    </p>
                    <p className="mt-4 text-teal-100">
                        Invoices go out on the 1st, utility bills split
                        themselves, and tenants report problems with a photo.
                    </p>
                </div>

                <p className="relative text-sm text-teal-200">
                    © {new Date().getFullYear()} {name}
                </p>
            </div>

            <div className="flex items-center justify-center p-6 sm:p-10">
                <div className="w-full max-w-sm space-y-6">
                    <Link
                        href={home()}
                        className="flex items-center gap-2.5 lg:hidden"
                    >
                        <span className="grid size-9 place-items-center rounded-lg bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-5" />
                        </span>
                        <span className="text-xl font-bold tracking-tight">
                            {name}
                        </span>
                    </Link>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
