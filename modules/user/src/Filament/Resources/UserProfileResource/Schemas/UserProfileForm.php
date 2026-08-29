<?php

declare(strict_types=1);

namespace Modules\User\Filament\Resources\UserProfileResource\Schemas;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Miran\Mksine\Core\Hooks\FormHookManager;

/**
 * 用户资料表单：与前台"创作中心→个人设置"完全同源（user_profiles 表）。
 * bio 为 Markdown 源文（个人主页简介卡片）；捐赠链接/文案与前台互填互通。
 */
class UserProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        $schema = $schema
            ->components([
                Section::make('用户资料')
                    ->schema([
                        TextInput::make('user_id')
                            ->label('用户 ID')
                            ->numeric()
                            ->required(),
                        MarkdownEditor::make('bio')
                            ->label('简介（Markdown，与前台个人设置同步）')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'strike', 'link', 'heading',
                                'blockquote', 'codeBlock', 'bulletList', 'orderedList', 'undo', 'redo',
                            ])
                            ->maxLength(20000)
                            ->helperText('保存后在用户个人主页展示为 Markdown 简介卡片'),
                        TextInput::make('website')
                            ->label('个人网站')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('signature')
                            ->label('签名')
                            ->maxLength(500),
                        TextInput::make('avatar_media_id')
                            ->label('头像媒体 ID')
                            ->numeric()
                            ->helperText('来自媒体库 MediaPicker 的媒体 ID'),
                    ]),

                Section::make('创作者捐赠按钮（与前台个人设置同步）')
                    ->description('填写后该用户每篇文章末尾显示专属捐赠按钮，优先于站点默认配置。')
                    ->schema([
                        TextInput::make('donation_url')
                            ->label('捐赠链接')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://donatr.ee/yourname'),
                        TextInput::make('donation_text')
                            ->label('按钮文案')
                            ->maxLength(100)
                            ->default('支持作者'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);

        // Apply form hooks
        $formHookManager = app(FormHookManager::class);

        return $formHookManager->apply('UserProfile.form', $schema);
    }
}
