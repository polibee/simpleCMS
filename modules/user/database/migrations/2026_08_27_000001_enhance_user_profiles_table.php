<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 个人资料增强：
 * - bio 改 TEXT（Markdown 长文，支持精美个人简介卡片）
 * - 新增 donation_url / donation_text（创作者自定义捐赠按钮）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->text('bio')->nullable()->change();
            $table->string('donation_url', 500)->nullable()->after('signature');
            $table->string('donation_text', 100)->nullable()->after('donation_url');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['donation_url', 'donation_text']);
            $table->string('bio')->nullable()->change();
        });
    }
};
