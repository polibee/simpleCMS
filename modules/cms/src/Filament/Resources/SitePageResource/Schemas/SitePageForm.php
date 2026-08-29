<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\SitePageResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SitePageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('页面信息')
                    ->schema([
                        TextInput::make('slug')
                            ->label('路径（slug）')
                            ->required()
                            ->alphaDash()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true)
                            ->helperText('前台访问地址：/p/{slug}；privacy / terms / about 另有固定入口'),
                        TextInput::make('title')
                            ->label('标题')
                            ->required()
                            ->maxLength(200),
                        Toggle::make('enabled')
                            ->label('启用')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('正文（基座富文本编辑器）')
                    ->schema([
                        \Miran\Mksine\Filament\Forms\Components\CKEditor::make('content')
                            ->label('页面内容')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
