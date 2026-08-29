<?php

declare(strict_types=1);

namespace Modules\Invite\Filament\Resources\InviteCodeResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InviteCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('邀请码信息（只读，生成请用列表页"批量生成"）')
                ->schema([
                    TextInput::make('code')->label('邀请码')->disabled(),
                    TextInput::make('source')->label('来源')->disabled(),
                    TextInput::make('status')->label('状态')->disabled(),
                    TextInput::make('gold_spent')->label('花费金币')->numeric()->disabled(),
                    TextInput::make('paid_amount')->label('实付金额')->numeric()->disabled(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }
}
