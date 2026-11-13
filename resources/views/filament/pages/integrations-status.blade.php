<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Credentials are read from the server's <code>.env</code> file only — they are never stored in the database or shown here.
            The full list, with where to get each one, is in <code>required-resource.txt</code>.
        </p>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($groups as $group)
            @php $ready = collect($group['keys'])->every(fn ($set) => $set); @endphp
            <x-filament::section>
                <x-slot name="heading">
                    <span class="flex flex-wrap items-center gap-2">
                        {{ $group['name'] }}
                        <x-filament::badge :color="$ready ? 'success' : ($group['required'] ? 'danger' : 'gray')">
                            {{ $ready ? 'Configured' : ($group['required'] ? 'Action needed' : 'Not set up') }}
                        </x-filament::badge>
                    </span>
                </x-slot>

                <ul class="space-y-1 text-sm">
                    @foreach ($group['keys'] as $key => $set)
                        <li class="flex items-center justify-between gap-3">
                            <code class="text-xs">{{ $key }}</code>
                            <x-filament::badge size="sm" :color="$set ? 'success' : 'danger'">{{ $set ? 'set' : 'missing' }}</x-filament::badge>
                        </li>
                    @endforeach
                </ul>

                @if (! empty($group['note']))
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ $group['note'] }}</p>
                @endif

                @if (! empty($group['webhook']))
                    <div class="mt-3 rounded-lg bg-gray-50 p-3 text-xs dark:bg-white/5">
                        <div class="font-semibold text-gray-700 dark:text-gray-200">Webhook URL to register</div>
                        <code class="break-all select-all">{{ $group['webhook'] }}</code>
                        <div class="mt-1 text-gray-500 dark:text-gray-400">{{ $group['webhook_hint'] }}</div>
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Connected websites">
        @forelse ($channels as $channel)
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/10">
                <span class="font-medium">{{ $channel->name }}</span>
                <span class="flex gap-2">
                    <x-filament::badge :color="$channel->is_active ? 'success' : 'gray'">{{ $channel->is_active ? 'Active' : 'Inactive' }}</x-filament::badge>
                    <x-filament::badge :color="$channel->api_key_prefix ? 'success' : 'danger'">{{ $channel->api_key_prefix ? 'API key issued' : 'No API key' }}</x-filament::badge>
                    @if ($channel->failed_count)
                        <x-filament::badge color="danger">{{ $channel->failed_count }} failed webhooks</x-filament::badge>
                    @endif
                </span>
            </div>
        @empty
            <p class="text-sm text-gray-600 dark:text-gray-400">No external websites connected yet. Add one under Integrations → Connected Websites.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
