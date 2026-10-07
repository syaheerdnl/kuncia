<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A meter / utility account on a property (e.g. "TNB - Floor 1").
        Schema::create('utility_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('label');
            $table->string('account_no')->nullable();
            $table->timestamps();
        });

        // Which units share a meter.
        Schema::create('unit_utility_meter', function (Blueprint $table) {
            $table->foreignId('utility_meter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->primary(['utility_meter_id', 'unit_id']);
        });

        // One bill per meter per month.
        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_meter_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['utility_meter_id', 'period']);
        });

        // Each tenant's share. invoice_item_id is null while waiting for an open invoice.
        Schema::create('utility_bill_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenancy_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bill_shares');
        Schema::dropIfExists('utility_bills');
        Schema::dropIfExists('unit_utility_meter');
        Schema::dropIfExists('utility_meters');
    }
};
