import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import TenancyTransactionController from '@/actions/App/Http/Controllers/TenancyTransactionController';
import InputError from '@/components/input-error';
import type { TenancyOption } from '@/components/properties/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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

/** Record a deposit received, or money paid out to a tenant. Shows on the timeline. */
export function RecordTransactionDialog({
    propertyId,
    tenancies,
}: {
    propertyId: number;
    tenancies: TenancyOption[];
}) {
    const [open, setOpen] = useState(false);
    const today = new Date().toISOString().slice(0, 10);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus /> Record deposit or payout
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record deposit or payout</DialogTitle>
                    <DialogDescription>
                        A deposit you received, or money you paid to a tenant,
                        for example a repair they arranged. It appears on the
                        timeline.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...TenancyTransactionController.store.form(propertyId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label>Tenant</Label>
                                <Select
                                    name="tenancy_id"
                                    defaultValue={String(
                                        tenancies[0]?.id ?? '',
                                    )}
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
                                <Label>What happened</Label>
                                <Select
                                    name="type"
                                    defaultValue="deposit_received"
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="deposit_received">
                                            Deposit received
                                        </SelectItem>
                                        <SelectItem value="paid_to_tenant">
                                            Paid to tenant
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.type} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="tx-amount">
                                        Amount (RM)
                                    </Label>
                                    <Input
                                        id="tx-amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="tx-date">Date</Label>
                                    <Input
                                        id="tx-date"
                                        name="occurred_on"
                                        type="date"
                                        defaultValue={today}
                                        max={today}
                                        required
                                    />
                                    <InputError message={errors.occurred_on} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tx-note">Note (optional)</Label>
                                <Input
                                    id="tx-note"
                                    name="note"
                                    maxLength={150}
                                    placeholder="e.g. Roof repair, deposit for the house"
                                />
                                <InputError message={errors.note} />
                            </div>

                            <Button disabled={processing} className="w-full">
                                Save
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
