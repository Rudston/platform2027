<?php

namespace App\Filament\Resources\ThemeSuggestions;

use App\Enums\ThemeSuggestionStatus;
use App\Filament\Resources\ThemeSuggestions\Pages\ListThemeSuggestions;
use App\Models\ThemeSuggestion;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Admin review of user-suggested tags. Global scope (no circle), admin/superadmin
 * only — same pattern as ContentBlockResource / EmailTemplateResource. Read-only
 * table + Approve / Reject row actions calling the model methods.
 */
class ThemeSuggestionResource extends Resource
{
    protected static ?string $model = ThemeSuggestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?string $navigationLabel = 'Tag suggestions';

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return (bool) $user?->hasAnyRole(['admin', 'superadmin']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['requestedBy', 'reviewedBy']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Suggested tag')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst($state instanceof ThemeSuggestionStatus ? $state->value : (string) $state))
                    ->color(fn ($state): string => match ($state instanceof ThemeSuggestionStatus ? $state->value : (string) $state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('requestedBy.name')->label('Suggested by')->placeholder('—'),
                TextColumn::make('origin_taggable_type')
                    ->label('From')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                TextColumn::make('reviewedBy.name')->label('Reviewed by')->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->recordActions([
                static::approveAction(),
                static::rejectAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ThemeSuggestion $record): bool => $record->status === ThemeSuggestionStatus::Pending)
            ->requiresConfirmation()
            ->modalHeading('Approve tag suggestion')
            ->modalDescription('Creates (or reuses) the tag and notifies the suggester. If it was raised while tagging something, the tag is attached there too.')
            ->action(function (ThemeSuggestion $record): void {
                /** @var User $reviewer */
                $reviewer = auth()->user();
                $record->approve($reviewer);

                Notification::make()->title('Tag suggestion approved')->success()->send();
            });
    }

    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (ThemeSuggestion $record): bool => $record->status === ThemeSuggestionStatus::Pending)
            ->schema([
                Textarea::make('review_note')
                    ->label('Reason')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->modalHeading('Reject tag suggestion')
            ->modalDescription('The suggester will be notified with your reason.')
            ->modalSubmitActionLabel('Reject')
            ->action(function (ThemeSuggestion $record, array $data): void {
                /** @var User $reviewer */
                $reviewer = auth()->user();
                $record->reject($reviewer, $data['review_note']);

                Notification::make()->title('Tag suggestion rejected')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThemeSuggestions::route('/'),
        ];
    }
}
