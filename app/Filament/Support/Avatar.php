<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/** TailAdmin-style "avatar + name + email" cell, using initials instead of photos. */
class Avatar
{
    protected const TONES = [
        'bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-400',
        'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
        'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
        'bg-info-50 text-info-600 dark:bg-info-500/15 dark:text-info-400',
        'bg-danger-50 text-danger-600 dark:bg-danger-500/15 dark:text-danger-400',
    ];

    public static function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr(count($parts) > 1 ? end($parts) : '', 0, 1));
    }

    public static function circle(?string $name, string $size = 'size-10 text-sm'): string
    {
        $tone = self::TONES[crc32((string) $name) % count(self::TONES)];

        return '<span class="ta-avatar '.$size.' '.$tone.'">'.e(self::initials($name)).'</span>';
    }

    public static function html(?string $name, ?string $secondary = null): HtmlString
    {
        return new HtmlString(
            '<div class="ta-person">'.self::circle($name).'<div class="min-w-0">'
            .'<span class="ta-person-name">'.e($name ?: '—').'</span>'
            .($secondary ? '<span class="ta-person-meta">'.e($secondary).'</span>' : '')
            .'</div></div>'
        );
    }
}
