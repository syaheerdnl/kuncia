<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark unpaid invoices past their due date as overdue and add the late fee';

    public function handle(InvoiceService $invoices): int
    {
        $count = $invoices->markOverdue(now());
        $this->info("Marked {$count} invoice(s) overdue.");

        return self::SUCCESS;
    }
}
