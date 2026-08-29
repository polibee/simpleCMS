<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crypto_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('session_id', 64)->nullable()->index();  // 游客订单绑定会话
            $table->string('ip', 45)->nullable();                    // 下单 IP（审计）
            $table->string('channel', 24)->default('coinpayments'); // coinpayments | xcash | mock
            $table->string('guest_email', 191)->nullable();         // 游客联系邮箱（可选）
        });
    }

    public function down(): void
    {
        Schema::table('crypto_orders', function (Blueprint $table) {
            $table->dropColumn(['session_id', 'ip', 'channel', 'guest_email']);
        });

        Schema::table('crypto_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
