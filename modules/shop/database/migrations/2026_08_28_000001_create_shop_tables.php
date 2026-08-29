<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 商城：商品（上架/下架/库存）+ 订单流水。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable()->comment('商品介绍（Markdown）');
            $table->decimal('price', 12, 2);
            $table->string('currency', 8)->default('USD');
            $table->unsignedInteger('stock')->default(0)->comment('库存（0=售罄）');
            $table->unsignedInteger('sold')->default(0)->comment('累计销量');
            $table->string('image_url', 500)->nullable();
            $table->enum('status', ['draft', 'on_sale', 'off_sale'])->default('draft')->index()
                ->comment('draft=草稿 / on_sale=上架 / off_sale=下架');
            $table->timestamps();
        });

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('USD');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('channel', 20);
            $table->string('invoice_id')->nullable()->comment('PayPal orderId / Xcash invoiceId');
            $table->string('checkout_url', 500)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_products');
    }
};
