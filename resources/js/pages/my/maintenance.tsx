import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import MyMaintenanceController from '@/actions/App/Http/Controllers/Tenant/MyMaintenanceController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { TicketTable } from '@/components/maintenance/ticket-table';
import type { TicketRow } from '@/components/maintenance/types';
import type { Option } from '@/components/properties/types';
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

type Props = { tickets: TicketRow[]; unit: string | null; priorities: Option[] };

export default function MyMaintenance({ tickets, unit, priorities }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Head title="My requests" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading title="My requests" description={unit ? `Report a problem in ${unit}` : 'You have no active tenancy.'} />
                    {unit && (
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button><Plus /> Report a problem</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Report a problem</DialogTitle>
                                    <DialogDescription>{unit}</DialogDescription>
                                </DialogHeader>
                                <Form {...MyMaintenanceController.store.form()} onSuccess={() => setOpen(false)} className="space-y-4">
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="title">What's wrong?</Label>
                                                <Input id="title" name="title" placeholder="e.g. Aircond not cold" required />
                                                <InputError message={errors.title} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="description">Details</Label>
                                                <textarea id="description" name="description" rows={4} required className="rounded-md border border-input bg-transparent px-3 py-2 text-sm" />
                                                <InputError message={errors.description} />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>How urgent?</Label>
                                                <Select name="priority" defaultValue="medium">
                                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        {priorities.map((p) => <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>)}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="photos">Photos (up to 3, 2MB each)</Label>
                                                <Input id="photos" name="photos[]" type="file" accept="image/*" multiple />
                                                <InputError message={errors.photos ?? errors['photos.0'] ?? errors['photos.1'] ?? errors['photos.2']} />
                                            </div>
                                            <Button disabled={processing} className="w-full">Send request</Button>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>
                <TicketTable tickets={tickets} showTenant={false} empty="No requests yet." />
            </div>
        </>
    );
}

MyMaintenance.layout = {
    breadcrumbs: [{ title: 'My requests', href: MyMaintenanceController.index() }],
};
