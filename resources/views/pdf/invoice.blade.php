@php
    /** @var \App\Models\Invoice $invoice */
    $tenancy = $invoice->tenancy;
    $property = $tenancy->unit->property;
    $rm = fn ($v) => 'RM '.number_format((float) $v, 2);
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .top { width: 100%; margin-bottom: 24px; }
        .top td { vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; }
        .muted { color: #666; }
        .right { text-align: right; }
        .status { display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 11px; }
        .paid { background: #d1fae5; color: #065f46; }
        .unpaid { background: #fef3c7; color: #92400e; }
        .overdue { background: #fee2e2; color: #991b1b; }
        .void { background: #eee; color: #555; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th { text-align: left; border-bottom: 2px solid #111; padding: 6px; }
        table.items td { border-bottom: 1px solid #ddd; padding: 6px; }
        .total td { font-weight: bold; border-bottom: none; }
    </style>
</head>
<body>
    <table class="top">
        <tr>
            <td>
                <div class="brand">Kuncia</div>
                <div class="muted">{{ $property->owner->name }}<br>{{ $property->owner->email }}</div>
            </td>
            <td class="right">
                <div style="font-size:16px;font-weight:bold">INVOICE</div>
                <div>{{ $invoice->invoice_no }}</div>
                <div class="status {{ $invoice->status->value }}">{{ $invoice->status->label() }}</div>
            </td>
        </tr>
    </table>

    <table class="top">
        <tr>
            <td>
                <div class="muted">Bill to</div>
                <strong>{{ $tenancy->tenant->name }}</strong><br>
                {{ $tenancy->tenant->email }}<br>
                {{ $property->name }} · Unit {{ $tenancy->unit->code }}<br>
                {{ $property->address }}, {{ $property->postcode }} {{ $property->city }}
            </td>
            <td class="right">
                <div><span class="muted">Period:</span> {{ $invoice->period->format('F Y') }}</div>
                <div><span class="muted">Issued:</span> {{ $invoice->issue_date->format('d M Y') }}</div>
                <div><span class="muted">Due:</span> {{ $invoice->due_date->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th>Description</th><th class="right">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr><td>{{ $item->description }}</td><td class="right">{{ $rm($item->amount) }}</td></tr>
            @endforeach
            <tr class="total"><td class="right">Total</td><td class="right">{{ $rm($invoice->total) }}</td></tr>
            <tr class="total"><td class="right">Balance due</td><td class="right">{{ $rm($outstanding) }}</td></tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top:32px">Pay online through your Kuncia tenant account. Thank you.</p>
</body>
</html>
