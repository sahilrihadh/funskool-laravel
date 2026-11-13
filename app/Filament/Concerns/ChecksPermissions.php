<?php

namespace App\Filament\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Gates a Filament resource by permission area (config/permissions.php):
 * viewAny/view need "<area>.view", every write needs "<area>.manage".
 * Set `protected static string $permissionArea = 'catalog';` on the resource.
 */
trait ChecksPermissions
{
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        return static::checkPermissionArea($action instanceof UnitEnum ? $action->name : $action);
    }

    protected static function checkPermissionArea(string $action): Response
    {
        $ability = static::$permissionArea.'.'.(in_array($action, ['viewAny', 'view'], true) ? 'view' : 'manage');

        return auth()->user()?->hasPermission($ability)
            ? Response::allow()
            : Response::deny('You do not have permission to do that.');
    }

    public static function userCan(string $ability): bool
    {
        return (bool) auth()->user()?->hasPermission($ability);
    }
}
