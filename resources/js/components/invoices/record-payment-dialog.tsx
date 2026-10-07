import { Form } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import { useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
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
import { formatRM } from '@/lib/format';

export function RecordPaymentDialog({ invoiceId, outstanding }: { invoiceId: number; outstanding: string }) {
    const [open, setOpen] = useState(false);
    const today = new Date().toISOString().slice(0, 10);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Banknote /> Record payment
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record payment</DialogTitle>
                    <DialogDescription>Cash or bank transfer received. Balance due: {formatRM(outstanding)}</DialogDescription>
                </DialogHeader>
                <Form
                    {...InvoiceController.recordPayment.form(invoiceId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="amount">Amount (RM)</Label>
                                    <Input id="amount" name="amount" type="number" step="0.01" defaultValue={outstanding} required />
                                    <InputError message={errors.amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="paid_at">Date received</Label>
                                    <Input id="paid_at" name="paid_at" type="date" defaultValue={today} max={today} required />
                                    <InputError message={errors.paid_at} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label>Method</Label>
                                <Select name="method" defaultValue="transfer">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="transfer">Bank transfer</SelectItem>
                                        <SelectItem value="cash">Cash</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.method} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reference">Reference (optional)</Label>
                                <Input id="reference" name="reference" placeholder="e.g. DuitNow ref / receipt no." />
                                <InputError message={errors.reference} />
                            </div>
                            <Button disabled={processing} className="w-full">
                                Save payment
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
