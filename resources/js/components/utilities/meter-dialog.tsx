import { Form } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import UtilityMeterController from '@/actions/App/Http/Controllers/UtilityMeterController';
import InputError from '@/components/input-error';
import type { Option, UnitRow } from '@/components/properties/types';
import type { Meter } from '@/components/utilities/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    units: UnitRow[];
    utilityTypes: Option[];
    meter?: Meter;
};

/** Add or edit a meter and pick the units that share it. */
export function MeterDialog({
    trigger,
    propertyId,
    units,
    utilityTypes,
    meter,
}: Props) {
    const [open, setOpen] = useState(false);
    const form = meter
        ? UtilityMeterController.update.form(meter.id)
        : UtilityMeterController.store.form(propertyId);
    const prefix = meter ? `meter-${meter.id}` : 'meter-new';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {meter ? `Edit ${meter.label}` : 'Add meter'}
                    </DialogTitle>
                    <DialogDescription>
                        One meter or account, e.g. TNB for Floor 1. Each bill is
                        split equally between the occupied units you tick.
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
                                    <Label>Type</Label>
                                    <Select
                                        name="type"
                                        defaultValue={
                                            meter?.type ?? 'electricity'
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {utilityTypes.map((t) => (
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
                                    <Label htmlFor={`${prefix}-account`}>
                                        Account no. (optional)
                                    </Label>
                                    <Input
                                        id={`${prefix}-account`}
                                        name="account_no"
                                        defaultValue={meter?.account_no ?? ''}
                                    />
                                    <InputError message={errors.account_no} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`${prefix}-label`}>Name</Label>
                                <Input
                                    id={`${prefix}-label`}
                                    name="label"
                                    defaultValue={meter?.label}
                                    placeholder="TNB - Floor 1"
                                    required
                                />
                                <InputError message={errors.label} />
                            </div>

                            <fieldset className="grid gap-2">
                                <legend className="mb-1 text-sm font-medium">
                                    Units on this meter
                                </legend>
                                {units.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Add units to this property first.
                                    </p>
                                ) : (
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        {units.map((u) => (
                                            <div
                                                key={u.id}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={`${prefix}-unit-${u.id}`}
                                                    name="unit_ids[]"
                                                    value={String(u.id)}
                                                    defaultChecked={
                                                        meter
                                                            ? meter.unit_ids.includes(
                                                                  u.id,
                                                              )
                                                            : true
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`${prefix}-unit-${u.id}`}
                                                    className="font-normal"
                                                >
                                                    {u.code}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                <InputError
                                    message={
                                        errors.unit_ids ?? errors['unit_ids.0']
                                    }
                                />
                            </fieldset>

                            <Button disabled={processing} className="w-full">
                                {meter ? 'Save meter' : 'Add meter'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
