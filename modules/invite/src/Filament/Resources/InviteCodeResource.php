<?php

declare(strict_types=1);

namespace Modules\Invite\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * 邀请码管理（后台 → 内容 → 邀请码）：列表 + 批量生成。
 */
class InviteCodeResource extends Resource
{
    protected static ?string $model = \Modules\Invite\Models\InviteCode::class;

    protected static ?string $slug = 'invite-codes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static \UnitEnum|string|null $navigationGroup = \Miran\Mksine\Filament\Support\AdminNavigationGroup::Content;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('invite::invite.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('invite::invite.invite_code');
    }

    public static function form(Schema $schema): Schema
    {
        return \Modules\Invite\Filament\Resources\InviteCodeResource\Schemas\InviteCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \Modules\Invite\Filament\Resources\InviteCodeResource\Tables\InviteCodeTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\Invite\Filament\Resources\InviteCodeResource\Pages\ListInviteCodes::route('/'),
        ];
    }
}
