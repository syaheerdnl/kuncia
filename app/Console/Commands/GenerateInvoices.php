<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate {--month= : Month to bill, YYYY-MM (default: current month)}';

    protected $description = 'Create monthly rent invoices for all active tenancies';

    public function handle(InvoiceService $invoices): int
    {
        $month = $this->option('month');
        $period = is_string($month) && $month !== ''
            ? CarbonImmutable::createFromFormat('Y-m', $month)?->startOfMonth()
            : now()->startOfMonth();

        if (! $period) {
            $this->error('Use --month=YYYY-MM');

            return self::FAILURE;
        }

        $count = $invoices->generateMonth($period);
        $this->info("Created {$count} invoice(s) for {$period->format('F Y')}.");

        return self::SUCCESS;
    }
}
