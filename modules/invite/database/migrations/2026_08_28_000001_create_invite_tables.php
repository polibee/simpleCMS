<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 邀请码：管理员生成（source=admin）/ 金币购买（gold）/ 加密购买（crypto）。
 * invite_orders 记录加密购买订单（支付成功后自动生成并归属购买者）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->enum('source', ['admin', 'gold', 'crypto'])->default('admin')->index();
            $table->decimal('paid_amount', 12, 2)->nullable()->comment('crypto 购买实付（USD）');
            $table->unsignedInteger('gold_spent')->nullable()->comment('gold 购买花费金币');
            $table->unsignedBigInteger('created_by')->nullable()->comment('生成/购买者（管理员生成时为空）');
            $table->unsignedBigInteger('used_by')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['active', 'used', 'disabled'])->default('active')->index();
            $table->string('batch_id', 40)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('invite_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 12, 2);
            $table->string('fiat_currency', 8)->default('USD');
            $table->string('channel', 20);
            $table->string('invoice_id')->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_orders');
        Schema::dropIfExists('invite_codes');
    }
};
