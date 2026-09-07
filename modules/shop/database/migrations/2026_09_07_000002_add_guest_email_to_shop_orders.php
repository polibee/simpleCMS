<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 游客购买支持：
 * - shop_orders.user_id 改为 nullable（游客下单无账号）
 * - shop_orders 增加 guest_email（游客下单时收集的邮箱，用于邮件交付）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE shop_orders MODIFY user_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('shop_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('guest_email', 191)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn('guest_email');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE shop_orders MODIFY user_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('shop_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
            });
        }
    }
};
