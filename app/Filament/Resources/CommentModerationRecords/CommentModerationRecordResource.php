<?php

namespace App\Filament\Resources\CommentModerationRecords;

use App\Enums\Moderation\ModerationAction;
use App\Enums\Moderation\ModerationFlagSource;
use App\Filament\Resources\CommentModerationRecords\Pages\ListCommentModerationRecords;
use App\Filament\Resources\CommentModerationRecords\Pages\ViewCommentModerationRecord;
use App\Models\Circles\Circle;
use App\Models\Forums\ForumDiscussion;
use App\Models\Moderation\CommentModerationRecord;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * The unified comment moderation queue: AI- and user-flagged comments an admin
 * resolves via Approve / Hide / Delete. Under the Governance nav group, same
 * pattern as RequestResource.
 *
 * ACCESS: global admins/superadmins see everything; a circle_admin sees only
 * records whose comment lives in a circle they manage (comment → forumDiscussion
 * → forumGroup → circle), scoped in getEloquentQuery() — the single choke point
 * for both listing and record resolution.
 */
class CommentModerationRecordResource extends Resource
{
    protected static ?string $model = CommentModerationRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Governance';

    protected static ?string $navigationLabel = 'Comment Moderation';

    public static function canViewAny(): bool
    {
        $user = static::authUser();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'superadmin'])
            || Circle::administeredBy($user)->isNotEmpty();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['comment.user', 'moderator', 'circle']);

        $user = static::authUser();

        // A circle_admin sees only records whose comment lives in a circle they
        // manage; global admins/superadmins are unscoped.
        if ($user !== null && ! $user->hasAnyRole(['admin', 'superadmin'])) {
            $manageableCircleIds = Circle::query()->manageableBy($user)->pluck('id');

            $query->whereHas('comment', fn (Builder $c) => $c->whereHasMorph(
                'commentable',
                [ForumDiscussion::class],
                fn (Builder $d) => $d->whereHas('group', fn (Builder $g) => $g->whereIn('circle_id', $manageableCircleIds)),
            ));
        }

        return $query;
    }

    /** The current panel user as an App\Models\User (or null). */
    protected static function authUser(): ?User
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user;
    }

    /** Read-only detail (the View page the front-end "Pending Review" badge links to). */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('author')
                ->content(fn (CommentModerationRecord $record): string => $record->comment?->user?->name ?? '—'),
            Placeholder::make('flagged_by')
                ->label('Flagged by')
                ->content(fn (CommentModerationRecord $record): string => $record->flagged_by?->label() ?? '—'),
            Placeholder::make('circle')
                ->content(fn (CommentModerationRecord $record): string => $record->circle?->name ?? '—'),
            Placeholder::make('commentable_type_label')
                ->label('On')
                ->content(fn (CommentModerationRecord $record): string => $record->commentable_type_label ?? '—'),
            Placeholder::make('url_to_parent')
                ->label('Link')
                ->content(fn (CommentModerationRecord $record): string|HtmlString => $record->url_to_parent
                    ? new HtmlString('<a class="text-primary-600 underline" target="_blank" href="'.e($record->url_to_parent).'">'.e($record->url_to_parent).'</a>')
                    : '—'),
            Placeholder::make('content')
                ->label('Content (at flag time)')
                ->content(fn (CommentModerationRecord $record): string => $record->content),
            Placeholder::make('moderated_content')
                ->label("Author's fix")
                ->content(fn (CommentModerationRecord $record): string => $record->moderated_content ?? '—'),
            Placeholder::make('ai_message')
                ->label('AI note')
                ->content(fn (CommentModerationRecord $record): string => $record->ai_message ?? '—'),
            Placeholder::make('resolvedBy')
                ->label('Moderated by')
                ->content(fn (CommentModerationRecord $record): string => ! $record->moderated
                    ? 'Pending'
                    : ($record->moderator?->name ?? 'Auto-approved')),
        ]);
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

                // Snapshot audit fields. Circle falls back gracefully if the
                // circle was deleted (circle_id nulled).
                TextColumn::make('circle.name')->label('Circle')->placeholder('—'),

                TextColumn::make('commentable_type_label')->label('On')->placeholder('—'),

                TextColumn::make('url_to_parent')
                    ->label('Link')
                    ->placeholder('—')
                    ->limit(40)
                    ->url(fn (CommentModerationRecord $record): ?string => $record->url_to_parent, shouldOpenInNewTab: true)
                    ->color('primary'),

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
                        ModerationAction::EditedAndApproved->value => 'success',
                        ModerationAction::Hidden->value => 'warning',
                        ModerationAction::Deleted->value => 'danger',
                        default => 'gray',
                    }),

                // Resolver: a human's name, "Auto-approved" for a system-resolved
                // (moderated_by_user_id NULL) record, or "—" while still pending.
                TextColumn::make('resolvedBy')
                    ->label('Moderated by')
                    ->state(fn (CommentModerationRecord $record): string => ! $record->moderated
                        ? '—'
                        : ($record->moderator?->name ?? 'Auto-approved')),
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
                // Pre-fillable from the Oversight page via
                // CommentModerationRecord::filamentUrlForCircle().
                Filter::make('circle')
                    ->schema([
                        Select::make('circle_id')
                            ->label('Circle')
                            ->options(fn (): array => Circle::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $circleId = $data['circle_id'] ?? null;

                        if (blank($circleId)) {
                            return $query;
                        }

                        return $query->whereHas('comment', fn (Builder $c) => $c->whereHasMorph(
                            'commentable',
                            [ForumDiscussion::class],
                            fn (Builder $d) => $d->whereHas('group', fn (Builder $g) => $g->where('circle_id', (int) $circleId)),
                        ));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                static::approveAction(),
                static::editAndApproveAction(),
                static::hideAction(),
                static::deleteRecordAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Approve — the comment stands; only this record is resolved. */
    public static function approveAction(): Action
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

    /**
     * Edit & Approve — the admin fixes the wording, then approves. Pre-fills the
     * comment's CURRENT content (what's there now, not the original snapshot).
     */
    public static function editAndApproveAction(): Action
    {
        return Action::make('editAndApprove')
            ->label('Edit & Approve')
            ->icon('heroicon-o-pencil-square')
            ->color('success')
            ->visible(fn (CommentModerationRecord $record): bool => ! $record->moderated)
            ->fillForm(fn (CommentModerationRecord $record): array => [
                'content' => $record->comment?->content ?? '',
            ])
            ->schema([
                Textarea::make('content')
                    ->label('Comment content')
                    ->required()
                    ->rows(6),
            ])
            ->modalHeading('Edit & approve comment')
            ->modalDescription('Fix the wording and approve. This replaces the comment text and resolves the record — it is NOT re-checked automatically.')
            ->modalSubmitActionLabel('Save & approve')
            ->action(function (CommentModerationRecord $record, array $data): void {
                /** @var User $admin */
                $admin = auth()->user();
                $record->resolveEditedAndApproved($admin, $data['content']);

                Notification::make()->title('Comment edited & approved')->success()->send();
            });
    }

    /** Hide — remove the comment (and its replies) from the thread. */
    public static function hideAction(): Action
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
    public static function deleteRecordAction(): Action
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
            'view' => ViewCommentModerationRecord::route('/{record}'),
        ];
    }
}
