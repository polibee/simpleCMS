<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 统一内容域事件：CMS 文章已发布。
 * 下游模块（Quest/AI/Search/Notification）监听此事件而非底座内部事件。
 */
class CmsArticlePublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $postId,
        public readonly int $authorId,
        public readonly string $title,
    ) {}
}
