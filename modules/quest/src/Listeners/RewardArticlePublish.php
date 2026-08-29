<?php

namespace Modules\Quest\Listeners;

use App\Models\User;
use Miran\Mksine\Core\Events\MksineEvent;
use Miran\Mksine\Core\Hooks\MksineListenerInterface;
use Modules\Economy\Services\WalletService;
use Modules\Quest\Models\Quest;

/**
 * 发布文章奖励：监听底座 post.created 事件。
 * 仅对 status=published 的文章发放；幂等由钱包层 (quest:post:{id}) 保证。
 */
class RewardArticlePublish implements MksineListenerInterface
{
    public function __construct(
        private readonly WalletService $wallets,
    ) {}

    public function handle(MksineEvent $event): void
    {
        $postId = $event->context()['post_id'] ?? null;
        $userId = $event->context()['user_id'] ?? null;

        if (! $postId || ! $userId) {
            return;
        }

        $status = $event->data()->get('status');
        if ($status !== 'published') {
            return; // 草稿不发奖
        }

        $quest = Quest::query()->where('key', 'publish_article')->first();
        if (! $quest || ! $quest->enabled || (float) $quest->reward_amount <= 0) {
            return;
        }

        $user = User::query()->find((int) $userId);
        if (! $user) {
            return;
        }

        // 幂等 ref：同一篇文章只奖励一次
        $this->wallets->apply(
            $user,
            $quest->reward_currency,
            'credit',
            (float) $quest->reward_amount,
            'quest:post',
            (int) $postId,
            '发布文章奖励',
        );
    }

    public function shouldHandle(MksineEvent $event): bool
    {
        return true;
    }

    public function shouldQueue(): bool
    {
        return false; // 同步执行：奖励发放需要即时反馈
    }

    public function priority(): int
    {
        return 10;
    }
}
