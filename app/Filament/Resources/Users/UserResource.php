<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionArea = 'customers';

    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Customers';
    }

    /**
     * Staff accounts (anyone who can sign in to this panel) are only editable
     * with staff.manage, and nobody can delete their own account.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;
        $user = auth()->user();

        if ($record instanceof User && ! in_array($action, ['view', 'viewAny'], true)) {
            if ($record->isStaff() && ! $user?->hasPermission('staff.manage')) {
                return Response::deny('Only staff managers can change staff accounts.');
            }

            if ($action === 'delete' && $record->is($user)) {
                return Response::deny('You cannot delete your own account.');
            }
        }

        if ($action === 'viewAny' && $user?->hasPermission('staff.manage')) {
            return Response::allow();
        }

        return static::checkPermissionArea($action);
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
