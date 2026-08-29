<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 页脚多列导航（通用模板）：管理员添加列、每列绑定一个底座菜单。
 * 前端按列渲染（标题 + 菜单项树）；未配置时回退单行 footer 菜单。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_footer_columns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);                    // 列标题（如"产品""支持"）
            $table->unsignedBigInteger('menu_id')->nullable(); // 绑定的底座菜单
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_footer_columns');
    }
};
