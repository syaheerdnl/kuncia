import { Form, router } from '@inertiajs/react';
import { Plus, ScanLine, Sparkles, X } from 'lucide-react';
import { useRef, useState } from 'react';
import InvoiceChargeController from '@/actions/App/Http/Controllers/InvoiceChargeController';
import InputError from '@/components/input-error';
import type { BillScan } from '@/components/invoices/types';
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
import { formatRM } from '@/lib/format';

/** Manual "Add charge" dialog (fallback when no bill / AI). */
export function AddChargeDialog({ invoiceId }: { invoiceId: number }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Plus /> Add charge
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add charge</DialogTitle>
                    <DialogDescription>
                        e.g. Electricity (TNB) Sep 2026, Water (SAMB), cleaning
                        fee.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...InvoiceChargeController.store.form(invoiceId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Input
                                    id="description"
                                    name="description"
                                    required
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="amount">Amount (RM)</Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>
                            <Button disabled={processing} className="w-full">
                                Add charge
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Upload a bill photo/PDF; the server asks Gemini to read it. */
export function ScanBillButton({
    invoiceId,
    enabled,
}: {
    invoiceId: number;
    enabled: boolean;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);

    const upload = (file: File | undefined) => {
        if (!file) {
            return;
        }

        setBusy(true);
        router.post(
            InvoiceChargeController.scan.url(invoiceId),
            { bill: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    return (
        <>
            <input
                ref={input}
                type="file"
                accept="image/*,application/pdf"
                className="hidden"
                onChange={(e) => upload(e.target.files?.[0])}
            />
            <Button
                variant="outline"
                disabled={!enabled || busy}
                title={
                    enabled
                        ? 'Upload a TNB / water bill and let AI read it'
                        : 'Add GEMINI_API_KEY to .env to enable'
                }
                onClick={() => input.current?.click()}
            >
                <ScanLine /> {busy ? 'Reading bill…' : 'Scan bill (AI)'}
            </Button>
        </>
    );
}

/** Shows what Gemini read; landlord edits/confirms before it is charged. */
export function ScanResultCard({
    invoiceId,
    scan,
}: {
    invoiceId: number;
    scan: BillScan;
}) {
    const facts = [
        ['Provider', scan.provider],
        ['Account no.', scan.account_no],
        ['Period', scan.period],
        [
            'Usage',
            scan.usage !== null
                ? `${scan.usage} ${scan.usage_unit ?? ''}`
                : null,
        ],
        ['Due date', scan.due_date],
        ['Amount on bill', formatRM(scan.amount)],
    ].filter(([, v]) => v);

    return (
        <div className="rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-900 dark:bg-violet-950/30">
            <div className="mb-3 flex items-start justify-between gap-2">
                <div className="flex items-center gap-2 font-semibold">
                    <Sparkles className="size-4 text-violet-600" /> AI read this
                    bill. Please check before adding
                </div>
                <Button
                    size="icon"
                    variant="ghost"
                    aria-label="Dismiss"
                    onClick={() =>
                        router.delete(
                            InvoiceChargeController.dismiss.url(invoiceId),
                            { preserveScroll: true },
                        )
                    }
                >
                    <X />
                </Button>
            </div>

            <dl className="mb-4 grid grid-cols-2 gap-2 text-sm md:grid-cols-3">
                {facts.map(([k, v]) => (
                    <div key={k}>
                        <dt className="text-xs text-muted-foreground">{k}</dt>
                        <dd className="font-medium">{v}</dd>
                    </div>
                ))}
            </dl>

            <Form
                {...InvoiceChargeController.store.form(invoiceId)}
                options={{ preserveScroll: true }}
                className="grid gap-3 sm:grid-cols-[1fr_10rem_auto] sm:items-end"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1">
                            <Label htmlFor="scan-description">
                                Description
                            </Label>
                            <Input
                                id="scan-description"
                                name="description"
                                defaultValue={scan.suggested_description}
                                required
                            />
                            <InputError message={errors.description} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="scan-amount">Amount (RM)</Label>
                            <Input
                                id="scan-amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                defaultValue={scan.amount}
                                required
                            />
                            <InputError message={errors.amount} />
                        </div>
                        <Button disabled={processing}>Add to invoice</Button>
                    </>
                )}
            </Form>
        </div>
    );
}
