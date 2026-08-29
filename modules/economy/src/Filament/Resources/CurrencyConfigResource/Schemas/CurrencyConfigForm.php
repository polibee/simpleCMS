<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\CurrencyConfigResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Miran\Mksine\Core\Hooks\FormHookManager;

class CurrencyConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        $schema = $schema
            ->components([
                Section::make('货币配置')
                    ->schema([
                        TextInput::make('key')
                            ->label('标识')
                            ->required()
                            ->maxLength(32)
                            ->helperText('如 gold / silver / copper，程序引用用'),
                        TextInput::make('name')
                            ->label('名称')
                            ->required()
                            ->maxLength(255)
                            ->helperText('显示名，可自定义，如"金币"'),
                        TextInput::make('unit')
                            ->label('汇率')
                            ->numeric()
                            ->required()
                            ->helperText('相对主货币（gold=1）的换算比例'),
                        Toggle::make('enabled')
                            ->label('启用'),
                    ]),
            ]);

        // Apply form hooks
        $formHookManager = app(FormHookManager::class);

        return $formHookManager->apply('CurrencyConfig.form', $schema);
    }
}
