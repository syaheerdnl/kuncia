import { Form } from '@inertiajs/react';
import { useState } from 'react';
import TenancyController from '@/actions/App/Http/Controllers/TenancyController';
import InputError from '@/components/input-error';
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

export function EndTenancyDialog({ tenancyId, unitLabel }: { tenancyId: number; unitLabel: string }) {
    const [open, setOpen] = useState(false);
    const today = new Date().toISOString().slice(0, 10);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    End tenancy
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>End tenancy</DialogTitle>
                    <DialogDescription>{unitLabel} will become vacant again.</DialogDescription>
                </DialogHeader>
                <Form
                    {...TenancyController.end.form(tenancyId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="end_date">Move-out date</Label>
                                <Input id="end_date" name="end_date" type="date" defaultValue={today} required />
                                <InputError message={errors.end_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Deposit</Label>
                                <Select name="deposit_status" defaultValue="refunded">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="refunded">Refunded to tenant</SelectItem>
                                        <SelectItem value="forfeited">Forfeited (damage / unpaid rent)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.deposit_status} />
                            </div>
                            <Button variant="destructive" disabled={processing} className="w-full">
                                End tenancy
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
