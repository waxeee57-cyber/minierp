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

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->enum('status', ['pending', 'paid', 'shipped', 'cancelled'])->default('pending');
            $table->unsignedInteger('total')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'placed_at']);
            $table->index(['customer_id', 'placed_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
