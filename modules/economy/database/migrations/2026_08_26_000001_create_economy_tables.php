<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 货币配置（后台可编辑名称/汇率）
        Schema::create('economy_currency_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // gold | silver | copper | 自定义
            $table->string('name')->default('货币');   // 显示名，后台可改
            $table->decimal('unit', 12, 6)->default(1); // 基准汇率（相对主货币）
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // 钱包
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('currency', 32);
            $table->decimal('balance', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
            $table->index('user_id');
        });

        // 钱包流水（幂等：同一 (wallet_id, ref_type, ref_id, type) 不重复入账）
        Schema::create('wallet_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->string('currency', 32);
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 18, 6);
            $table->decimal('balance_after', 18, 6);
            $table->string('ref_type', 64);            // quest:checkin | invite:redeem | ...
            $table->string('ref_id', 64);              // 业务 ID
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->unique(['wallet_id', 'ref_type', 'ref_id', 'type'], 'wallet_ledger_idempotency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('economy_currency_configs');
    }
};
