import { Head } from '@inertiajs/react';
import { FileDown, Home, Mail, Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import MyUnitController from '@/actions/App/Http/Controllers/Tenant/MyUnitController';
import TenancyController from '@/actions/App/Http/Controllers/TenancyController';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';

type MyTenancy = {
    id: number;
    status: string;
    start_date: string;
    end_date: string | null;
    monthly_rent: string;
    due_day: number;
    deposit_amount: string;
    deposit_status: string;
    unit: { code: string; type: string };
    property: { name: string; type: string; address: string };
    landlord: { name: string; email: string; phone: string | null };
};

const formatDate = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

/** 1 -> 1st, 2 -> 2nd, 7 -> 7th */
function ordinal(n: number): string {
    const rem = n % 100;

    if (rem >= 11 && rem <= 13) {
        return `${n}th`;
    }

    return `${n}${['th', 'st', 'nd', 'rd'][n % 10] ?? 'th'}`;
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <div className="mt-1 text-sm font-medium">{children}</div>
        </div>
    );
}

export default function MyUnit({ tenancy }: { tenancy: MyTenancy | null }) {
    if (!tenancy) {
        return (
            <>
                <Head title="My unit" />
                <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                    <Heading title="My unit" description="Your rental home" />
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                        <Home className="size-8 text-muted-foreground" />
                        <p className="font-medium">No active tenancy</p>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            You are not renting a unit right now. If this looks
                            wrong, ask your landlord to check your tenancy.
                        </p>
                    </div>
                </div>
            </>
        );
    }

    const { unit, property, landlord } = tenancy;

    return (
        <>
            <Head title="My unit" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`${property.name} · ${unit.code}`}
                        description={property.address}
                    />
                    <Button variant="outline" asChild>
                        {/* Plain link: a file download, not an Inertia visit */}
                        <a href={TenancyController.agreement.url(tenancy.id)}>
                            <FileDown /> Agreement PDF
                        </a>
                    </Button>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="space-y-4 rounded-xl border p-4">
                        <h2 className="font-semibold">Your rental</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <Fact label="Monthly rent">
                                {formatRM(tenancy.monthly_rent)}
                            </Fact>
                            <Fact label="Rent due">
                                {ordinal(tenancy.due_day)} of every month
                            </Fact>
                            <Fact label="Deposit">
                                <span className="flex flex-wrap items-center gap-2">
                                    {formatRM(tenancy.deposit_amount)}
                                    <StatusBadge
                                        status={tenancy.deposit_status}
                                    />
                                </span>
                            </Fact>
                            <Fact label="Tenancy">
                                <StatusBadge status={tenancy.status} />
                            </Fact>
                            <Fact label="Lease starts">
                                {formatDate(tenancy.start_date)}
                            </Fact>
                            <Fact label="Lease ends">
                                {tenancy.end_date
                                    ? formatDate(tenancy.end_date)
                                    : 'No end date'}
                            </Fact>
                            <Fact label="Property type">{property.type}</Fact>
                            <Fact label="Unit type">{unit.type}</Fact>
                        </div>
                    </section>

                    <section className="space-y-4 rounded-xl border p-4">
                        <h2 className="font-semibold">Your landlord</h2>
                        <p className="text-sm font-medium">{landlord.name}</p>
                        <div className="space-y-2 text-sm">
                            <a
                                href={`mailto:${landlord.email}`}
                                className="flex items-center gap-2 hover:underline"
                            >
                                <Mail className="size-4 text-muted-foreground" />
                                {landlord.email}
                            </a>
                            {landlord.phone && (
                                <a
                                    href={`tel:${landlord.phone}`}
                                    className="flex items-center gap-2 hover:underline"
                                >
                                    <Phone className="size-4 text-muted-foreground" />
                                    {landlord.phone}
                                </a>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

MyUnit.layout = {
    breadcrumbs: [{ title: 'My unit', href: MyUnitController() }],
};
