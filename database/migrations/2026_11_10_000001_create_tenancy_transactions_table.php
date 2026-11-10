<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenancy_transactions', function (Blueprint $table) {
            $table->id();
            // Money records are never deleted along with a tenancy.
            $table->foreignId('tenancy_id')->constrained()->restrictOnDelete();
            $table->string('type')->index();
            $table->decimal('amount', 10, 2);
            $table->date('occurred_on')->index();
            $table->string('note', 150)->nullable();
            $table->timestamps();

            // Lets the importer run twice without duplicating a row.
            $table->unique(['tenancy_id', 'type', 'occurred_on', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenancy_transactions');
    }
};
