<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletLedgerResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Miran\Mksine\Core\Hooks\FormHookManager;

class WalletLedgerForm
{
    public static function configure(Schema $schema): Schema
    {
        $schema = $schema
            ->components([
                Section::make('流水')
                    ->schema([
                        TextInput::make('wallet_id')
                            ->label('钱包 ID')
                            ->numeric()
                            ->required(),
                        TextInput::make('currency')
                            ->label('货币')
                            ->required()
                            ->maxLength(32),
                        Select::make('type')
                            ->label('类型')
                            ->options(['credit' => '入账', 'debit' => '扣减'])
                            ->required(),
                        TextInput::make('amount')
                            ->label('金额')
                            ->numeric()
                            ->required(),
                        TextInput::make('balance_after')
                            ->label('变动后余额')
                            ->numeric()
                            ->required(),
                        TextInput::make('ref_type')
                            ->label('来源类型')
                            ->required()
                            ->maxLength(64),
                        TextInput::make('ref_id')
                            ->label('来源 ID')
                            ->required()
                            ->maxLength(64),
                        TextInput::make('remark')
                            ->label('备注')
                            ->maxLength(255),
                    ]),
            ]);

        // Apply form hooks
        $formHookManager = app(FormHookManager::class);

        return $formHookManager->apply('WalletLedger.form', $schema);
    }
}
