<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Leave empty to generate from the title. Changing it breaks existing links.'),
                        RichEditor::make('content')->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Publishing')
                    ->schema([
                        Select::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])->default('draft')->required(),
                        DateTimePicker::make('published_at')->label('Publish at')->helperText('Optional — schedule for later.'),
                        Toggle::make('show_in_footer')->label('Link in the storefront footer'),
                        TextInput::make('sort_order')->numeric()->default(0),
                    ])
                    ->columns(4),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('meta_title')->maxLength(255),
                        TextInput::make('meta_description')->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
