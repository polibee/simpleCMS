<?php

declare(strict_types=1);

namespace Modules\Ads\Filament\Resources\AdResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Ads\Models\Ad;

/**
 * 广告表单：基础信息 + 按类型分区的字段（text/image/html 各自独立，
 * 切换类型只显示对应区块，避免字段重复混淆）。
 */
class AdForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('基础设置')
                    ->schema([
                        TextInput::make('name')
                            ->label('名称（内部标识）')
                            ->required()
                            ->maxLength(120)
                            ->columnSpanFull(),
                        Select::make('position')
                            ->label('投放位置')
                            ->options(Ad::POSITIONS)
                            ->required()
                            ->helperText('广告将渲染到所选位置（按排序值顺序）'),
                        Select::make('type')
                            ->label('广告类型')
                            ->options(Ad::TYPES)
                            ->default('text')
                            ->required()
                            ->live()
                            ->helperText('切换后只在下方填写对应类型的字段'),                        TextInput::make('sort')
                            ->label('排序（小的在前）')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(9999),
                        Toggle::make('enabled')
                            ->label('启用')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // ── 文本广告 ──
                Section::make('文本广告内容')
                    ->schema([
                        TextInput::make('text')
                            ->label('广告文案')
                            ->required(fn ($get) => in_array($get('type'), [null, 'text']))
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('link_url')
                            ->label('跳转链接')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Toggle::make('new_tab')
                            ->label('新窗口打开')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($get) => in_array($get('type'), [null, 'text'])),

                // ── 图文结合广告 ──
                Section::make('图文结合广告内容')
                    ->schema([
                        TextInput::make('image_url')
                            ->label('图片地址')
                            ->url()
                            ->required(fn ($get) => $get('type') === 'combo')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('title')
                            ->label('标题')
                            ->maxLength(200)
                            ->columnSpanFull(),
                        TextInput::make('text')
                            ->label('广告文案')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('link_url')
                            ->label('跳转链接')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Toggle::make('new_tab')
                            ->label('新窗口打开')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('type') === 'combo'),

                // ── 文章卡片式广告 ──
                Section::make('文章卡片式广告内容')
                    ->description('外观与文章卡片一致（网格/列表自动跟随当前布局），图片自动裁剪为卡片比例。')
                    ->schema([
                        TextInput::make('title')
                            ->label('卡片标题')
                            ->required(fn ($get) => $get('type') === 'card')
                            ->maxLength(200)
                            ->columnSpanFull(),
                        TextInput::make('text')
                            ->label('卡片摘要')
                            ->maxLength(300)
                            ->columnSpanFull(),
                        TextInput::make('image_url')
                            ->label('封面图片地址')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('link_url')
                            ->label('跳转链接')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('paragraph')
                            ->label('插入位置（第 N 张卡片后）')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->default(0)
                            ->helperText('仅"卡片中间"位置生效：0 或 1 = 插在最前；3 = 第 3 张卡片之后'),
                        Toggle::make('new_tab')
                            ->label('新窗口打开')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('type') === 'card'),

                // ── HTML / JS 广告 ──
                Section::make('HTML / JS 广告代码')
                    ->schema([
                        Textarea::make('html')
                            ->label('广告代码')
                            ->required(fn ($get) => $get('type') === 'html')
                            ->rows(10)
                            ->columnSpanFull()
                            ->helperText('支持 HTML 与 <script>（Google AdSense、联盟广告等）；渲染时原样输出并执行脚本'),
                    ])
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('type') === 'html'),

                // ── 段落注入位置（仅 article_inline）──
                Section::make('段落注入位置')
                    ->schema([
                        TextInput::make('paragraph')
                            ->label('注入到第 N 个段落后')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(1)
                            ->helperText('1 = 第一段正文之后'),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->visible(fn ($get) => $get('position') === 'article_inline' && $get('type') !== 'card'),
            ]);
    }
}
