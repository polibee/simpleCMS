<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商品交付系统：
 * - shop_products 增加 delivery_type（none/download/content/invite_code）+ delivery_payload
 * - shop_orders 增加 delivered_data JSON（支付成功后填充给买家的交付内容）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_products', function (Blueprint $table) {
            $table->enum('delivery_type', ['none', 'download', 'content', 'invite_code'])
                ->default('none')->after('status')->comment('交付类型');
            $table->text('delivery_payload')->nullable()->after('delivery_type')
                ->comment('交付内容：下载 URL / 文本内容');
        });

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->json('delivered_data')->nullable()->after('paid_at')
                ->comment('交付内容（支付成功后生成）');
        });
    }

    public function down(): void
    {
        Schema::table('shop_products', function (Blueprint $table) {
            $table->dropColumn(['delivery_type', 'delivery_payload']);
        });
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn('delivered_data');
        });
    }
};
