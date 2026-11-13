<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product.name')->label('Product')->disabled()->dehydrated(false),
                TextInput::make('user.name')->label('Customer')->disabled()->dehydrated(false),
                TextInput::make('rating')->disabled()->dehydrated(false),
                TextInput::make('title')->disabled()->dehydrated(false),
                Textarea::make('comment')->disabled()->dehydrated(false)->columnSpanFull(),
                Toggle::make('is_approved')->label('Approved (visible on the product page)'),
            ])
            ->columns(2);
    }
}
