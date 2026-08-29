<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 备份与恢复（后台顶级页，super_admin）：
 * mysqldump 导出 → storage/app/backups/*.sql；支持下载、删除、导入恢复。
 * 凭据经临时 --defaults-extra-file 传递（避免出现在进程命令行）。
 */
class BackupManagerPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected string $view = 'filament.pages.backup-manager';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'backups';
    }

    protected static ?int $navigationSort = 2;

    public ?string $restoreFile = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('备份与恢复');
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('数据备份与恢复');
    }

    // ------------------------------------------------------------------
    // 列表
    // ------------------------------------------------------------------

    public function backups(): array
    {
        $dir = $this->backupDir();
        if (! is_dir($dir)) {
            return [];
        }

        return collect(glob($dir.'/*.sql') ?: [])
            ->map(fn ($path) => [
                'name' => basename($path),
                'size' => round(filesize($path) / 1024, 1).' KB',
                'time' => date('Y-m-d H:i:s', filemtime($path)),
                'path' => $path,
            ])
            ->sortByDesc('time')
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // 备份
    // ------------------------------------------------------------------

    public function backup(): void
    {
        $dir = $this->backupDir();
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->notify('无法创建备份目录', 'danger');

            return;
        }

        $target = $dir.'/backup-'.date('Ymd-His').'.sql';
        $database = config('database.connections.mysql.database');

        $cnf = $this->writeTempCnf();
        if (! $cnf) {
            return;
        }

        $binary = $this->binaryPath('mysqldump');
        $cnfArg = str_replace('\\', '/', $cnf);
        $targetArg = str_replace('\\', '/', $target);

        // stdout 重定向到备份文件（Windows cmd 引号安全）
        $result = Process::fromShellCommandline(
            '"'.$binary.'" --defaults-extra-file="'.$cnfArg.'" --single-transaction --databases '.$database.' > "'.$targetArg.'"',
        )->setTimeout(300)->run();

        @unlink($cnf);

        if (! $result->successful() || ! is_file($target) || filesize($target) < 100) {
            @unlink($target);
            $this->notify('备份失败：'.mb_substr($result->errorOutput() ?: $result->output(), 0, 200), 'danger');

            return;
        }

        $this->notify('备份完成：'.basename($target).'（'.round(filesize($target) / 1024, 1).' KB）', 'success');
    }

    public function download(string $name): ?BinaryFileResponse
    {
        $path = $this->guardPath($name);
        if (! $path) {
            return null;
        }

        return response()->download($path, $name);
    }

    public function remove(string $name): void
    {
        $path = $this->guardPath($name);
        if ($path) {
            @unlink($path);
            $this->notify('已删除 '.$name, 'success');
        }
    }

    // ------------------------------------------------------------------
    // 恢复（导入）
    // ------------------------------------------------------------------

    public function restore(string $name): void
    {
        $path = $this->guardPath($name);
        if (! $path) {
            return;
        }

        if (! $this->confirmRestore($name)) {
            return;
        }

        $cnf = $this->writeTempCnf();
        if (! $cnf) {
            return;
        }

        $database = config('database.connections.mysql.database');
        $binary = $this->binaryPath('mysql');
        $cnfArg = str_replace('\\', '/', $cnf);
        $pathArg = str_replace('\\', '/', $path);

        // dump 含 CREATE DATABASE/USE，stdin 导入本实例
        $result = Process::fromShellCommandline(
            '"'.$binary.'" --defaults-extra-file="'.$cnfArg.'" < "'.$pathArg.'"',
        )->setTimeout(600)->run();

        @unlink($cnf);

        if (! $result->successful()) {
            $this->notify('恢复失败：'.mb_substr($result->errorOutput() ?: $result->output(), 0, 200), 'danger');

            return;
        }

        $this->notify('恢复完成（'.$name.'），请重新登录刷新权限缓存。', 'success');
    }

    /** 恢复二次确认（Livewire 弹窗由 blade wire:confirm 实现）。 */
    private function confirmRestore(string $name): bool
    {
        return true;
    }

    // ------------------------------------------------------------------
    // 内部
    // ------------------------------------------------------------------

    private function backupDir(): string
    {
        return storage_path('app/backups');
    }

    private function guardPath(string $name): ?string
    {
        $path = realpath($this->backupDir().'/'.$name);

        if (! $path || ! str_starts_with($path, realpath($this->backupDir())) || ! is_file($path)) {
            $this->notify('无效的备份文件', 'danger');

            return null;
        }

        return $path;
    }

    /** Laragon bin 下查找 mysqldump/mysql（遍历版本目录）。 */
    private function binaryPath(string $tool): string
    {
        $candidates = glob('D:/laragon/bin/mysql/*/bin/'.$tool.'.exe') ?: [];

        return $candidates[0] ?? $tool; // 兜底交给 PATH
    }

    /** 临时 defaults-extra-file：凭据不出现在命令行。 */
    private function writeTempCnf(): ?string
    {
        $conn = config('database.connections.mysql');
        $cnfPath = storage_path('app/backups/.my.cnf.tmp');

        if (! is_dir(dirname($cnfPath))) {
            mkdir(dirname($cnfPath), 0700, true);
        }

        $content = "[client]\nuser=".$conn['username']."\n";
        if (! empty($conn['password'])) {
            $content .= 'password="'.$conn['password']."\"\n";
        }
        $content .= 'host='.($conn['host'] ?? '127.0.0.1')."\n";
        $content .= 'port='.($conn['port'] ?? 3306)."\n";

        if (file_put_contents($cnfPath, $content) === false) {
            $this->notify('无法写入临时凭据文件', 'danger');

            return null;
        }

        return $cnfPath;
    }

    private function notify(string $body, string $type): void
    {
        Notification::make()->body($body)->$type()->send();
    }
}
