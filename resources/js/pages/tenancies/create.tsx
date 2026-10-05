import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import TenancyController from '@/actions/App/Http/Controllers/TenancyController';
import TenantController from '@/actions/App/Http/Controllers/TenantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type UnitOption = { id: number; code: string; monthly_rent: string; deposit: string };
type Props = {
    tenants: { id: number; name: string; email: string }[];
    properties: { id: number; name: string; units: UnitOption[] }[];
    preselectTenant: number | null;
};

export default function TenanciesCreate({ tenants, properties, preselectTenant }: Props) {
    const units = properties.flatMap((p) => p.units);
    const [unitId, setUnitId] = useState('');
    const [rent, setRent] = useState('');
    const [deposit, setDeposit] = useState('');

    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth() + 1, 1).toISOString().slice(0, 10);

    const pickUnit = (value: string) => {
        setUnitId(value);
        const unit = units.find((u) => String(u.id) === value);

        if (unit) {
            setRent(unit.monthly_rent);
            setDeposit(unit.deposit);
        }
    };

    const nothingToRent = tenants.length === 0 || units.length === 0;

    return (
        <>
            <Head title="New tenancy" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Heading title="New tenancy" description="Move a tenant into a vacant unit." />

                {nothingToRent ? (
                    <div className="max-w-2xl rounded-xl border border-dashed p-8 text-sm text-muted-foreground">
                        {tenants.length === 0 && <p>All your tenants already have an active tenancy, or you have none. Add a tenant first.</p>}
                        {units.length === 0 && <p>You have no vacant units right now.</p>}
                    </div>
                ) : (
                    <Form {...TenancyController.store.form()} className="max-w-2xl space-y-6">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label>Tenant</Label>
                                    <Select name="tenant_id" defaultValue={preselectTenant ? String(preselectTenant) : undefined}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose tenant" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {tenants.map((t) => (
                                                <SelectItem key={t.id} value={String(t.id)}>
                                                    {t.name} · {t.email}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.tenant_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Vacant unit</Label>
                                    <Select name="unit_id" value={unitId} onValueChange={pickUnit}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Choose unit" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {properties.map((p) => (
                                                <SelectGroup key={p.id}>
                                                    <SelectLabel>{p.name}</SelectLabel>
                                                    {p.units.map((u) => (
                                                        <SelectItem key={u.id} value={String(u.id)}>
                                                            {u.code} · RM {u.monthly_rent}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.unit_id} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="start_date">Start date</Label>
                                        <Input id="start_date" name="start_date" type="date" defaultValue={start} required />
                                        <InputError message={errors.start_date} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="end_date">End date (optional)</Label>
                                        <Input id="end_date" name="end_date" type="date" />
                                        <InputError message={errors.end_date} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="monthly_rent">Monthly rent (RM)</Label>
                                        <Input id="monthly_rent" name="monthly_rent" type="number" step="0.01" value={rent} onChange={(e) => setRent(e.target.value)} required />
                                        <InputError message={errors.monthly_rent} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="deposit_amount">Deposit (RM)</Label>
                                        <Input id="deposit_amount" name="deposit_amount" type="number" step="0.01" value={deposit} onChange={(e) => setDeposit(e.target.value)} required />
                                        <InputError message={errors.deposit_amount} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="due_day">Rent due day (1–28)</Label>
                                        <Input id="due_day" name="due_day" type="number" min={1} max={28} defaultValue={7} required />
                                        <InputError message={errors.due_day} />
                                    </div>
                                </div>

                                <Button disabled={processing}>Start tenancy</Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

TenanciesCreate.layout = {
    breadcrumbs: [
        { title: 'Tenants', href: TenantController.index() },
        { title: 'New tenancy', href: TenancyController.create() },
    ],
};
