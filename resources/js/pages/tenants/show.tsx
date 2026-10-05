import { Head, Link } from '@inertiajs/react';
import { CalendarDays, FileDown, KeyRound, Mail, Phone } from 'lucide-react';
import TenancyController from '@/actions/App/Http/Controllers/TenancyController';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import { StatusBadge } from '@/components/status-badge';
import { EndTenancyDialog } from '@/components/tenants/end-tenancy-dialog';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';

type TenancyRow = {
    id: number;
    property: string;
    unit: string;
    start_date: string;
    end_date: string | null;
    monthly_rent: string;
    deposit_amount: string;
    deposit_status: string;
    due_day: number;
    status: string;
};

type InvoiceRow = { id: number; invoice_no: string; period: string; due_date: string; total: string; status: string };

type Props = {
    tenant: { id: number; name: string; email: string; phone: string | null; joined: string | null };
    tenancies: TenancyRow[];
    invoices: InvoiceRow[];
};

export default function TenantsShow({ tenant, tenancies, invoices }: Props) {
    const active = tenancies.find((t) => t.status === 'active');
    const history = tenancies.filter((t) => t.status !== 'active');

    return (
        <>
            <Head title={tenant.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-xl font-semibold">{tenant.name}</h1>
                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span className="flex items-center gap-1"><Mail className="size-3.5" /> {tenant.email}</span>
                        {tenant.phone && <span className="flex items-center gap-1"><Phone className="size-3.5" /> {tenant.phone}</span>}
                        {tenant.joined && <span className="flex items-center gap-1"><CalendarDays className="size-3.5" /> Added {tenant.joined}</span>}
                    </div>
                </div>

                <div className="rounded-xl border p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="font-semibold">Current tenancy</h2>
                        {active ? (
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" asChild>
                                    {/* Plain link: a file download, not an Inertia visit */}
                                    <a href={TenancyController.agreement.url(active.id)}>
                                        <FileDown /> Agreement PDF
                                    </a>
                                </Button>
                                <EndTenancyDialog tenancyId={active.id} unitLabel={`${active.property} · ${active.unit}`} />
                            </div>
                        ) : (
                            <Button size="sm" asChild>
                                <Link href={TenancyController.create({ query: { tenant: tenant.id } })}>
                                    <KeyRound /> Start tenancy
                                </Link>
                            </Button>
                        )}
                    </div>
                    {active ? (
                        <dl className="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                            <div><dt className="text-xs text-muted-foreground">Unit</dt><dd className="font-medium">{active.property} · {active.unit}</dd></div>
                            <div><dt className="text-xs text-muted-foreground">Period</dt><dd>{active.start_date} → {active.end_date ?? 'open'}</dd></div>
                            <div><dt className="text-xs text-muted-foreground">Rent</dt><dd>{formatRM(active.monthly_rent)} (due day {active.due_day})</dd></div>
                            <div><dt className="text-xs text-muted-foreground">Deposit</dt><dd>{formatRM(active.deposit_amount)} · {active.deposit_status}</dd></div>
                        </dl>
                    ) : (
                        <p className="text-sm text-muted-foreground">This tenant is not renting any unit right now.</p>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border">
                        <h2 className="border-b p-4 font-semibold">Recent invoices</h2>
                        {invoices.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">No invoices yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <tbody>
                                    {invoices.map((i) => (
                                        <tr key={i.id} className="border-b last:border-0">
                                            <td className="px-4 py-2">
                                                <div className="font-medium">{i.invoice_no}</div>
                                                <div className="text-xs text-muted-foreground">{i.period} · due {i.due_date}</div>
                                            </td>
                                            <td className="px-4 py-2 text-right">{formatRM(i.total)}</td>
                                            <td className="px-4 py-2 text-right"><StatusBadge status={i.status} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    <div className="rounded-xl border">
                        <h2 className="border-b p-4 font-semibold">Tenancy history</h2>
                        {history.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">No past tenancies.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <tbody>
                                    {history.map((t) => (
                                        <tr key={t.id} className="border-b last:border-0">
                                            <td className="px-4 py-2">
                                                <div className="font-medium">{t.property} · {t.unit}</div>
                                                <div className="text-xs text-muted-foreground">{t.start_date} → {t.end_date}</div>
                                            </td>
                                            <td className="px-4 py-2 text-xs">Deposit {t.deposit_status.toLowerCase()}</td>
                                            <td className="px-4 py-2 text-right"><StatusBadge status={t.status} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

TenantsShow.layout = {
    breadcrumbs: [{ title: 'Tenants', href: TenantController.index() }],
};
