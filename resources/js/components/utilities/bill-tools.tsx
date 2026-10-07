import { Form, router } from '@inertiajs/react';
import { Plus, ScanLine, Sparkles, X } from 'lucide-react';
import { useRef, useState } from 'react';
import UtilityBillController from '@/actions/App/Http/Controllers/UtilityBillController';
import InputError from '@/components/input-error';
import type { MeterScan } from '@/components/utilities/types';
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

/** Last month as YYYY-MM: bills usually arrive the month after. */
function lastMonth(): string {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() - 1);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

/** Type in a bill by hand, with an optional photo / PDF as proof. */
export function AddBillDialog({
    meterId,
    meterLabel,
}: {
    meterId: number;
    meterLabel: string;
}) {
    const [open, setOpen] = useState(false);
    const id = `bill-${meterId}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Plus /> Add bill
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add bill · {meterLabel}</DialogTitle>
                    <DialogDescription>
                        Split equally between units that were occupied that
                        month and added to each tenant&apos;s invoice.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...UtilityBillController.store.form(meterId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-period`}>
                                        Bill month
                                    </Label>
                                    <Input
                                        id={`${id}-period`}
                                        name="period"
                                        type="month"
                                        defaultValue={lastMonth()}
                                        required
                                    />
                                    <InputError message={errors.period} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`${id}-amount`}>
                                        Amount (RM)
                                    </Label>
                                    <Input
                                        id={`${id}-amount`}
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${id}-file`}>
                                    Bill photo or PDF (optional)
                                </Label>
                                <Input
                                    id={`${id}-file`}
                                    name="bill"
                                    type="file"
                                    accept="image/*,application/pdf"
                                />
                                <InputError message={errors.bill} />
                            </div>
                            <Button disabled={processing} className="w-full">
                                Save and split
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Upload a bill and let Gemini read the month and amount. */
export function ScanMeterBillButton({
    meterId,
    enabled,
}: {
    meterId: number;
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
            UtilityBillController.scan.url(meterId),
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
                size="sm"
                variant="outline"
                disabled={!enabled || busy}
                title={
                    enabled
                        ? 'Upload the bill and let AI read it'
                        : 'Add GEMINI_API_KEY to .env to enable'
                }
                onClick={() => input.current?.click()}
            >
                <ScanLine /> {busy ? 'Reading…' : 'Scan bill'}
            </Button>
        </>
    );
}

/** What the AI read; the landlord checks it before the bill is split. */
export function MeterScanCard({
    meterId,
    scan,
}: {
    meterId: number;
    scan: MeterScan;
}) {
    const id = `scan-${meterId}`;
    const facts = [
        ['File', scan.file_name],
        ['Provider', scan.provider],
        ['Account no.', scan.account_no],
        [
            'Usage',
            scan.usage !== null
                ? `${scan.usage} ${scan.usage_unit ?? ''}`
                : null,
        ],
        ['Amount on bill', formatRM(scan.amount)],
    ].filter(([, v]) => v);

    return (
        <div className="rounded-lg border border-violet-200 bg-violet-50 p-4 dark:border-violet-900 dark:bg-violet-950/30">
            <div className="mb-3 flex items-start justify-between gap-2">
                <div className="flex items-center gap-2 text-sm font-semibold">
                    <Sparkles className="size-4 text-violet-600" /> AI read this
                    bill. Check before saving
                </div>
                <Button
                    size="icon"
                    variant="ghost"
                    aria-label="Dismiss"
                    onClick={() =>
                        router.delete(
                            UtilityBillController.dismiss.url(meterId),
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
                        <dd className="truncate font-medium">{v}</dd>
                    </div>
                ))}
            </dl>

            <Form
                {...UtilityBillController.store.form(meterId)}
                options={{ preserveScroll: true }}
                className="grid gap-3 sm:grid-cols-[10rem_10rem_auto] sm:items-end"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="use_scan" value="1" />
                        <div className="grid gap-1">
                            <Label htmlFor={`${id}-period`}>Bill month</Label>
                            <Input
                                id={`${id}-period`}
                                name="period"
                                type="month"
                                defaultValue={scan.period ?? lastMonth()}
                                required
                            />
                            <InputError message={errors.period} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor={`${id}-amount`}>Amount (RM)</Label>
                            <Input
                                id={`${id}-amount`}
                                name="amount"
                                type="number"
                                step="0.01"
                                defaultValue={scan.amount}
                                required
                            />
                            <InputError message={errors.amount} />
                        </div>
                        <Button disabled={processing}>Save and split</Button>
                    </>
                )}
            </Form>
        </div>
    );
}
