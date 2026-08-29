<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Support\CmsQuery;

/**
 * 定时发布：将 published_at 已到时间但仍为 draft 状态的文章自动发布。
 * 由 Laravel Scheduler 每分钟调度（routes/console.php 或 Kernel::schedule）。
 */
class PublishScheduledPosts extends Command
{
    protected $signature = 'cms:publish-scheduled';
    protected $description = '发布到达定时发布时间的文章';

    public function handle(): int
    {
        $now = now();

        // 发布：status=draft 且 published_at <= now
        $published = DB::table('posts')
            ->where('status', 'draft')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->update(['status' => 'published', 'updated_at' => $now]);

        // 归档：status=published 且 published_at > now（管理员提前设了未来时间改为撤回）
        $archived = DB::table('posts')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '>', $now)
            ->where('created_at', '<', $now->copy()->subMinutes(5)) // 避免刚创建就被归档
            ->update(['status' => 'draft', 'updated_at' => $now]);

        if ($published > 0 || $archived > 0) {
            $this->info("定时发布：{$published} 篇发布，{$archived} 篇归档。");
        }

        return self::SUCCESS;
    }
}
