<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tenants and maintenance staff belong to the landlord who added them. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('landlord_id')->nullable()->after('phone')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('landlord_id');
        });
    }
};
