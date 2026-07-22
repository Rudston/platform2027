<?php

namespace App\Filament\Resources\CommentModerationRecords;

use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationFlagSource;
use App\Filament\Resources\CommentModerationRecords\Pages\ListCommentModerationRecords;
use App\Models\Moderation\CommentModerationRecord;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The unified comment moderation queue: AI- and user-flagged comments an admin
 * resolves via Approve / Hide / Delete. Under the Governance nav group, same
 * pattern as RequestResource.
 *
 * ACCESS: admin/superadmin only, unscoped — for now.
 * TODO (stewardship follow-up): scope getEloquentQuery() to circle_admins via
 * Circle::scopeManageableBy through the comment → forumDiscussion → forumGroup →
 * circle chain, and admit circle_admins in canViewAny(), so they see records for
 * their own circles' forums (mirrors RequestResource's role-scoped query).
 */
class CommentModerationRecordResource extends Resource
{
    protected static ?string $model = CommentModerationRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Governance';

    protected static ?string $navigationLabel = 'Comment Moderation';

    public static function canViewAny(): bool
    {
        return (bool) static::authUser()?->hasAnyRole(['admin', 'superadmin']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['comment.user', 'moderator']);
    }

    /** The current panel user as an App\Models\User (or null). */
    protected static function authUser(): ?User
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),

                TextColumn::make('flagged_by')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ($state instanceof ModerationFlagSource ? $state : ModerationFlagSource::from((string) $state))->label())
                    ->color(fn ($state): string => match ($state instanceof ModerationFlagSource ? $state->value : (string) $state) {
                        ModerationFlagSource::Ai->value => 'info',
                        ModerationFlagSource::User->value => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('comment.user.name')->label('Author')->placeholder('—'),

                TextColumn::make('content')
                    ->label('Content (at flag time)')
                    ->limit(60)
                    ->tooltip(fn (CommentModerationRecord $record): ?string => $record->content),

                // Only AI records carry a message; user records show '—'.
                TextColumn::make('ai_message')
                    ->label('AI note')
                    ->limit(60)
                    ->placeholder('—')
                    ->tooltip(fn (CommentModerationRecord $record): ?string => $record->ai_message),

                // The author's edit-since, for before/after comparison.
                TextColumn::make('moderated_content')
                    ->label("Author's fix")
                    ->limit(60)
                    ->placeholder('—')
                    ->tooltip(fn (CommentModerationRecord $record): ?string => $record->moderated_content),

                IconColumn::make('fixed_by_author')->label('Fixed')->boolean(),

                IconColumn::make('moderated')->label('Resolved')->boolean(),

                TextColumn::make('moderation_action')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state): string => $state instanceof ModerationAction ? $state->label() : (string) $state)
                    ->color(fn ($state): string => match ($state instanceof ModerationAction ? $state->value : (string) $state) {
                        ModerationAction::Approved->value => 'success',
                        ModerationAction::Hidden->value => 'warning',
                        ModerationAction::Deleted->value => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('flagged_by')->options([
                    ModerationFlagSource::Ai->value => 'AI',
                    ModerationFlagSource::User->value => 'User',
                ]),
                TernaryFilter::make('moderated')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Resolved')
                    ->falseLabel('Pending'),
            ])
            ->recordActions([
                static::approveAction(),
                static::hideAction(),
                static::deleteRecordAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Approve — the comment stands; only this record is resolved. */
    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (CommentModerationRecord $record): bool => ! $record->moderated)
            ->requiresConfirmation()
            ->modalHeading('Approve comment')
            ->modalDescription('Marks this flag as OK. The comment is left as-is.')
            ->action(function (CommentModerationRecord $record): void {
                /** @var User $admin */
                $admin = auth()->user();
                $record->resolveApproved($admin);

                Notification::make()->title('Comment approved')->success()->send();
            });
    }

    /** Hide — remove the comment (and its replies) from the thread. */
    protected static function hideAction(): Action
    {
        return Action::make('hide')
            ->label('Hide')
            ->icon('heroicon-o-eye-slash')
            ->color('warning')
            ->visible(fn (CommentModerationRecord $record): bool => ! $record->moderated)
            ->requiresConfirmation()
            ->modalHeading('Hide comment')
            ->modalDescription('Hides the comment and its replies from the thread. This cannot be undone here.')
            ->action(function (CommentModerationRecord $record): void {
                /** @var User $admin */
                $admin = auth()->user();
                $record->resolveHidden($admin);

                Notification::make()->title('Comment hidden')->success()->send();
            });
    }

    /** Delete — remove the comment (tombstone if it has replies). */
    protected static function deleteRecordAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (CommentModerationRecord $record): bool => ! $record->moderated)
            ->requiresConfirmation()
            ->modalHeading('Delete comment')
            ->modalDescription('Deletes the comment. If it has replies it is tombstoned so they still resolve; otherwise it is removed outright.')
            ->action(function (CommentModerationRecord $record): void {
                /** @var User $admin */
                $admin = auth()->user();
                $record->resolveDeleted($admin);

                Notification::make()->title('Comment deleted')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommentModerationRecords::route('/'),
        ];
    }
}
