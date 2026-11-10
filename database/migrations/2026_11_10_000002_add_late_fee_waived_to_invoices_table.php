<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Set on an invoice made after the fact for a missed month: it can still turn
            // overdue, but the nightly job does not add the late fee to it.
            $table->boolean('late_fee_waived')->default(false)->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('late_fee_waived');
        });
    }
};
