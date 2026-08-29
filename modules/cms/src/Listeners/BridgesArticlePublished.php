<?php

namespace Modules\CMS\Listeners;

use App\Events\CmsArticlePublished;
use Miran\Mksine\Core\Events\MksineEvent;
use Miran\Mksine\Core\Hooks\MksineListenerInterface;

/**
 * 桥接监听器：底座 post.created(published) → 统一域事件 cms.article.published。
 * 下游（Quest/AI/Notification）只依赖统一事件，不感知底座事件结构。
 */
class BridgesArticlePublished implements MksineListenerInterface
{
    public function handle(MksineEvent $event): void
    {
        if ($event->data()->get('status') !== 'published') {
            return;
        }

        CmsArticlePublished::dispatch(
            (int) ($event->context()['post_id'] ?? 0),
            (int) ($event->context()['user_id'] ?? 0),
            (string) ($event->data()->get('title') ?? ''),
        );
    }

    public function shouldHandle(MksineEvent $event): bool
    {
        return true;
    }

    public function shouldQueue(): bool
    {
        return false;
    }

    public function priority(): int
    {
        return 20;
    }
}
