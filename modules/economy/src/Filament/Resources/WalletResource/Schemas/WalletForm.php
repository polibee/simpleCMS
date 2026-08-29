<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Miran\Mksine\Core\Hooks\FormHookManager;

class WalletForm
{
    public static function configure(Schema $schema): Schema
    {
        $schema = $schema
            ->components([
                Section::make('钱包')
                    ->schema([
                        TextInput::make('user_id')
                            ->label('用户 ID')
                            ->numeric()
                            ->required(),
                        TextInput::make('currency')
                            ->label('货币')
                            ->required()
                            ->maxLength(32),
                        TextInput::make('balance')
                            ->label('余额')
                            ->numeric()
                            ->required(),
                    ]),
            ]);

        // Apply form hooks
        $formHookManager = app(FormHookManager::class);

        return $formHookManager->apply('Wallet.form', $schema);
    }
}
