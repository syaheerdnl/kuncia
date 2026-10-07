import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import UnitController from '@/actions/App/Http/Controllers/UnitController';
import InputError from '@/components/input-error';
import type { Option, UnitRow } from '@/components/properties/types';
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

type Props = {
    trigger: ReactNode;
    propertyId: number;
    unitTypes: Option[];
    unit?: UnitRow;
};

/** Add (no unit) or edit (with unit) a unit in a dialog. */
export function UnitDialog({ trigger, propertyId, unitTypes, unit }: Props) {
    const [open, setOpen] = useState(false);
    const form = unit
        ? UnitController.update.form(unit.id)
        : UnitController.store.form(propertyId);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {unit ? `Edit unit ${unit.code}` : 'Add unit'}
                    </DialogTitle>
                    <DialogDescription>
                        A room, bed or whole house that can be rented out.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="code">Unit code</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        defaultValue={unit?.code}
                                        placeholder="A-101"
                                        required
                                    />
                                    <InputError message={errors.code} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Type</Label>
                                    <Select
                                        name="type"
                                        defaultValue={unit?.type ?? 'room'}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {unitTypes.map((t) => (
                                                <SelectItem
                                                    key={t.value}
                                                    value={t.value}
                                                >
                                                    {t.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.type} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="monthly_rent">
                                        Monthly rent (RM)
                                    </Label>
                                    <Input
                                        id="monthly_rent"
                                        name="monthly_rent"
                                        type="number"
                                        step="0.01"
                                        min="1"
                                        defaultValue={unit?.monthly_rent}
                                        required
                                    />
                                    <InputError message={errors.monthly_rent} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="deposit">
                                        Deposit (RM)
                                    </Label>
                                    <Input
                                        id="deposit"
                                        name="deposit"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        defaultValue={unit?.deposit ?? 0}
                                        required
                                    />
                                    <InputError message={errors.deposit} />
                                </div>
                            </div>

                            {unit && unit.status !== 'occupied' && (
                                <div className="grid gap-2">
                                    <Label>Status</Label>
                                    <Select
                                        name="status"
                                        defaultValue={unit.status}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="vacant">
                                                Vacant
                                            </SelectItem>
                                            <SelectItem value="maintenance">
                                                Under maintenance
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>
                            )}

                            <Button disabled={processing} className="w-full">
                                {unit ? 'Save unit' : 'Add unit'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
