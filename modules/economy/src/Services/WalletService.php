<?php

namespace Modules\Economy\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Economy\Events\WalletCredited;
use Modules\Economy\Events\WalletDebited;
use Modules\Economy\Models\Wallet;
use Modules\Economy\Models\WalletLedger;

/**
 * 钱包核心服务：原子 credit/debit + 幂等。
 *
 * 幂等保证：wallet_ledger 表 (wallet_id, ref_type, ref_id, type) 唯一约束。
 * 同一业务引用重复调用只会入账一次；并发下由唯一约束兜底。
 */
final class WalletService
{
    /**
     * 记账（credit=入账 / debit=扣减），返回流水记录。
     * 幂等：同一 (refType, refId, type) 重复调用返回 null 且不改变余额。
     *
     * @throws \RuntimeException 余额不足（debit）或货币未启用
     */
    public function apply(
        Authenticatable $user,
        string $currency,
        string $type,
        float|string $amount,
        string $refType,
        int|string $refId,
        ?string $remark = null,
    ): ?WalletLedger {
        if (! in_array($type, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException("type 必须为 credit 或 debit，收到: {$type}");
        }

        $amount = (float) $amount;
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须大于 0');
        }

        $userId = (int) $user->getAuthIdentifier();

        return $this->inTransaction(function () use ($userId, $currency, $type, $amount, $refType, $refId, $remark) {
            // 幂等预检：已入账则直接返回 null
            $existing = WalletLedger::query()
                ->where('wallet_id', $this->walletIdFor($userId, $currency))
                ->where('ref_type', $refType)
                ->where('ref_id', (string) $refId)
                ->where('type', $type)
                ->first();

            if ($existing) {
                return null;
            }

            // 行锁钱包（防并发）
            $wallet = Wallet::query()
                ->where('user_id', $userId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $userId,
                    'currency' => $currency,
                    'balance' => 0,
                ]);
            }

            $newBalance = (float) $wallet->balance;

            // 余额列是 decimal(18,6)：PHP 浮点运算的误差会在多次累加后漂移，
            // 每次运算后按存储精度定档，避免误差累积（P3-11）
            if ($type === 'credit') {
                $newBalance = round($newBalance + $amount, 6);
            } else {
                if (round($newBalance, 6) < round($amount, 6)) {
                    throw new \RuntimeException("余额不足：{$currency} 当前 {$newBalance}，需要 {$amount}");
                }
                $newBalance = round($newBalance - $amount, 6);
            }

            $wallet->update(['balance' => $newBalance]);

            $ledger = WalletLedger::create([
                'wallet_id' => $wallet->id,
                'currency' => $currency,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'ref_type' => $refType,
                'ref_id' => (string) $refId,
                'remark' => $remark,
            ]);

            if ($type === 'credit') {
                WalletCredited::dispatch($ledger);
            } else {
                WalletDebited::dispatch($ledger);
            }

            return $ledger;
        });
    }

    /**
     * 事务执行：已在事务内则直接执行（避免嵌套 SAVEPOINT 在 MySQL 下的兼容问题），
     * 否则开启新事务。保证单条 SQL 的原子性由外层事务/行锁承担。
     */
    private function inTransaction(callable $callback): mixed
    {
        if (DB::transactionLevel() > 0) {
            return $callback();
        }

        return DB::transaction($callback);
    }

    /**
     * 转账（锁定两个钱包，原子）。
     */
    public function transfer(
        Authenticatable $from,
        Authenticatable $to,
        string $currency,
        float|string $amount,
        string $remark = '转账',
    ): void {
        $amount = (float) $amount;
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须大于 0');
        }

        $fromId = (int) $from->getAuthIdentifier();
        $toId = (int) $to->getAuthIdentifier();

        $this->inTransaction(function () use ($fromId, $toId, $currency, $amount, $remark) {
            // 按 id 升序锁，避免死锁
            [$firstId, $secondId] = $fromId < $toId ? [$fromId, $toId] : [$toId, $fromId];

            $firstWallet = Wallet::query()->where('user_id', $firstId)->where('currency', $currency)->lockForUpdate()->first();
            $secondWallet = Wallet::query()->where('user_id', $secondId)->where('currency', $currency)->lockForUpdate()->first();

            // from 对应较小 id 时：from=$firstWallet, to=$secondWallet；否则相反
            $fromWallet = $firstId === $fromId ? $firstWallet : $secondWallet;
            $toWallet = $firstId === $fromId ? $secondWallet : $firstWallet;

            if (! $fromWallet || (float) $fromWallet->balance < $amount) {
                throw new \RuntimeException('转账余额不足');
            }

            if (! $toWallet) {
                $toWallet = Wallet::create([
                    'user_id' => $toId,
                    'currency' => $currency,
                    'balance' => 0,
                ]);
            }

            // 同 apply()：按 decimal(18,6) 的存储精度定档，防浮点漂移
            $fromNew = round((float) $fromWallet->balance - $amount, 6);
            $toNew = round((float) $toWallet->balance + $amount, 6);

            $fromWallet->update(['balance' => $fromNew]);
            $toWallet->update(['balance' => $toNew]);

            // ref_id 必须每次转账唯一：旧实现固定为 'user:{对方id}'，第二次转账
            // 就会撞上 wallet_ledger_idempotency 唯一索引，导致同一对用户只能
            // 转账一次（P3-4）。这里带上转账批次 ID，双方共用同一批次号便于对账。
            $batch = 'transfer:'.Str::uuid()->toString();

            WalletLedger::create([
                'wallet_id' => $fromWallet->id,
                'currency' => $currency,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $fromNew,
                'ref_type' => 'transfer',
                'ref_id' => $batch.':out:'.$toId,
                'remark' => $remark,
            ]);
            WalletLedger::create([
                'wallet_id' => $toWallet->id,
                'currency' => $currency,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $toNew,
                'ref_type' => 'transfer',
                'ref_id' => $batch.':in:'.$fromId,
                'remark' => $remark,
            ]);
        });
    }

    public function balance(Authenticatable $user, string $currency): float
    {
        $wallet = Wallet::query()
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('currency', $currency)
            ->first();

        return $wallet ? (float) $wallet->balance : 0.0;
    }

    private function walletIdFor(int $userId, string $currency): int
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->where('currency', $currency)
            ->first();

        if (! $wallet) {
            $wallet = Wallet::create([
                'user_id' => $userId,
                'currency' => $currency,
                'balance' => 0,
            ]);
        }

        return (int) $wallet->id;
    }
}
