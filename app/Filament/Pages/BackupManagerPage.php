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

    /**
     * 执行一次备份。
     *
     * @param  string|null  $suffix  自定义文件名后缀（恢复前的自动快照用）
     */
    public function backup(?string $suffix = null): void
    {
        $dir = $this->backupDir();
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->notify('无法创建备份目录', 'danger');

            return;
        }

        $target = $dir.'/backup-'.($suffix ?: date('Ymd-His')).'.sql';
        $database = config('database.connections.mysql.database');

        $cnf = $this->writeTempCnf();
        if (! $cnf) {
            return;
        }

        $binary = $this->binaryPath('mysqldump');
        $cnfArg = str_replace('\\', '/', $cnf);
        $targetArg = str_replace('\\', '/', $target);

        try {
            // stdout 重定向到备份文件（Windows cmd 引号安全）；库名加反引号
            $result = Process::fromShellCommandline(
                '"'.$binary.'" --defaults-extra-file="'.$cnfArg.'" --single-transaction --databases `'.str_replace('`', '\\`', (string) $database).'` > "'.$targetArg.'"',
            )->setTimeout(300)->run();
        } finally {
            @unlink($cnf);
        }

        if (! $result->successful() || ! is_file($target) || filesize($target) < 100) {
            @unlink($target);
            $this->notify('备份失败：'.mb_substr($result->errorOutput() ?: $result->output(), 0, 200), 'danger');

            return;
        }

        @chmod($target, 0600); // 备份含全量数据，禁止其它本地用户读取

        // 自动快照不需要打扰用户（它是恢复流程的一部分）
        if ($suffix === null) {
            $this->notify('备份完成：'.basename($target).'（'.round(filesize($target) / 1024, 1).' KB）', 'success');
        }
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

        // 破坏性操作：导入前先自动做一份快照，保证可回滚
        $this->backup('restore-'.date('Ymd-His'));

        $cnf = $this->writeTempCnf();
        if (! $cnf) {
            return;
        }

        $database = config('database.connections.mysql.database');
        $binary = $this->binaryPath('mysql');
        $cnfArg = str_replace('\\', '/', $cnf);
        $pathArg = str_replace('\\', '/', $path);

        try {
            // 库名加反引号：含特殊字符的库名不会破坏命令行结构
            $result = Process::fromShellCommandline(
                '"'.$binary.'" --defaults-extra-file="'.$cnfArg.'" `'.str_replace('`', '\\`', (string) $database).'` < "'.$pathArg.'"',
            )->setTimeout(600)->run();
        } finally {
            // 无论成功失败、是否超时，都必须抹掉明文凭据文件
            @unlink($cnf);
        }

        if (! $result->successful()) {
            // 只给简短摘要，避免把可能含敏感信息的 stderr 完整回显
            $this->notify('恢复失败，详见日志（logs/laravel.log）', 'danger');
            \Illuminate\Support\Facades\Log::error('数据库恢复失败', [
                'file' => $name,
                'output' => mb_substr($result->errorOutput() ?: $result->output(), 0, 2000),
            ]);

            return;
        }

        \Illuminate\Support\Facades\Log::warning('管理员执行了数据库恢复', [
            'file' => $name,
            'user_id' => auth()->id(),
        ]);

        $this->notify('恢复完成（'.$name.'），导入前已自动生成快照，请重新登录刷新权限缓存。', 'success');
    }

    // ------------------------------------------------------------------
    // 内部
    // ------------------------------------------------------------------

    private function backupDir(): string
    {
        return (string) (config('backup.disk_dir') ?: storage_path('app/backups'));
    }

    private function guardPath(string $name): ?string
    {
        $dir = realpath($this->backupDir());
        $path = realpath($this->backupDir().'/'.$name);

        // 目录前缀比较必须带分隔符：否则同级的 backups-evil/x.sql 也会通过
        if ($dir === false || ! $path || ! str_starts_with($path, $dir.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            $this->notify('无效的备份文件', 'danger');

            return null;
        }

        return $path;
    }

    /**
     * 定位 mysqldump / mysql 可执行文件。
     *
     * 搜索路径由 config/backup.php 的 binaries 与 env 控制，不再硬编码开发机目录
     * （原实现写死本机 Laragon 的 mysql bin 通配路径，仅在本机可用）。
     */
    private function binaryPath(string $tool): string
    {
        $configured = config('backup.binaries.'.$tool);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach ((array) config('backup.search_paths', []) as $pattern) {
            $candidates = glob(str_replace('{tool}', $tool, (string) $pattern)) ?: [];
            if ($candidates !== []) {
                return $candidates[0];
            }
        }

        return $tool; // 兜底交给 PATH
    }

    /**
     * 临时 defaults-extra-file：凭据不出现在命令行。
     *
     * 文件以 0600 写入，且调用方必须在 finally 中 unlink（超时/异常也不留明文）。
     */
    private function writeTempCnf(): ?string
    {
        $conn = config('database.connections.mysql');
        $cnfPath = storage_path('app/backups/.my.cnf.tmp');

        if (! is_dir(dirname($cnfPath))) {
            mkdir(dirname($cnfPath), 0700, true);
        }

        $content = "[client]\nuser=".$conn['username']."\n";
        if (! empty($conn['password'])) {
            $content .= 'password="'.str_replace('"', '\\"', (string) $conn['password'])."\"\n";
        }
        $content .= 'host='.($conn['host'] ?? '127.0.0.1')."\n";
        $content .= 'port='.($conn['port'] ?? 3306)."\n";

        if (file_put_contents($cnfPath, $content) === false) {
            $this->notify('无法写入临时凭据文件', 'danger');

            return null;
        }

        @chmod($cnfPath, 0600);

        return $cnfPath;
    }

    private function notify(string $body, string $type): void
    {
        Notification::make()->body($body)->$type()->send();
    }
}
