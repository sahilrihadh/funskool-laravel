@php
    use App\Enums\TicketStatus;
    use App\Enums\TicketPriority;
    use App\Filament\Support\Avatar;
@endphp

<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- Conversation --}}
        <div class="ta-card overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-white/10">
                <div class="min-w-0">
                    <h3 class="text-lg font-medium text-gray-800 dark:text-white/90">
                        Ticket #{{ $ticket->ticket_number }} - {{ $ticket->subject }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $ticket->created_at->format('D, g:i A') }} ({{ $ticket->created_at->diffForHumans() }})
                    </p>
                </div>

                @if ($pager['position'])
                    <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ $pager['position'] }} of {{ $pager['total'] }}</span>
                        <div class="flex gap-2">
                            <a @if ($pager['previous']) href="{{ $pager['previous'] }}" @endif aria-label="Newer ticket"
                               @class(['ta-icon-btn', 'pointer-events-none opacity-40' => ! $pager['previous']])>
                                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronLeft) }}
                            </a>
                            <a @if ($pager['next']) href="{{ $pager['next'] }}" @endif aria-label="Older ticket"
                               @class(['ta-icon-btn', 'pointer-events-none opacity-40' => ! $pager['next']])>
                                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronRight) }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($ticket->messages as $message)
                    <article @class([
                        'px-6 py-5',
                        'bg-warning-50/70 dark:bg-warning-500/5' => $message->is_internal,
                    ])>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                {!! Avatar::circle($message->author_name, 'size-12 text-base') !!}
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-white/90">
                                        {{ $message->author_name }}
                                        @if ($message->is_staff)
                                            <x-filament::badge size="sm" color="primary" class="ms-1 inline-flex">Staff</x-filament::badge>
                                        @endif
                                        @if ($message->is_internal)
                                            <x-filament::badge size="sm" color="warning" class="ms-1 inline-flex">Internal note</x-filament::badge>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $message->author_email }}</p>
                                </div>
                            </div>
                            <time class="text-xs text-gray-500 dark:text-gray-400" datetime="{{ $message->created_at->toIso8601String() }}"
                                  title="{{ $message->created_at->format('d M Y, g:i A') }}">
                                {{ $message->created_at->format('D, g:i A') }} ({{ $message->created_at->diffForHumans() }})
                            </time>
                        </div>

                        <div class="mt-4 text-sm leading-6 whitespace-pre-line text-gray-600 dark:text-gray-300">{{ $message->body }}</div>

                        @if (! empty($message->attachments))
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($message->attachments as $index => $file)
                                    <a href="{{ route('support.attachment', [$message, $index]) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">
                                        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedPaperClip, attributes: new \Filament\Support\View\ComponentAttributeBag(['class' => 'size-4'])) }}
                                        {{ \Illuminate\Support\Str::limit($file['name'] ?? 'attachment', 32) }}
                                        <span class="text-gray-400">{{ \Illuminate\Support\Number::fileSize($file['size'] ?? 0) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            @if ($canReply && $ticket->status !== TicketStatus::Closed->value)
                <form wire:submit="sendReply" class="border-t border-gray-200 p-6 dark:border-white/10">
                    {{ $this->form }}

                    <div class="mt-4 flex justify-end">
                        <x-filament::button type="submit" icon="heroicon-m-paper-airplane" icon-position="after" wire:loading.attr="disabled">
                            Reply
                        </x-filament::button>
                    </div>
                </form>
            @elseif ($ticket->status === TicketStatus::Closed->value)
                <p class="border-t border-gray-200 px-6 py-5 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    This ticket is closed. Reopen it to reply.
                </p>
            @endif
        </div>

        {{-- Ticket details --}}
        <aside class="ta-card h-fit">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-white/10">
                <h3 class="text-lg font-medium text-gray-800 dark:text-white/90">Ticket Details</h3>
            </div>
            <dl class="space-y-4 px-6 py-5 text-sm">
                @php
                    $rows = [
                        'Customer' => $ticket->name,
                        'Email' => $ticket->email,
                        'Phone' => $ticket->phone,
                        'Ticket ID' => '#'.$ticket->ticket_number,
                        'Category' => $ticket->categoryLabel(),
                        'Created' => $ticket->created_at->format('M d, Y'),
                        'Source' => $ticket->salesChannel?->name ?? ucfirst($ticket->source),
                        'Assigned to' => $ticket->assignee?->name ?? 'Unassigned',
                        'First reply' => $ticket->first_response_at ? $ticket->created_at->diffForHumans($ticket->first_response_at, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]).' after opening' : 'Not yet',
                    ];
                @endphp

                @foreach ($rows as $label => $value)
                    @continue(blank($value))
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="text-end font-medium break-all text-gray-800 dark:text-white/90">{{ $value }}</dd>
                    </div>
                @endforeach

                <div class="flex items-center justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Priority</dt>
                    <dd><x-filament::badge :color="TicketPriority::tryFrom($ticket->priority)?->getColor() ?? 'gray'">{{ TicketPriority::tryFrom($ticket->priority)?->getLabel() ?? $ticket->priority }}</x-filament::badge></dd>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                    <dd><x-filament::badge :color="TicketStatus::colorFor($ticket->status)">{{ TicketStatus::labelFor($ticket->status) }}</x-filament::badge></dd>
                </div>

                @if ($ticket->order)
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Order</dt>
                        <dd>
                            <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $ticket->order]) }}"
                               class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $ticket->order->order_number }}</a>
                        </dd>
                    </div>
                @endif

                @if ($ticket->user)
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400">Account</dt>
                        <dd>
                            <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $ticket->user]) }}"
                               class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $ticket->user->orders()->count() }} order(s)</a>
                        </dd>
                    </div>
                @endif
            </dl>
        </aside>
    </div>
</x-filament-panels::page>
