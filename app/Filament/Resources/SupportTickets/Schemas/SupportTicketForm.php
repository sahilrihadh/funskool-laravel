<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Enums\TicketPriority;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/** Staff logging a ticket on a customer's behalf (phone, walk-in, social media). */
class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        Select::make('user_id')
                            ->label('Existing customer')
                            ->helperText('Optional — fills in the contact details.')
                            ->options(fn () => User::query()->where('role', 'customer')->orderBy('name')->limit(500)->get()->mapWithKeys(fn (User $user) => [$user->id => "{$user->name} ({$user->email})"]))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($user = User::query()->find($state)) {
                                    $set('name', $user->name);
                                    $set('email', $user->email);
                                    $set('phone', $user->phone);
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')->email()->required()->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(20),
                        Select::make('order_id')
                            ->label('Related order')
                            ->options(fn () => Order::query()->latest()->limit(300)->pluck('order_number', 'id'))
                            ->searchable(),
                    ])
                    ->columns(2),

                Section::make('Request')
                    ->schema([
                        TextInput::make('subject')->required()->maxLength(255)->columnSpanFull(),
                        Select::make('category')->options(SupportTicket::CATEGORIES)->default('general')->required(),
                        Select::make('priority')->options(TicketPriority::options())->default('medium')->required(),
                        Textarea::make('message')->label('What the customer needs')->required()->rows(6)->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
