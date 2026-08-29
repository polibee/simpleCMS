<?php

declare(strict_types=1);

namespace Modules\User\Filament\Resources\UserProfileResource;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\User\Filament\Resources\UserProfileResource\Pages;
use Modules\User\Filament\Resources\UserProfileResource\Schemas\UserProfileForm;
use Modules\User\Filament\Resources\UserProfileResource\Tables\UserProfileTable;
use Modules\User\Models\UserProfile;

class UserProfileResource extends Resource
{
    protected static ?string $model = UserProfile::class;

    protected static ?string $slug = 'userprofiles';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return __('UserProfiles');
    }

    public static function getModelLabel(): string
    {
        return __('UserProfile');
    }

    public static function getPluralModelLabel(): string
    {
        return __('UserProfiles');
    }

    public static function form(Schema $schema): Schema
    {
        return UserProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserProfileTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserProfiles::route('/'),
            'create' => Pages\CreateUserProfile::route('/create'),
            'edit' => Pages\EditUserProfile::route('/{record}/edit'),
        ];
    }
}