<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Product')
                    ->tabs([
                        Tabs\Tab::make('Basic Info')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state)))
                                    ->maxLength(255),

                                TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),

                                Select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                Select::make('brand_id')
                                    ->label('Brand')
                                    ->relationship('brand', 'name')
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50),

                                TextInput::make('barcode')
                                    ->maxLength(50),

                                Textarea::make('short_description')
                                    ->maxLength(500)
                                    ->columnSpanFull(),

                                RichEditor::make('description')
                                    ->columnSpanFull()
                                    ->fileAttachmentsDirectory('products/descriptions'),
                            ])
                            ->columns(2),

                        Tabs\Tab::make('Pricing & Inventory')
                            ->schema([
                                TextInput::make('regular_price')
                                    ->label('Regular Price (₹)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₹')
                                    ->default(0),

                                TextInput::make('sale_price')
                                    ->label('Sale Price (₹)')
                                    ->numeric()
                                    ->prefix('₹')
                                    ->nullable(),

                                TextInput::make('stock_quantity')
                                    ->label('Stock Quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('low_stock_threshold')
                                    ->label('Low Stock Threshold')
                                    ->helperText('Product is flagged as low stock at or below this quantity.')
                                    ->numeric()
                                    ->default(5),

                                TextInput::make('weight')
                                    ->label('Weight (kg)')
                                    ->numeric()
                                    ->helperText('Used for future shipping rate calculations.'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Toggle::make('is_featured')
                                    ->label('Featured Product')
                                    ->default(false),
                            ])
                            ->columns(2),

                        Tabs\Tab::make('Images')
                            ->schema([
                                FileUpload::make('image')
                                    ->label('Main Product Image')
                                    ->image()
                                    ->directory('products')
                                    ->imageResizeMode('cover')
                                    ->imageResizeTargetWidth('800')
                                    ->imageResizeTargetHeight('800')
                                    ->columnSpanFull(),

                                Repeater::make('images')
                                    ->label('Gallery Images')
                                    ->relationship('images')
                                    ->schema([
                                        FileUpload::make('image_path')
                                            ->label('Image')
                                            ->image()
                                            ->directory('products/gallery')
                                            ->required(),

                                        TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(0),
                                    ])
                                    ->columns(2)
                                    ->orderColumn('sort_order')
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->addActionLabel('Add gallery image')
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Variants')
                            ->schema([
                                Repeater::make('variants')
                                    ->label('Variants')
                                    ->relationship('variants')
                                    ->schema([
                                        TextInput::make('sku')
                                            ->label('Variant SKU')
                                            ->required(),

                                        KeyValue::make('attributes')
                                            ->label('Attributes')
                                            ->keyLabel('Attribute (e.g. Size)')
                                            ->valueLabel('Value (e.g. M)')
                                            ->columnSpanFull(),

                                        TextInput::make('price')
                                            ->label('Price Override (₹)')
                                            ->numeric()
                                            ->prefix('₹')
                                            ->helperText('Leave empty to use the product price.'),

                                        TextInput::make('stock_quantity')
                                            ->label('Stock')
                                            ->numeric()
                                            ->default(0),

                                        FileUpload::make('image')
                                            ->image()
                                            ->directory('products/variants'),

                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true),
                                    ])
                                    ->columns(3)
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->addActionLabel('Add variant')
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Also Available On')
                            ->schema([
                                Repeater::make('marketplaceLinks')
                                    ->label('External Marketplace Links')
                                    ->relationship('marketplaceLinks')
                                    ->helperText('Show buyers where else this product is listed (Amazon, Flipkart, etc). Links appear on the product detail page.')
                                    ->schema([
                                        Select::make('platform')
                                            ->options([
                                                'Funskool India' => 'Funskool India (Official)',
                                                'Amazon' => 'Amazon',
                                                'Flipkart' => 'Flipkart',
                                                'Meesho' => 'Meesho',
                                                'Myntra' => 'Myntra',
                                                'Other' => 'Other',
                                            ])
                                            ->required()
                                            ->searchable(),

                                        TextInput::make('url')
                                            ->label('Product URL')
                                            ->url()
                                            ->required()
                                            ->maxLength(500),

                                        TextInput::make('sort_order')
                                            ->numeric()
                                            ->default(0),

                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true),
                                    ])
                                    ->columns(4)
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->addActionLabel('Add marketplace link')
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('SEO')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->maxLength(255),

                                Textarea::make('meta_description')
                                    ->maxLength(500),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
