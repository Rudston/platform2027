<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Models\Communication\EmailTemplate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Communication';

    /**
     * Admin/superadmin only. The panel is now reachable by circle_admins too
     * (see User::canAccessPanel), so this resource must gate itself explicitly
     * — canAccess() defaults to canViewAny(), so this covers nav + all pages.
     */
    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return (bool) $user?->hasAnyRole(['admin', 'superadmin']);
    }

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

    /**
     * True when the given locale has BOTH a subject and a body (ignoring
     * markup/whitespace-only values). Fallback locale is not consulted — we
     * report on this locale's own content.
     */
    protected static function localeIsComplete(EmailTemplate $record, string $locale): bool
    {
        $subject = trim(strip_tags((string) $record->getTranslation('subject', $locale, false)));
        $body = trim(strip_tags((string) $record->getTranslation('body', $locale, false)));

        return $subject !== '' && $body !== '';
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
                    ->helperText('Stable identifier used in code to send this template. Cannot be changed after creation.'),

                TextInput::make('description')
                    ->maxLength(255)
                    ->helperText('Admin-facing note describing when this template is used.'),

                Toggle::make('is_html')
                    ->label('Rich HTML content')
                    ->default(true)
                    // Live so the body editor type below switches immediately.
                    ->live()
                    ->helperText('On: rich text (HTML) editor. Off: plain-text editor.'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive templates cannot be sent.'),

                // Read-only: the variable whitelist is maintained by developers,
                // not editable here. Shown as chips for reference.
                TagsInput::make('available_variables')
                    ->label('Available variables')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Set by developers. Reference these as {{ variable_name }} in the subject or body.'),

                Tabs::make('translations')
                    ->columnSpanFull()
                    ->tabs(array_map(
                        fn (string $locale): Tab => Tab::make(static::localeLabel($locale))
                            ->schema([
                                TextInput::make("subject.{$locale}")
                                    ->label('Subject')
                                    ->maxLength(255),

                                // Both editors bind to the same translatable path
                                // (body.{locale}); only one shows at a time,
                                // toggled live by is_html.
                                RichEditor::make("body.{$locale}")
                                    ->label('Body')
                                    ->visible(fn (Get $get): bool => (bool) $get('is_html')),
                                Textarea::make("body.{$locale}")
                                    ->label('Body')
                                    ->rows(10)
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

                // One badge per locale: "Complete" (green) when both subject and
                // body exist for that locale, "Missing" (grey) otherwise.
                ...array_map(
                    fn (string $locale): TextColumn => TextColumn::make("locale_status_{$locale}")
                        ->label(static::localeLabel($locale))
                        ->badge()
                        ->state(fn (EmailTemplate $record): string => static::localeIsComplete($record, $locale) ? 'Complete' : 'Missing')
                        ->color(fn (EmailTemplate $record): string => static::localeIsComplete($record, $locale) ? 'success' : 'gray'),
                    static::locales(),
                ),

                ToggleColumn::make('is_active'),

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
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
