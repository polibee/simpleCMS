<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 广告位管理：文本/图片/HTML(JS) 三类广告，按 position 投放到前端各注入点。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->comment('内部标识名');
            $table->string('position', 50)->index()->comment('投放位置');
            $table->enum('type', ['text', 'image', 'html'])->default('text');
            $table->string('title')->nullable();
            $table->text('text')->nullable()->comment('文本广告内容');
            $table->text('image_url')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->longText('html')->nullable()->comment('HTML/JS 广告代码');
            $table->unsignedTinyInteger('paragraph')->nullable()->comment('article_inline：注入在第几个段落后');
            $table->boolean('new_tab')->default(true);
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
