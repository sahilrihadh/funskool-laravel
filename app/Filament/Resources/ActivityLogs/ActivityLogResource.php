<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Read-only: audit entries can't be edited or deleted from the panel. */
class ActivityLogResource extends Resource
{
    use ChecksPermissions;

    protected static string $permissionArea = 'audit';

    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('user.name')->label('Staff')->placeholder('System'),
            TextEntry::make('action')->badge(),
            TextEntry::make('ip_address')->placeholder('—'),
            TextEntry::make('subject_type')->label('Record')->formatStateUsing(fn ($state, $record) => class_basename($state).' #'.$record->subject_id)->placeholder('—'),
            TextEntry::make('description')->placeholder('—'),
            TextEntry::make('changes')
                ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->fontFamily('mono')
                ->columnSpanFull(),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Staff')->placeholder('System / customer')->searchable(),
                TextColumn::make('action')->badge()->color(fn ($state) => match ($state) {
                    'deleted', 'login_failed', 'refund' => 'danger',
                    'export', 'api_key_rotated', 'webhook_secret_rotated' => 'warning',
                    'created' => 'success',
                    default => 'gray',
                }),
                TextColumn::make('subject_type')->label('Record')
                    ->formatStateUsing(fn ($state, $record) => class_basename($state).' #'.$record->subject_id)
                    ->placeholder('—'),
                TextColumn::make('description')->limit(60)->placeholder('—'),
                TextColumn::make('ip_address')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->options(fn () => ActivityLog::query()->distinct()->orderBy('action')->pluck('action', 'action')),
                SelectFilter::make('subject_type')->label('Record type')
                    ->options(fn () => ActivityLog::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type')->mapWithKeys(fn ($type) => [$type => class_basename($type)])),
                SelectFilter::make('user_id')->label('Staff')->relationship('user', 'name', fn ($query) => $query->where('role', '!=', 'customer')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
