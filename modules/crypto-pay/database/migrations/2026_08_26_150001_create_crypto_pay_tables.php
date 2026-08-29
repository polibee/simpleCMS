<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 加密支付订单（付费文章解锁凭证）
        Schema::create('crypto_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();          // 本地单号 CP-xxxx
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('post_id')->index();     // 付费文章
            $table->decimal('amount', 10, 2);                   // 法币金额（USD）
            $table->string('fiat_currency', 8)->default('USD');
            $table->string('invoice_id', 64)->nullable()->unique(); // CoinPayments 发票 ID
            $table->string('checkout_url', 500)->nullable();    // 托管收银台地址
            $table->enum('status', ['pending', 'paid', 'cancelled', 'failed'])->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();                // webhook 原始载荷（审计）
            $table->timestamps();

            $table->index(['user_id', 'post_id', 'status']);
        });

        // 付费文章定价（与 CMS 解耦：只存 post_id）
        Schema::create('crypto_post_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->unique();
            $table->decimal('price', 10, 2)->default(0);       // >0 即付费
            $table->string('currency', 8)->default('USD');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_post_prices');
        Schema::dropIfExists('crypto_orders');
    }
};
