<?php

namespace App\Filament\Resources\ReturnRequests;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\ReturnRequests\Pages\EditReturnRequest;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\ReturnRequests\Schemas\ReturnRequestForm;
use App\Filament\Resources\ReturnRequests\Tables\ReturnRequestsTable;
use App\Models\ReturnRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ReturnRequestResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionArea = 'orders';

    protected static ?string $model = ReturnRequest::class;

    protected static ?string $navigationLabel = 'Returns';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Sales';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ReturnRequest::query()->whereIn('status', ['requested', 'approved', 'received'])->count() ?: null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ReturnRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReturnRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturnRequests::route('/'),
            'edit' => EditReturnRequest::route('/{record}/edit'),
        ];
    }
}
