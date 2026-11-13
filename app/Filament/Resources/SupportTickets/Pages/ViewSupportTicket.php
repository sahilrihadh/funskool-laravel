<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\StaffNotifier;
use App\Services\SupportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/** TailAdmin-style "Ticket Reply" screen: thread + reply box + details panel. */
class ViewSupportTicket extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SupportTicketResource::class;

    protected string $view = 'filament.support.ticket-reply';

    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->form->fill(['status' => $this->defaultReplyStatus(), 'internal' => false]);
    }

    public function hydrate(): void
    {
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    protected function ticket(): SupportTicket
    {
        return $this->getRecord();
    }

    protected function canReply(): bool
    {
        return (bool) auth()->user()?->hasPermission('support.manage');
    }

    public function getTitle(): string|Htmlable
    {
        return "Ticket #{$this->ticket()->ticket_number}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            SupportTicketResource::getUrl() => 'Support Tickets',
            '#' => "#{$this->ticket()->ticket_number}",
        ];
    }

    protected function defaultReplyStatus(): string
    {
        return in_array($this->ticket()->status, [TicketStatus::OnHold->value, TicketStatus::Solved->value], true)
            ? $this->ticket()->status
            : TicketStatus::InProgress->value;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Textarea::make('body')
                    ->hiddenLabel()
                    ->placeholder('Type your reply here…')
                    ->rows(6)
                    ->required(),
                FileUpload::make('attachments')
                    ->hiddenLabel()
                    ->multiple()
                    ->maxFiles(3)
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/*', 'application/pdf', 'text/plain'])
                    ->disk(SupportService::ATTACHMENT_DISK)
                    ->directory('support-attachments/'.$this->ticket()->id)
                    ->visibility('private')
                    ->storeFileNamesIn('attachment_names')
                    ->panelLayout('compact')
                    ->placeholder('Attach files (images, PDF — max 5 MB each)'),
                Radio::make('status')
                    ->label('Set status')
                    ->options([
                        TicketStatus::InProgress->value => 'In-Progress',
                        TicketStatus::Solved->value => 'Solved',
                        TicketStatus::OnHold->value => 'On-Hold',
                    ])
                    ->inline(),
                Toggle::make('internal')
                    ->label('Internal note (not sent to the customer)'),
            ]);
    }

    public function sendReply(SupportService $support): void
    {
        abort_unless($this->canReply(), 403);

        $data = $this->form->getState();

        $support->staffReply(
            $this->ticket(),
            auth()->user(),
            $data['body'],
            $data['internal'] ? null : ($data['status'] ?? null),
            (bool) $data['internal'],
            collect($data['attachments'] ?? [])->map(fn (string $path) => ['path' => $path, 'name' => $data['attachment_names'][$path] ?? basename($path)])->values()->all(),
        );

        $this->record->refresh();
        $this->form->fill(['status' => $this->defaultReplyStatus(), 'internal' => false]);

        Notification::make()
            ->title($data['internal'] ? 'Internal note added' : 'Reply sent to '.$this->ticket()->email)
            ->success()
            ->send();
    }

    /** Position among all tickets (newest first) for the "4 of 120" pager. */
    protected function pager(): array
    {
        $ids = SupportTicket::query()->orderByDesc('id')->pluck('ticket_number', 'id');
        $keys = $ids->keys()->values();
        $index = $keys->search($this->ticket()->id);

        return [
            'position' => $index === false ? null : $index + 1,
            'total' => $keys->count(),
            'previous' => $index > 0 ? SupportTicketResource::getUrl('view', ['record' => $ids[$keys[$index - 1]]]) : null,
            'next' => $index !== false && $index < $keys->count() - 1 ? SupportTicketResource::getUrl('view', ['record' => $ids[$keys[$index + 1]]]) : null,
        ];
    }

    protected function getViewData(): array
    {
        return [
            'ticket' => $this->ticket()->load(['messages', 'order', 'assignee', 'salesChannel', 'user']),
            'pager' => $this->pager(),
            'canReply' => $this->canReply(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')
                ->label(fn () => $this->ticket()->assigned_to ? 'Reassign' : 'Assign')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->visible(fn () => $this->canReply())
                ->schema([
                    Select::make('assigned_to')
                        ->label('Assign to')
                        ->options(fn () => app(StaffNotifier::class)->staffWith('support.view')->pluck('name', 'id'))
                        ->default(fn () => $this->ticket()->assigned_to)
                        ->placeholder('Unassigned'),
                ])
                ->action(function (array $data, SupportService $support) {
                    $support->assign($this->ticket(), $data['assigned_to'] ? User::query()->find($data['assigned_to']) : null);
                    $this->record->refresh();
                    Notification::make()->title('Assignment updated')->success()->send();
                }),

            Action::make('details')
                ->label('Edit details')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn () => $this->canReply())
                ->fillForm(fn () => $this->ticket()->only(['category', 'priority']))
                ->schema([
                    Select::make('category')->options(SupportTicket::CATEGORIES)->required(),
                    Select::make('priority')->options(TicketPriority::options())->required(),
                ])
                ->action(function (array $data) {
                    $this->ticket()->update($data);
                    Notification::make()->title('Ticket updated')->success()->send();
                }),

            Action::make('close')
                ->label('Close ticket')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Closed tickets cannot be replied to by the customer. They can open a new one.')
                ->visible(fn () => $this->canReply() && $this->ticket()->status !== TicketStatus::Closed->value)
                ->action(function (SupportService $support) {
                    $support->setStatus($this->ticket(), TicketStatus::Closed->value);
                    Notification::make()->title('Ticket closed')->success()->send();
                }),

            Action::make('reopen')
                ->label('Reopen')
                ->icon('heroicon-o-lock-open')
                ->color('gray')
                ->visible(fn () => $this->canReply() && $this->ticket()->isResolved())
                ->action(fn (SupportService $support) => $support->setStatus($this->ticket(), TicketStatus::Open->value)),
        ];
    }
}
