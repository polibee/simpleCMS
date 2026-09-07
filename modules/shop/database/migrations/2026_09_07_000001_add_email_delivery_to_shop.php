<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 商品交付新增「邮件交付」：
 * - shop_products.delivery_type 枚举增加 email（支付回调后把 payload 通过后台邮箱服务发给买家）
 * - shop_orders 增加 email_sent_at（邮件已发送时间，幂等标记）
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL 枚举无法原地扩展，需要 MODIFY；SQLite（测试）用重建设备兼容
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE shop_products MODIFY delivery_type ENUM('none','download','content','invite_code','email') NOT NULL DEFAULT 'none'");
        } else {
            Schema::table('shop_products', function (Blueprint $table) {
                $table->string('delivery_type', 24)->default('none')->change();
            });
        }

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('delivered_data');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn('email_sent_at');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE shop_products MODIFY delivery_type ENUM('none','download','content','invite_code') NOT NULL DEFAULT 'none'");
        } else {
            Schema::table('shop_products', function (Blueprint $table) {
                $table->string('delivery_type', 24)->default('none')->change();
            });
        }
    }
};
