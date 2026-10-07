import { Head, Link } from '@inertiajs/react';
import { Building2, MapPin, Plus } from 'lucide-react';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type PropertyCard = {
    id: number;
    name: string;
    type: string;
    city: string;
    state: string;
    cover_url: string | null;
    units_count: number;
    occupied_count: number;
};

export default function PropertiesIndex({
    properties,
}: {
    properties: PropertyCard[];
}) {
    return (
        <>
            <Head title="Properties" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Properties"
                        description="Your buildings, houses and hostels"
                    />
                    <Button asChild>
                        <Link href={PropertyController.create()}>
                            <Plus /> Add property
                        </Link>
                    </Button>
                </div>

                {properties.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <Building2 className="size-10 text-muted-foreground" />
                        <p className="font-medium">No properties yet</p>
                        <p className="text-sm text-muted-foreground">
                            Add your first property to start managing units and
                            tenants.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {properties.map((p) => {
                            const pct = p.units_count
                                ? Math.round(
                                      (p.occupied_count / p.units_count) * 100,
                                  )
                                : 0;

                            return (
                                <Link
                                    key={p.id}
                                    href={PropertyController.show(p.id)}
                                    className="group overflow-hidden rounded-xl border transition hover:shadow-md"
                                >
                                    <div className="flex h-36 items-center justify-center bg-muted">
                                        {p.cover_url ? (
                                            <img
                                                src={p.cover_url}
                                                alt=""
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <Building2 className="size-10 text-muted-foreground" />
                                        )}
                                    </div>
                                    <div className="space-y-3 p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <h3 className="font-semibold group-hover:underline">
                                                {p.name}
                                            </h3>
                                            <Badge variant="secondary">
                                                {p.type}
                                            </Badge>
                                        </div>
                                        <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                            <MapPin className="size-3.5" />{' '}
                                            {p.city}, {p.state}
                                        </p>
                                        <div className="space-y-1">
                                            <div className="flex justify-between text-xs text-muted-foreground">
                                                <span>
                                                    {p.occupied_count}/
                                                    {p.units_count} units
                                                    occupied
                                                </span>
                                                <span>{pct}%</span>
                                            </div>
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-emerald-500"
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

PropertiesIndex.layout = {
    breadcrumbs: [{ title: 'Properties', href: PropertyController.index() }],
};
