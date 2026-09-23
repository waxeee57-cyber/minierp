<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_orders', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 30);
            $table->string('external_id', 80);
            $table->string('external_number', 80)->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('order_number', 40)->nullable();
            $table->string('status', 20)->index();
            $table->text('error')->nullable();
            $table->string('customer_email', 160)->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->string('utm_source', 80)->nullable()->index();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->json('payload');
            $table->timestamps();

            // Idempotencia: ugyanaz a webshop-rendelés kétszer sosem kerül be (a webhookok újrapróbálkoznak).
            $table->unique(['channel', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_orders');
    }
};
