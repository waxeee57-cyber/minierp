<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Magyar adószám: 12345678-2-41. Üresen: magánszemély.
            $table->string('tax_number', 13)->nullable()->after('company');
            $table->string('postal_code', 10)->nullable()->after('city');
            $table->string('address', 160)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['tax_number', 'postal_code', 'address']);
        });
    }
};
