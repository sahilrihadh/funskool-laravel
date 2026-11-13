<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')->maxLength(50),
                TextInput::make('full_name')->required(),
                TextInput::make('phone')->tel()->required(),
                Select::make('type')
                    ->options(['shipping' => 'Shipping', 'billing' => 'Billing'])
                    ->required()
                    ->default('shipping'),
                TextInput::make('line1')->label('Address Line 1')->required()->columnSpanFull(),
                TextInput::make('line2')->label('Address Line 2')->columnSpanFull(),
                TextInput::make('city')->required(),
                TextInput::make('state')->required(),
                TextInput::make('postal_code')->required(),
                TextInput::make('country')->default('India')->required(),
                Toggle::make('is_default'),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('label')->placeholder('—'),
                TextColumn::make('full_name'),
                TextColumn::make('city'),
                TextColumn::make('type')->badge(),
                IconColumn::make('is_default')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
