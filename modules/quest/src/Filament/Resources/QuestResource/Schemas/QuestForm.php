<?php

declare(strict_types=1);

namespace Modules\Quest\Filament\Resources\QuestResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('任务规则')
                    ->schema([
                        TextInput::make('key')
                            ->label('标识')
                            ->disabled()
                            ->helperText('程序引用用，不可修改'),
                        TextInput::make('name')
                            ->label('名称')
                            ->required(),
                        Select::make('reward_currency')
                            ->label('奖励货币')
                            ->options([
                                'gold' => '金币',
                                'silver' => '银币',
                                'copper' => '铜币',
                            ])
                            ->required(),
                        TextInput::make('reward_amount')
                            ->label('奖励数额')
                            ->numeric()
                            ->required(),
                        TextInput::make('daily_limit')
                            ->label('每日上限次数')
                            ->numeric()
                            ->default(1),
                        Toggle::make('enabled')
                            ->label('启用'),
                    ]),
            ]);
    }
}
