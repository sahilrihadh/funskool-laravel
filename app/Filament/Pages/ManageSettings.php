<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChecksPermissions;
use App\Models\ActivityLog;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageSettings extends Page
{
    use ChecksPermissions;

    protected static string $permissionArea = 'settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Store Settings';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return static::userCan('settings.view');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Configuration';
    }

    public function mount(): void
    {
        $this->form->fill(Setting::getMany([
            'store_name',
            'store_email',
            'store_phone',
            'store_address',
            'logo',
            'currency_code',
            'currency_symbol',
            'tax_percent',
            'facebook_url',
            'instagram_url',
            'twitter_url',
            'tax_registration_number',
            'order_number_prefix',
            'return_window_days',
            'order_alert_email',
            'low_stock_alert_email',
            'support_alert_email',
            'invoice_footer',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Store Information')
                    ->schema([
                        TextInput::make('store_name'),
                        TextInput::make('store_email')->email(),
                        TextInput::make('store_phone')->tel(),
                        TextInput::make('store_address'),
                        FileUpload::make('logo')->image()->directory('settings'),
                    ])
                    ->columns(2),

                Section::make('Currency & Tax')
                    ->schema([
                        TextInput::make('currency_code')->default('INR')->maxLength(3),
                        TextInput::make('currency_symbol')->default('₹')->maxLength(5),
                        TextInput::make('tax_percent')
                            ->label('Global Tax (%)')
                            ->numeric()
                            ->default(0)
                            ->helperText('Applied to every order at checkout until per-category/region tax rules are added. Confirm GST treatment with your tax adviser.'),
                        TextInput::make('tax_registration_number')
                            ->label('GSTIN / Tax registration no.')
                            ->helperText('Printed on invoices.'),
                    ])
                    ->columns(4),

                Section::make('Orders, Returns & Alerts')
                    ->schema([
                        TextInput::make('order_number_prefix')->default('ORD')->maxLength(10)->alphaDash()
                            ->helperText('e.g. FSK → FSK-20261024-AB12CD'),
                        TextInput::make('return_window_days')->label('Return window (days after delivery)')->numeric()->default(7)->minValue(0),
                        TextInput::make('order_alert_email')->label('New-order alert email')->email()
                            ->helperText('Staff inbox notified of every new order.'),
                        TextInput::make('low_stock_alert_email')->label('Low-stock alert email')->email()
                            ->helperText('Falls back to the new-order email.'),
                        TextInput::make('support_alert_email')->label('Support ticket alert email')->email()
                            ->helperText('Optional shared inbox for new tickets and customer replies (staff also get in-panel notifications).'),
                        TextInput::make('invoice_footer')->label('Invoice footer text')->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Social Links')
                    ->schema([
                        TextInput::make('facebook_url')->url(),
                        TextInput::make('instagram_url')->url(),
                        TextInput::make('twitter_url')->url(),
                    ])
                    ->columns(3),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label('Save')->submit('save')->visible(fn () => static::userCan('settings.manage')),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::userCan('settings.manage'), 403);

        $state = $this->form->getState();
        $before = Setting::getMany(array_keys($state));
        Setting::setMany($state);

        $changes = collect($state)
            ->filter(fn ($value, $key) => (string) $value !== (string) ($before[$key] ?? ''))
            ->map(fn ($value, $key) => ['old' => $before[$key] ?? null, 'new' => $value])
            ->all();

        if ($changes !== []) {
            ActivityLog::record('settings_updated', null, $changes, 'Store settings changed');
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
