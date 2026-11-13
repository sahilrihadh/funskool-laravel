{{-- One-click light/dark switch in the header (TailAdmin round icon button). Uses Filament's own
     theme store, so it stays in sync with the Light / Dark / System picker in the user menu. --}}
<button
    type="button"
    x-data
    x-on:click="$dispatch('theme-changed', $store.theme === 'dark' ? 'light' : 'dark')"
    x-bind:aria-label="$store.theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
    x-tooltip="{ content: $store.theme === 'dark' ? 'Light mode' : 'Dark mode', theme: $store.theme }"
    class="ta-header-btn"
>
    <span x-show="$store.theme !== 'dark'">
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedMoon) }}
    </span>
    <span x-show="$store.theme === 'dark'" x-cloak>
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSun) }}
    </span>
</button>
