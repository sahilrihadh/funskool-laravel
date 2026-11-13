<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials avatar rendered as an inline SVG in TailAdmin brand colours.
 * Replaces Filament's default, which loads images from ui-avatars.com
 * (and so sends staff names to a third party).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = e(Avatar::initials(Filament::getNameForDefaultAvatar($record)));

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#ecf3ff"/><text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Outfit, ui-sans-serif, system-ui, sans-serif" font-size="24" font-weight="600" fill="#3641f5">{$initials}</text></svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode(trim($svg));
    }
}
