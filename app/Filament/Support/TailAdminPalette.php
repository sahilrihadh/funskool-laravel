<?php

namespace App\Filament\Support;

use Filament\Support\Colors\Color;

/**
 * Exact colour scales from the TailAdmin design system (tailadmin.com),
 * converted to the OKLCH format Filament expects.
 */
class TailAdminPalette
{
    public const BRAND = [
        50 => '#ecf3ff', 100 => '#dde9ff', 200 => '#c2d6ff', 300 => '#9cb9ff', 400 => '#7592ff',
        500 => '#465fff', 600 => '#3641f5', 700 => '#2a31d8', 800 => '#252dae', 900 => '#262e89', 950 => '#161950',
    ];

    public const GRAY = [
        50 => '#f9fafb', 100 => '#f2f4f7', 200 => '#e4e7ec', 300 => '#d0d5dd', 400 => '#98a2b3',
        500 => '#667085', 600 => '#475467', 700 => '#344054', 800 => '#1d2939', 900 => '#101828', 950 => '#0c111d',
    ];

    public const SUCCESS = [
        50 => '#ecfdf3', 100 => '#d1fadf', 200 => '#a6f4c5', 300 => '#6ce9a6', 400 => '#32d583',
        500 => '#12b76a', 600 => '#039855', 700 => '#027a48', 800 => '#05603a', 900 => '#054f31', 950 => '#053321',
    ];

    public const ERROR = [
        50 => '#fef3f2', 100 => '#fee4e2', 200 => '#fecdca', 300 => '#fda29b', 400 => '#f97066',
        500 => '#f04438', 600 => '#d92d20', 700 => '#b42318', 800 => '#912018', 900 => '#7a271a', 950 => '#55160c',
    ];

    public const WARNING = [
        50 => '#fffaeb', 100 => '#fef0c7', 200 => '#fedf89', 300 => '#fec84b', 400 => '#fdb022',
        500 => '#f79009', 600 => '#dc6803', 700 => '#b54708', 800 => '#93370d', 900 => '#7a2e0e', 950 => '#4e1d09',
    ];

    public const BLUE_LIGHT = [
        50 => '#f0f9ff', 100 => '#e0f2fe', 200 => '#b9e6fe', 300 => '#7cd4fd', 400 => '#36bffa',
        500 => '#0ba5ec', 600 => '#0086c9', 700 => '#026aa2', 800 => '#065986', 900 => '#0b4a6f', 950 => '#062c41',
    ];

    /** @return array<string, array<int, string>> */
    public static function colors(): array
    {
        $oklch = fn (array $scale) => array_map(fn (string $hex) => Color::convertToOklch($hex), $scale);

        return [
            'primary' => $oklch(self::BRAND),
            'gray' => $oklch(self::GRAY),
            'success' => $oklch(self::SUCCESS),
            'danger' => $oklch(self::ERROR),
            'warning' => $oklch(self::WARNING),
            'info' => $oklch(self::BLUE_LIGHT),
        ];
    }
}
