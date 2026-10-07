import { Link, router } from '@inertiajs/react';
import {
    Droplets,
    Gauge,
    Paperclip,
    Pencil,
    Plus,
    Receipt,
    Trash2,
    Waves,
    Wifi,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import UtilityBillController from '@/actions/App/Http/Controllers/UtilityBillController';
import UtilityMeterController from '@/actions/App/Http/Controllers/UtilityMeterController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { Option, UnitRow } from '@/components/properties/types';
import {
    AddBillDialog,
    MeterScanCard,
    ScanMeterBillButton,
} from '@/components/utilities/bill-tools';
import { MeterDialog } from '@/components/utilities/meter-dialog';
import type {
    Meter,
    MeterBill,
    MeterScan,
    ShareState,
} from '@/components/utilities/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatRM } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    propertyId: number;
    units: UnitRow[];
    meters: Meter[];
    utilityTypes: Option[];
    aiEnabled: boolean;
    pendingScans: Record<string, MeterScan>;
};

const icons: Record<string, LucideIcon> = {
    electricity: Zap,
    water: Droplets,
    sewerage: Waves,
    internet: Wifi,
    other: Receipt,
};

const shareStyle: Record<ShareState, string> = {
    billed: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
    waiting:
        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
};

const shareLabel: Record<ShareState, string> = {
    billed: 'On invoice',
    waiting: 'Next invoice',
    paid: 'Paid',
};

/** Meters on a property: enter each bill once, it is split between tenants. */
export function MetersSection({
    propertyId,
    units,
    meters,
    utilityTypes,
    aiEnabled,
    pendingScans,
}: Props) {
    return (
        <div className="rounded-xl border">
            <div className="flex items-center justify-between gap-4 border-b p-4">
                <div>
                    <h2 className="font-semibold">Utility bills</h2>
                    <p className="text-xs text-muted-foreground">
                        Enter a TNB or water bill once. It is split equally
                        between the occupied units on that meter.
                    </p>
                </div>
                <MeterDialog
                    propertyId={propertyId}
                    units={units}
                    utilityTypes={utilityTypes}
                    trigger={
                        <Button size="sm" disabled={units.length === 0}>
                            <Plus /> Add meter
                        </Button>
                    }
                />
            </div>

            {meters.length === 0 ? (
                <div className="flex flex-col items-center gap-2 p-10 text-center text-sm text-muted-foreground">
                    <Gauge className="size-8" />
                    No meters yet. Add one per bill you receive, e.g. "TNB -
                    Floor 1" and "TNB - Floor 2".
                </div>
            ) : (
                <div className="divide-y">
                    {meters.map((m) => (
                        <MeterBlock
                            key={m.id}
                            meter={m}
                            propertyId={propertyId}
                            units={units}
                            utilityTypes={utilityTypes}
                            aiEnabled={aiEnabled}
                            scan={pendingScans[String(m.id)]}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function MeterBlock({
    meter,
    propertyId,
    units,
    utilityTypes,
    aiEnabled,
    scan,
}: {
    meter: Meter;
    propertyId: number;
    units: UnitRow[];
    utilityTypes: Option[];
    aiEnabled: boolean;
    scan?: MeterScan;
}) {
    const Icon = icons[meter.type] ?? Receipt;

    return (
        <div className="space-y-3 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    <div className="rounded-lg bg-muted p-2">
                        <Icon className="size-4" />
                    </div>
                    <div className="space-y-1">
                        <p className="font-medium">{meter.label}</p>
                        <p className="text-xs text-muted-foreground">
                            {meter.type_label}
                            {meter.account_no && ` · Acc ${meter.account_no}`}
                        </p>
                        <div className="flex flex-wrap gap-1">
                            {meter.unit_codes.map((c) => (
                                <Badge key={c} variant="secondary">
                                    {c}
                                </Badge>
                            ))}
                        </div>
                    </div>
                </div>
                <div className="flex flex-wrap gap-1">
                    <AddBillDialog
                        meterId={meter.id}
                        meterLabel={meter.label}
                    />
                    <ScanMeterBillButton
                        meterId={meter.id}
                        enabled={aiEnabled}
                    />
                    <MeterDialog
                        propertyId={propertyId}
                        units={units}
                        utilityTypes={utilityTypes}
                        meter={meter}
                        trigger={
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label={`Edit ${meter.label}`}
                            >
                                <Pencil />
                            </Button>
                        }
                    />
                    <ConfirmDialog
                        trigger={
                            <Button
                                size="icon"
                                variant="ghost"
                                aria-label={`Delete ${meter.label}`}
                                disabled={meter.bills.length > 0}
                            >
                                <Trash2 />
                            </Button>
                        }
                        title={`Delete ${meter.label}?`}
                        description="Only meters without bills can be deleted."
                        onConfirm={() =>
                            router.delete(
                                UtilityMeterController.destroy.url(meter.id),
                                { preserveScroll: true },
                            )
                        }
                    />
                </div>
            </div>

            {scan && <MeterScanCard meterId={meter.id} scan={scan} />}

            {meter.bills.length > 0 && (
                <ul className="space-y-2">
                    {meter.bills.map((b) => (
                        <BillRow key={b.id} bill={b} />
                    ))}
                </ul>
            )}
        </div>
    );
}

function BillRow({ bill }: { bill: MeterBill }) {
    const removable = bill.shares.every((s) => s.state !== 'paid');

    return (
        <li className="rounded-lg border bg-muted/30 p-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span className="font-medium">{bill.period}</span>
                    <span>{formatRM(bill.amount)}</span>
                    {bill.files.map((f) => (
                        <a
                            key={f.url}
                            href={f.url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-2 hover:underline"
                        >
                            <Paperclip className="size-3" /> {f.name}
                        </a>
                    ))}
                </div>
                <ConfirmDialog
                    trigger={
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-7"
                            aria-label={`Remove ${bill.period} bill`}
                            disabled={!removable}
                            title={
                                removable
                                    ? 'Remove bill'
                                    : 'A tenant already paid this bill'
                            }
                        >
                            <Trash2 />
                        </Button>
                    }
                    title={`Remove the ${bill.period} bill?`}
                    description="Its lines are taken off the tenants' unpaid invoices."
                    confirmLabel="Remove"
                    onConfirm={() =>
                        router.delete(
                            UtilityBillController.destroy.url(bill.id),
                            { preserveScroll: true },
                        )
                    }
                />
            </div>

            {bill.shares.length === 0 ? (
                <p className="mt-1 text-xs text-muted-foreground">
                    No unit was occupied that month. Nobody was charged.
                </p>
            ) : (
                <ul className="mt-2 grid max-w-xl gap-1">
                    {bill.shares.map((s) => (
                        <li
                            key={s.id}
                            className="flex items-center justify-between gap-2 text-xs"
                        >
                            <span className="truncate">
                                <span className="font-medium">{s.unit}</span>{' '}
                                {s.tenant} · {formatRM(s.amount)}
                            </span>
                            {s.invoice_id ? (
                                <Link
                                    href={InvoiceController.show(s.invoice_id)}
                                    className={cn(
                                        'shrink-0 rounded-md px-2 py-0.5 font-medium',
                                        shareStyle[s.state],
                                    )}
                                >
                                    {shareLabel[s.state]}
                                </Link>
                            ) : (
                                <span
                                    className={cn(
                                        'shrink-0 rounded-md px-2 py-0.5 font-medium',
                                        shareStyle[s.state],
                                    )}
                                >
                                    {shareLabel[s.state]}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}
