<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two entries of the same kind, day and amount (an electricity deposit and a water deposit,
 * both RM100) are different entries when their notes differ. The note joins the unique key.
 *
 * The new key is added before the old one is dropped, so the tenancy_id foreign key always has
 * an index to rely on. The name is given by hand: the default would be over MySQL's 64 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancy_transactions', function (Blueprint $table) {
            $table->unique(['tenancy_id', 'type', 'occurred_on', 'amount', 'note'], 'tenancy_transactions_entry_unique');
        });

        Schema::table('tenancy_transactions', function (Blueprint $table) {
            $table->dropUnique(['tenancy_id', 'type', 'occurred_on', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_transactions', function (Blueprint $table) {
            $table->unique(['tenancy_id', 'type', 'occurred_on', 'amount']);
        });

        Schema::table('tenancy_transactions', function (Blueprint $table) {
            $table->dropUnique('tenancy_transactions_entry_unique');
        });
    }
};
