<?php

namespace App\Filament\Resources\SalesChannels;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\SalesChannels\Pages\CreateSalesChannel;
use App\Filament\Resources\SalesChannels\Pages\EditSalesChannel;
use App\Filament\Resources\SalesChannels\Pages\ListSalesChannels;
use App\Filament\Resources\SalesChannels\RelationManagers\WebhookDeliveriesRelationManager;
use App\Filament\Resources\SalesChannels\Schemas\SalesChannelForm;
use App\Filament\Resources\SalesChannels\Tables\SalesChannelsTable;
use App\Models\SalesChannel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalesChannelResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionArea = 'integrations';

    protected static ?string $model = SalesChannel::class;

    protected static ?string $navigationLabel = 'Connected Websites';

    protected static ?string $modelLabel = 'connected website';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Integrations';
    }

    public static function form(Schema $schema): Schema
    {
        return SalesChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesChannelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            WebhookDeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesChannels::route('/'),
            'create' => CreateSalesChannel::route('/create'),
            'edit' => EditSalesChannel::route('/{record}/edit'),
        ];
    }
}
