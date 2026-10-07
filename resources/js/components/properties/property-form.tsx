import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import type { Option, PropertyFormData } from '@/components/properties/types';
import { Button } from '@/components/ui/button';
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
    form: {
        action: string;
        method: 'get' | 'post' | 'put' | 'patch' | 'delete';
    };
    types: Option[];
    states: string[];
    property?: PropertyFormData;
    submitLabel: string;
};

export function PropertyForm({
    form,
    types,
    states,
    property,
    submitLabel,
}: Props) {
    return (
        <Form {...form} className="max-w-2xl space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Property name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={property?.name}
                            placeholder="e.g. Hostel Seri Durian Tunggal"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Type</Label>
                        <Select name="type" defaultValue={property?.type}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>
                                        {t.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">Address</Label>
                        <Input
                            id="address"
                            name="address"
                            defaultValue={property?.address}
                            placeholder="No, Jalan, Taman"
                            required
                        />
                        <InputError message={errors.address} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="city">City</Label>
                            <Input
                                id="city"
                                name="city"
                                defaultValue={property?.city}
                                required
                            />
                            <InputError message={errors.city} />
                        </div>
                        <div className="grid gap-2">
                            <Label>State</Label>
                            <Select name="state" defaultValue={property?.state}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select state" />
                                </SelectTrigger>
                                <SelectContent>
                                    {states.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.state} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="postcode">Postcode</Label>
                            <Input
                                id="postcode"
                                name="postcode"
                                inputMode="numeric"
                                maxLength={5}
                                defaultValue={property?.postcode}
                                required
                            />
                            <InputError message={errors.postcode} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">
                            Description (optional)
                        </Label>
                        <textarea
                            id="description"
                            name="description"
                            rows={4}
                            defaultValue={property?.description ?? ''}
                            className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="cover_image">
                            Cover photo (optional, max 5MB)
                        </Label>
                        {property?.cover_url && (
                            <img
                                src={property.cover_url}
                                alt=""
                                className="h-32 w-56 rounded-md object-cover"
                            />
                        )}
                        <Input
                            id="cover_image"
                            name="cover_image"
                            type="file"
                            accept="image/*"
                        />
                        <InputError message={errors.cover_image} />
                    </div>

                    <Button disabled={processing}>{submitLabel}</Button>
                </>
            )}
        </Form>
    );
}
