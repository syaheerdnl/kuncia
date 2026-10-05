import { Head, Link, router } from '@inertiajs/react';
import { KeyRound, Search, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import TenancyController from '@/actions/App/Http/Controllers/TenancyController';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import Heading from '@/components/heading';
import { AddTenantDialog } from '@/components/tenants/add-tenant-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatRM } from '@/lib/format';

type TenantRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    unit: string | null;
    outstanding: string;
};

type Props = { tenants: TenantRow[]; filters: { q: string } };

export default function TenantsIndex({ tenants, filters }: Props) {
    const [q, setQ] = useState(filters.q);

    // Debounced search
    useEffect(() => {
        if (q === filters.q) {
            return;
        }

        const t = setTimeout(() => {
            router.get(TenantController.index.url(), q ? { q } : {}, { preserveState: true, replace: true });
        }, 300);

        return () => clearTimeout(t);
    }, [q, filters.q]);

    return (
        <>
            <Head title="Tenants" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title="Tenants" description="People renting your units" />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={TenancyController.create()}>
                                <KeyRound /> New tenancy
                            </Link>
                        </Button>
                        <AddTenantDialog />
                    </div>
                </div>

                <div className="relative max-w-sm">
                    <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                    <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, email, phone" className="pl-9" />
                </div>

                {tenants.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-12 text-center text-sm text-muted-foreground">
                        <Users className="size-8" />
                        {filters.q ? 'No tenants match your search.' : 'No tenants yet. Add your first tenant.'}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs text-muted-foreground">
                                <tr className="border-b">
                                    <th className="px-4 py-2 font-medium">Name</th>
                                    <th className="px-4 py-2 font-medium">Contact</th>
                                    <th className="px-4 py-2 font-medium">Current unit</th>
                                    <th className="px-4 py-2 text-right font-medium">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tenants.map((t) => (
                                    <tr key={t.id} className="border-b last:border-0 hover:bg-muted/50">
                                        <td className="px-4 py-3 font-medium">
                                            <Link href={TenantController.show(t.id)} className="hover:underline">
                                                {t.name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            <div>{t.email}</div>
                                            <div className="text-xs">{t.phone}</div>
                                        </td>
                                        <td className="px-4 py-3">{t.unit ?? <span className="text-muted-foreground">No active tenancy</span>}</td>
                                        <td className={`px-4 py-3 text-right font-medium ${Number(t.outstanding) > 0 ? 'text-red-600 dark:text-red-400' : ''}`}>
                                            {formatRM(t.outstanding)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

TenantsIndex.layout = {
    breadcrumbs: [{ title: 'Tenants', href: TenantController.index() }],
};
