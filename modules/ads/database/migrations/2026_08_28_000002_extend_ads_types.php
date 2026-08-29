<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 广告类型扩展：combo（图文结合）、card（文章卡片式，网格/列表自适应）。
 * type 列由 enum 放宽为 string 以便扩展。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->string('type', 20)->default('text')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->enum('type', ['text', 'image', 'html'])->default('text')->change();
        });
    }
};
