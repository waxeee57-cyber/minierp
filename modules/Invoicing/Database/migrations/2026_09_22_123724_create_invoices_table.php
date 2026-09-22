<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('order_id')->constrained()->unique();
            $table->foreignId('customer_id')->constrained();
            $table->date('issue_date');
            $table->date('delivery_date');
            $table->date('payment_due');
            $table->unsignedBigInteger('net_total');
            $table->unsignedBigInteger('vat_total');
            $table->unsignedBigInteger('gross_total');
            $table->json('lines');
            $table->longText('xml');
            $table->enum('nav_status', ['validated', 'invalid', 'submitted', 'storno_required'])->default('validated');
            $table->text('nav_error')->nullable();
            $table->string('nav_transaction_id', 40)->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
