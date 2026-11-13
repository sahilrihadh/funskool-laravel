<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Filament\Support\Avatar;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('User')
                    ->formatStateUsing(fn ($state, $record) => Avatar::html($state, $record->email))
                    ->html()
                    ->searchable(['name', 'email'])
                    ->sortable(),

                TextColumn::make('phone'),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => UserRole::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'danger',
                        'customer' => 'success',
                        default => 'warning',
                    }),

                IconColumn::make('marketing_opt_in')
                    ->label('Marketing')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('app_authentication_secret')
                    ->label('MFA')
                    ->boolean()
                    ->getStateUsing(fn ($record) => filled($record->app_authentication_secret))
                    ->visible(fn () => auth()->user()?->hasPermission('staff.manage'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label('Orders'),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(UserRole::options()),
            ])
            ->headerActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->outlined()
                    ->visible(fn () => auth()->user()?->hasPermission('customers.export'))
                    ->action(fn ($livewire) => $livewire->downloadCsv()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
