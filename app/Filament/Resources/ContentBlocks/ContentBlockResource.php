<?php

namespace App\Filament\Resources\ContentBlocks;

use App\Filament\Resources\ContentBlocks\Pages\CreateContentBlock;
use App\Filament\Resources\ContentBlocks\Pages\EditContentBlock;
use App\Filament\Resources\ContentBlocks\Pages\ListContentBlocks;
use App\Models\ContentBlock;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ContentBlockResource extends Resource
{
    protected static ?string $model = ContentBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    /**
     * Supported locales — read from config, never hardcoded.
     *
     * @return list<string>
     */
    protected static function locales(): array
    {
        return array_values((array) config('app.supported_locales', []));
    }

    /** Human-readable label for a locale code (e.g. "en" => "English"). */
    protected static function localeLabel(string $locale): string
    {
        if (extension_loaded('intl')) {
            return \Locale::getDisplayName($locale, (string) config('app.fallback_locale', 'en')) ?: $locale;
        }

        return $locale;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->maxLength(150)
                    ->unique(ignoreRecord: true)
                    // Read-only after creation: the key is the stable lookup handle.
                    ->disabledOn('edit')
                    ->helperText('Stable identifier used in <x-content-block key="…" />. Cannot be changed after creation.'),

                TextInput::make('description')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_html')
                    ->label('Rich HTML content')
                    ->default(true)
                    // Live so the editor type below switches immediately.
                    ->live()
                    ->helperText('On: rich text (HTML) editor. Off: plain-text editor.'),

                Tabs::make('content')
                    ->columnSpanFull()
                    ->tabs(array_map(
                        fn (string $locale): Tab => Tab::make(static::localeLabel($locale))
                            ->schema([
                                // Both editors bind to the same translatable path
                                // (content.{locale}); only one shows at a time,
                                // toggled live by is_html.
                                RichEditor::make("content.{$locale}")
                                    ->label('Content')
                                    ->visible(fn (Get $get): bool => (bool) $get('is_html')),
                                Textarea::make("content.{$locale}")
                                    ->label('Content')
                                    ->rows(8)
                                    ->visible(fn (Get $get): bool => ! (bool) $get('is_html')),
                            ]),
                        static::locales(),
                    )),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(50),

                // One badge per locale: checkmark if content exists, dash if not.
                ...array_map(
                    fn (string $locale): IconColumn => IconColumn::make("content_{$locale}")
                        ->label(static::localeLabel($locale))
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-minus')
                        // Treat markup/whitespace-only (e.g. an empty RichEditor's
                        // "<p></p>") as "no content" so the badge is accurate.
                        ->state(fn (ContentBlock $record): bool => filled(trim(strip_tags((string) $record->getTranslation('content', $locale, false))))),
                    static::locales(),
                ),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentBlocks::route('/'),
            'create' => CreateContentBlock::route('/create'),
            'edit' => EditContentBlock::route('/{record}/edit'),
        ];
    }
}
