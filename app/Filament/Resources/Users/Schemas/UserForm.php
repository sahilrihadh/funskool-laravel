<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(20),

                Select::make('role')
                    ->options(fn () => auth()->user()?->hasPermission('staff.manage')
                        ? collect(UserRole::options())->when(! auth()->user()->isAdmin(), fn ($options) => $options->except(UserRole::Admin->value))->all()
                        : [UserRole::Customer->value => UserRole::Customer->getLabel()])
                    ->helperText('Staff roles grant admin-panel access. Permissions per role: config/permissions.php.')
                    ->required()
                    ->default('customer'),

                Toggle::make('marketing_opt_in')
                    ->label('Marketing consent')
                    ->helperText('Only change this on the customer\'s explicit request — consent is timestamped.')
                    ->default(false),

                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->minLength(8)
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('Leave empty to keep the current password.'),
            ])
            ->columns(2);
    }
}
