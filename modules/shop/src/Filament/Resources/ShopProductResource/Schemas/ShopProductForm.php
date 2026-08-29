<?php

declare(strict_types=1);

namespace Modules\Shop\Filament\Resources\ShopProductResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Modules\Shop\Models\ShopProduct;

class ShopProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('商品信息')
                    ->schema([
                        TextInput::make('name')
                            ->label('商品名称')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set, $operation) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state) ?: 'product-'.Str::random(6));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('路径（slug）')
                            ->required()
                            ->alphaDash()
                            ->maxLength(220)
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->label('状态')
                            ->options(['draft' => '草稿', 'on_sale' => '已上架', 'off_sale' => '已下架'])
                            ->default('draft')
                            ->required()
                            ->helperText('上架后前台商品页可见并可购买'),
                        TextInput::make('category')
                            ->label('商品分类')
                            ->maxLength(50)
                            ->placeholder('主题 / 插件 / 服务…')
                            ->helperText('前台按此分类筛选；留空归入"其他"'),
                        TextInput::make('stock')
                            ->label('库存')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('价格与展示')
                    ->schema([
                        TextInput::make('price')
                            ->label('价格（USD）')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01),
                        TextInput::make('image_url')
                            ->label('商品图片')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                        \Filament\Forms\Components\RichEditor::make('description')
                            ->label('商品介绍')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'link', 'blockquote', 'bulletList', 'orderedList',
                                'h2', 'h3', 'undo', 'redo',
                            ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('交付设置')
                    ->description('支付成功后自动交付给买家的内容；"无"表示仅扣库存无数字交付。')
                    ->schema([
                        Select::make('delivery_type')
                            ->label('交付类型')
                            ->options([
                                'none' => '无（仅扣库存）',
                                'download' => '下载链接（买家获得下载地址）',
                                'content' => '文本内容（买家获得隐藏文本，如激活码）',
                                'invite_code' => '邀请码（自动生成并归属买家）',
                            ])
                            ->default('none')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('delivery_payload')
                            ->label('下载链接')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('delivery_type') === 'download'),
                        Textarea::make('delivery_payload')
                            ->label('交付内容')
                            ->rows(4)
                            ->maxLength(3000)
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('delivery_type') === 'content')
                            ->helperText('支付成功后展示给买家的文本，如激活码、配置信息等'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
