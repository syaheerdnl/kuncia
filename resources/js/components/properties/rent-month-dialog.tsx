import { Form } from '@inertiajs/react';
import { useState } from 'react';
import RentMonthController from '@/actions/App/Http/Controllers/RentMonthController';
import InputError from '@/components/input-error';
import type { RentTenancy } from '@/components/properties/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRM } from '@/lib/format';

/**
 * Catch up a month whose rent was never invoiced: pick the tenant and one of their
 * missing months, and tick "already paid" to record the payment in the same step.
 * The invoice never gets a late fee.
 */
export function RentMonthDialog({
    propertyId,
    tenancies,
    open,
    onOpenChange,
    initial,
}: {
    propertyId: number;
    /** Only tenancies that have at least one missing month. */
    tenancies: RentTenancy[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initial: { tenancyId: number; month: string };
}) {
    const [tenancyId, setTenancyId] = useState(String(initial.tenancyId));
    const [month, setMonth] = useState(initial.month);
    const [paid, setPaid] = useState(false);

    const tenancy = tenancies.find((t) => String(t.id) === tenancyId);
    const today = new Date().toISOString().slice(0, 10);

    const pickTenancy = (id: string) => {
        setTenancyId(id);
        setMonth(
            tenancies.find((t) => String(t.id) === id)?.missing[0]?.month ?? '',
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a missed month</DialogTitle>
                    <DialogDescription>
                        Makes the invoice for a month that was never billed. No
                        late fee is added to it.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...RentMonthController.store.form(propertyId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="paid"
                                value={paid ? '1' : '0'}
                            />

                            <div className="grid gap-2">
                                <Label>Tenant</Label>
                                <Select
                                    name="tenancy_id"
                                    value={tenancyId}
                                    onValueChange={pickTenancy}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {tenancies.map((t) => (
                                            <SelectItem
                                                key={t.id}
                                                value={String(t.id)}
                                            >
                                                {t.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.tenancy_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Month</Label>
                                <Select
                                    name="month"
                                    value={month}
                                    onValueChange={setMonth}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pick a month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(tenancy?.missing ?? []).map((m) => (
                                            <SelectItem
                                                key={m.month}
                                                value={m.month}
                                            >
                                                {m.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Only months with no invoice are listed. The
                                    rent is{' '}
                                    {tenancy
                                        ? formatRM(tenancy.rent)
                                        : 'the tenancy rent'}
                                    .
                                </p>
                                <InputError message={errors.month} />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="rm-paid"
                                    checked={paid}
                                    onCheckedChange={(v) => setPaid(v === true)}
                                />
                                <Label htmlFor="rm-paid">Already paid</Label>
                            </div>

                            {paid && (
                                <div className="space-y-4 rounded-lg border p-3">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="rm-amount">
                                                Amount (RM)
                                            </Label>
                                            <Input
                                                id="rm-amount"
                                                name="amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                defaultValue={tenancy?.rent}
                                                required
                                            />
                                            <InputError
                                                message={errors.amount}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="rm-date">
                                                Date received
                                            </Label>
                                            <Input
                                                id="rm-date"
                                                name="paid_at"
                                                type="date"
                                                defaultValue={today}
                                                max={today}
                                                required
                                            />
                                            <InputError
                                                message={errors.paid_at}
                                            />
                                        </div>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>How it was paid</Label>
                                        <Select
                                            name="method"
                                            defaultValue="transfer"
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="transfer">
                                                    Bank transfer
                                                </SelectItem>
                                                <SelectItem value="cash">
                                                    Cash
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.method} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="rm-ref">
                                            Reference (optional)
                                        </Label>
                                        <Input
                                            id="rm-ref"
                                            name="reference"
                                            maxLength={100}
                                        />
                                    </div>
                                </div>
                            )}

                            <Button
                                disabled={processing || !month}
                                className="w-full"
                            >
                                {paid
                                    ? 'Create and mark paid'
                                    : 'Create invoice'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
