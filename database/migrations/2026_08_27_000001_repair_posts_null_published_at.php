<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\CMS\Support\CmsQuery;

/**
 * 修复存量文章排序：已发布但 published_at 为 NULL 的数据在
 * ORDER BY published_at DESC 下沉底，表现为"新文章排在最后"。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('posts')) {
            CmsQuery::repairNullPublishedDates();
        }
    }

    public function down(): void
    {
        // 数据修复无回滚语义
    }
};
