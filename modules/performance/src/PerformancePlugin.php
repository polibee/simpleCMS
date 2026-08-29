<?php

declare(strict_types=1);

namespace Modules\Performance;

use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;

class PerformancePlugin implements PluginInterface
{
    public function id(): string
    {
        return 'performance';
    }

    public function install(): void {}

    public function activate(): void {}

    public function deactivate(): void {}

    public function uninstall(bool $deleteData = false): void {}

    public function boot(): void {}

    public function migrationsPath(): ?string
    {
        return __DIR__.'/../database/migrations';
    }

    public function configPath(): ?string
    {
        return null;
    }

    public function viewsPath(): ?string
    {
        return null;
    }

    public function webRoutesPath(): ?string
    {
        return null;
    }

    public function apiRoutesPath(): ?string
    {
        return null;
    }

    public function translationsPath(): ?string
    {
        return null;
    }

    public function namespace(): ?string
    {
        return 'Modules\\Performance';
    }

    public function filamentResourcesPath(): ?string
    {
        return null;
    }

    public function filamentPagesPath(): ?string
    {
        return null;
    }

    public function filamentWidgetsPath(): ?string
    {
        return null;
    }
}
