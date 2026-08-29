<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 系统级侧边栏卡片实例表（ADR-010）。
 * 所有模块/产品的区域共用此表；区域由各模块注册（cms.sidebar / forum.sidebar / ...）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_cards', function (Blueprint $table) {
            $table->id();
            $table->string('area_key', 64);   // 区域 key（模块注册）
            $table->string('type', 32);       // 卡片类型 key
            $table->string('title')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('enabled')->default(true);
            $table->json('data')->nullable(); // 类型专属配置
            $table->timestamps();

            $table->index(['area_key', 'enabled', 'sort']);
        });

        // 迁移旧 cms_cards 数据（若存在）
        if (Schema::hasTable('cms_cards')) {
            $rows = DB::table('cms_cards')->get(['area_key', 'type', 'title', 'sort', 'enabled', 'data']);

            foreach ($rows as $row) {
                DB::table('sidebar_cards')->insert([
                    'area_key' => $row->area_key,
                    'type' => $row->type,
                    'title' => $row->title,
                    'sort' => $row->sort,
                    'enabled' => $row->enabled,
                    'data' => $row->data,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::drop('cms_cards');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_cards');
    }
};
