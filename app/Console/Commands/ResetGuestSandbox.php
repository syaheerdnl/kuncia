<?php

namespace App\Console\Commands;

use App\Support\GuestSandbox;
use Illuminate\Console\Command;

class ResetGuestSandbox extends Command
{
    protected $signature = 'kuncia:guest-reset';

    protected $description = 'Rebuild the guest (recruiter) account and its sample data. Real landlords are not touched.';

    public function handle(GuestSandbox $sandbox): int
    {
        $guest = $sandbox->reset();
        $this->info("Guest sandbox ready: {$guest->email}");

        return self::SUCCESS;
    }
}
