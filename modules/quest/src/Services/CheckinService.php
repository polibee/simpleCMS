<?php

namespace Modules\Quest\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Modules\Economy\Services\WalletService;
use Modules\Quest\Models\Quest;
use Modules\Quest\Models\QuestCheckin;

/**
 * 签到服务：每日一次，奖励货币。
 * 幂等：quest_checkins (user_id, checkin_date) 唯一约束 + 钱包 ref 幂等双保险。
 */
final class CheckinService
{
    public function __construct(
        private readonly WalletService $wallets,
    ) {}

    public const QUEST_KEY = 'daily_checkin';

    /**
     * 执行签到。
     *
     * @return array{ok: bool, message: string, reward: float|null}
     */
    public function checkin(Authenticatable $user): array
    {
        $date = now()->toDateString();

        return DB::transaction(function () use ($user, $date) {
            // 原子插入防并发重复签到（唯一约束兜底）
            try {
                $quest = Quest::query()->where('key', self::QUEST_KEY)->first();

                QuestCheckin::create([
                    'user_id' => (int) $user->getAuthIdentifier(),
                    'checkin_date' => $date,
                    'quest_id' => $quest?->id,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                return ['ok' => false, 'message' => '今天已经签到过了', 'reward' => null];
            }

            if (! $quest || ! $quest->enabled || (float) $quest->reward_amount <= 0) {
                return ['ok' => true, 'message' => '签到成功（未配置奖励）', 'reward' => 0.0];
            }

            // 发奖：钱包层幂等（ref = quest:daily_checkin:{date}）
            $ledger = $this->wallets->apply(
                $user,
                $quest->reward_currency,
                'credit',
                (float) $quest->reward_amount,
                'quest:'.self::QUEST_KEY,
                $date,
                '每日签到',
            );

            return [
                'ok' => true,
                'message' => $ledger !== null ? "签到成功，获得 {$quest->reward_amount} {$quest->reward_currency}" : '今日奖励已发放过',
                'reward' => $ledger !== null ? (float) $ledger->amount : null,
            ];
        });
    }

    /**
     * 用户今天是否已签到。
     */
    public function hasCheckedInToday(Authenticatable $user): bool
    {
        return QuestCheckin::query()
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('checkin_date', now()->toDateString())
            ->exists();
    }
}
