import { Head, Link, router } from '@inertiajs/react';
import { DoorOpen, MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import UnitController from '@/actions/App/Http/Controllers/UnitController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type {
    Option,
    PropertyFormData,
    UnitRow,
} from '@/components/properties/types';
import { UnitDialog } from '@/components/properties/unit-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { MetersSection } from '@/components/utilities/meters-section';
import type { Meter, MeterScan } from '@/components/utilities/types';
import { formatRM } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    property: PropertyFormData & { type_label: string };
    units: UnitRow[];
    unitTypes: Option[];
    meters: Meter[];
    utilityTypes: Option[];
    aiEnabled: boolean;
    pendingScans: Record<string, MeterScan>;
};

const statusStyle: Record<UnitRow['status'], string> = {
    vacant: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    occupied:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    maintenance:
        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
};

export default function PropertiesShow({
    property,
    units,
    unitTypes,
    meters,
    utilityTypes,
    aiEnabled,
    pendingScans,
}: Props) {
    const occupied = units.filter((u) => u.status === 'occupied');
    const monthlyIncome = occupied.reduce(
        (sum, u) => sum + Number(u.monthly_rent),
        0,
    );
    const potential = units.reduce((sum, u) => sum + Number(u.monthly_rent), 0);

    const stats = [
        { label: 'Units', value: units.length },
        { label: 'Occupied', value: occupied.length },
        {
            label: 'Vacant',
            value: units.filter((u) => u.status === 'vacant').length,
        },
        {
            label: 'Rent / month',
            value: `${formatRM(monthlyIncome)} of ${formatRM(potential)}`,
        },
    ];

    return (
        <>
            <Head title={property.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {property.name}
                            </h1>
                            <Badge variant="secondary">
                                {property.type_label}
                            </Badge>
                        </div>
                        <p className="flex items-center gap-1 text-sm text-muted-foreground">
                            <MapPin className="size-3.5" />
                            {property.address}, {property.postcode}{' '}
                            {property.city}, {property.state}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={PropertyController.edit(property.id)}>
                                <Pencil /> Edit
                            </Link>
                        </Button>
                        <ConfirmDialog
                            trigger={
                                <Button variant="outline">
                                    <Trash2 /> Delete
                                </Button>
                            }
                            title="Delete this property?"
                            description="Only properties without any tenancy history can be deleted. This cannot be undone."
                            onConfirm={() =>
                                router.delete(
                                    PropertyController.destroy.url(property.id),
                                )
                            }
                        />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {stats.map((s) => (
                        <div key={s.label} className="rounded-xl border p-4">
                            <p className="text-xs text-muted-foreground">
                                {s.label}
                            </p>
                            <p className="mt-1 text-lg font-semibold">
                                {s.value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="rounded-xl border">
                    <div className="flex items-center justify-between border-b p-4">
                        <h2 className="font-semibold">Units</h2>
                        <UnitDialog
                            propertyId={property.id}
                            unitTypes={unitTypes}
                            trigger={
                                <Button size="sm">
                                    <Plus /> Add unit
                                </Button>
                            }
                        />
                    </div>

                    {units.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 p-10 text-center text-sm text-muted-foreground">
                            <DoorOpen className="size-8" />
                            No units yet. Add rooms, beds or the whole house.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground">
                                    <tr className="border-b">
                                        <th className="px-4 py-2 font-medium">
                                            Code
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Type
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Rent
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Deposit
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Tenant
                                        </th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {units.map((u) => (
                                        <tr
                                            key={u.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {u.code}
                                            </td>
                                            <td className="px-4 py-3">
                                                {u.type_label}
                                            </td>
                                            <td className="px-4 py-3">
                                                {formatRM(u.monthly_rent)}
                                            </td>
                                            <td className="px-4 py-3">
                                                {formatRM(u.deposit)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={cn(
                                                        'rounded-md px-2 py-0.5 text-xs font-medium capitalize',
                                                        statusStyle[u.status],
                                                    )}
                                                >
                                                    {u.status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {u.tenant ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <UnitDialog
                                                        propertyId={property.id}
                                                        unitTypes={unitTypes}
                                                        unit={u}
                                                        trigger={
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                aria-label={`Edit ${u.code}`}
                                                            >
                                                                <Pencil />
                                                            </Button>
                                                        }
                                                    />
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                aria-label={`Delete ${u.code}`}
                                                                disabled={
                                                                    u.status ===
                                                                    'occupied'
                                                                }
                                                            >
                                                                <Trash2 />
                                                            </Button>
                                                        }
                                                        title={`Delete unit ${u.code}?`}
                                                        description="Units with tenancy records cannot be deleted."
                                                        onConfirm={() =>
                                                            router.delete(
                                                                UnitController.destroy.url(
                                                                    u.id,
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <MetersSection
                    propertyId={property.id}
                    units={units}
                    meters={meters}
                    utilityTypes={utilityTypes}
                    aiEnabled={aiEnabled}
                    pendingScans={pendingScans}
                />
            </div>
        </>
    );
}

PropertiesShow.layout = {
    breadcrumbs: [{ title: 'Properties', href: PropertyController.index() }],
};
