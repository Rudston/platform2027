<?php

namespace App\Filament\Resources\Requests;

use App\Enums\CircleStatus;
use App\Enums\CommunityType;
use App\Filament\Resources\Requests\Pages\ListRequests;
use App\Filament\Resources\Requests\Pages\ViewRequest;
use App\Models\Circles\Circle;
use App\Models\Communication\Request;
use App\Models\User;
use App\Services\Communication\EmailServiceHandler;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Throwable;
use UnitEnum;

class RequestResource extends Resource
{
    protected static ?string $model = Request::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Governance';

    /** @var array<string, string> */
    protected const TYPE_OPTIONS = [
        'organisation_approval' => 'Organisation Approval',
        'circle_join' => 'Circle Join',
        'location_request' => 'Location Request',
        'circle_association' => 'Circle Association',
    ];

    /** @var array<string, string> */
    protected const STATUS_OPTIONS = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'denied' => 'Denied',
        'expired' => 'Expired',
    ];

    /** @var array<string, string> */
    protected const DIRECTION_OPTIONS = [
        'external' => 'External',
        'internal' => 'Internal',
    ];

    /**
     * Visible to global admins/superadmins and to any circle_admin (a
     * team-scoped role, so checked across all teams). Circle admins see a
     * scoped subset — see getEloquentQuery(). canAccess() defaults to this, so
     * it also gates the resource's pages.
     */
    public static function canViewAny(): bool
    {
        $user = static::authUser();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'superadmin'])
            || Circle::administeredBy($user)->isNotEmpty();
    }

    /**
     * Eager-load the relations shown in the table/view, and scope by role.
     *
     * This query is the single choke point for BOTH listing and record
     * resolution (Filament binds route records through it), so a circle_admin
     * can neither list nor open a request outside their scope.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['requester', 'circle', 'responsibleAdmin']);

        $user = static::authUser();

        // Global admins see every request; a circle_admin sees only requests
        // directed to them (responsible_admin_id) or within a circle they
        // administer — that circle or any descendant of it (subtree via path).
        if ($user !== null && ! $user->hasAnyRole(['admin', 'superadmin'])) {
            $administered = Circle::administeredBy($user);

            $query->where(function (Builder $q) use ($user, $administered): void {
                $q->where('responsible_admin_id', $user->id);

                if ($administered->isNotEmpty()) {
                    $q->orWhereHas('circle', function (Builder $c) use ($administered): void {
                        $c->where(function (Builder $inner) use ($administered): void {
                            foreach ($administered as $circle) {
                                $inner->orWhere('circles.id', $circle->id)
                                    ->orWhere('circles.path', 'like', $circle->path.'/%');
                            }
                        });
                    });
                }
            });
        }

        return $query;
    }

    /** The current panel user as an App\Models\User (or null). */
    protected static function authUser(): ?User
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * Whether the current user may act (Approve/Deny/Resend) on this request.
     * Global admins/superadmins may act on any; a circle_admin only on requests
     * directed to them or within a circle they administer (matches the listing
     * scope in getEloquentQuery). Mirrors Circle::isNestedIn for the subtree
     * test, consistent with the app's path-based tree logic.
     */
    protected static function userMayActOn(Request $record): bool
    {
        $user = static::authUser();

        if ($user === null) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'superadmin'])) {
            return true;
        }

        if ($record->responsible_admin_id === $user->id) {
            return true;
        }

        $circle = $record->circle;

        if ($circle === null) {
            return false;
        }

        return Circle::administeredBy($user)->contains(
            fn (Circle $administered): bool => $circle->id === $administered->id
                || $circle->isNestedIn($administered),
        );
    }

    public static function form(Schema $schema): Schema
    {
        // View-only: admins act through the Approve/Deny/Resend actions, not by
        // editing raw fields. The View page renders these read-only.
        return $schema
            ->components([
                TextInput::make('type')
                    ->formatStateUsing(fn (?string $state): string => $state ? Str::headline($state) : '')
                    ->disabled(),

                TextInput::make('status')
                    ->formatStateUsing(fn (?string $state): string => $state ? Str::headline($state) : '')
                    ->disabled(),

                Placeholder::make('requester_name')
                    ->label('Requester name')
                    ->content(fn (Request $record): string => $record->requester?->name ?? '—'),

                Placeholder::make('requester_email')
                    ->label('Requester email')
                    ->content(fn (Request $record): string => $record->requester?->email ?? '—'),

                Placeholder::make('circle_name')
                    ->label('Circle')
                    ->content(fn (Request $record): string => $record->circle?->name ?? '—'),

                Placeholder::make('responsible_admin_name')
                    ->label('Responsible admin')
                    ->helperText('Notified of this request. Any admin/superadmin can still act on it.')
                    ->content(fn (Request $record): string => $record->responsibleAdmin?->name ?? '—'),

                TextInput::make('respondent_email')
                    ->disabled(),

                Textarea::make('response_note')
                    ->rows(3)
                    ->disabled(),

                DateTimePicker::make('responded_at')
                    ->disabled(),

                DateTimePicker::make('token_expires_at')
                    ->disabled(),

                Placeholder::make('email_log')
                    ->label('Email log')
                    ->columnSpanFull()
                    ->content(fn (Request $record): HtmlString => static::renderEmailLog($record)),
            ]);
    }

    /** Render the metadata.email_log as a small read-only table. */
    protected static function renderEmailLog(Request $record): HtmlString
    {
        $log = $record->metadata['email_log'] ?? [];

        if (empty($log)) {
            return new HtmlString('<span class="text-sm text-gray-500">No emails logged.</span>');
        }

        $rows = '';
        foreach ($log as $entry) {
            $rows .= '<tr>'
                .'<td class="pr-4 py-1 align-top">'.e($entry['template'] ?? '').'</td>'
                .'<td class="pr-4 py-1 align-top">'.e($entry['recipient'] ?? '').'</td>'
                .'<td class="pr-4 py-1 align-top whitespace-nowrap">'.e($entry['sent_at'] ?? '').'</td>'
                .'<td class="pr-4 py-1 align-top">'.e($entry['status'] ?? '').'</td>'
                .'<td class="py-1 align-top">'.e($entry['error'] ?? '').'</td>'
                .'</tr>';
        }

        return new HtmlString(
            '<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr>'
            .'<th class="pr-4 text-left font-semibold">Template</th>'
            .'<th class="pr-4 text-left font-semibold">Recipient</th>'
            .'<th class="pr-4 text-left font-semibold">Sent at</th>'
            .'<th class="pr-4 text-left font-semibold">Status</th>'
            .'<th class="text-left font-semibold">Error</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Governance actions (manual approve / deny / resend)
    |--------------------------------------------------------------------------
    */

    /** Manually approve a pending request: activate the circle, grant the role, notify. */
    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Request $record): bool => $record->status === 'pending' && static::userMayActOn($record))
            ->requiresConfirmation()
            ->modalHeading('Approve request')
            ->modalDescription('Are you sure you want to manually approve this request? An email will be sent to the requester.')
            ->modalSubmitActionLabel('Approve')
            ->action(function (Request $record): void {
                DB::transaction(function () use ($record): void {
                    $record->update([
                        'status' => 'approved',
                        'responded_at' => now(),
                    ]);

                    $record->circle?->update(['status' => CircleStatus::Active]);

                    if ($record->requester && $record->circle_id) {
                        app(PermissionRegistrar::class)->setPermissionsTeamId($record->circle_id);
                        $record->requester->assignRole('circle_admin');

                        // Also give the community's creator an active membership
                        // (direct grant — not a rate-limited join). Organisation
                        // creators are labelled organisation_member.
                        $creatorRole = $record->circle?->circleable_type === CommunityType::Organisation->value
                            ? 'organisation_member'
                            : null;
                        $record->circle?->joinAsMember($record->requester, internalRole: $creatorRole, skipChecks: true);
                    }
                });

                $sent = static::attemptEmail(
                    $record,
                    'email.organisation_approval_confirmed',
                    $record->requester?->email,
                    [
                        'requester_name' => (string) ($record->requester?->name ?? ''),
                        'organisation_name' => (string) ($record->requestable?->name ?? ''),
                        'community_url' => $record->circle
                            ? route('communities.show', $record->circle)
                            : url('/'),
                    ],
                );

                static::notify($sent, 'Request approved', 'Request approved — email not sent');
            });
    }

    /** Manually deny a pending request (optional note). Circle stays unchanged. */
    protected static function denyAction(): Action
    {
        return Action::make('deny')
            ->label('Deny')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Request $record): bool => $record->status === 'pending' && static::userMayActOn($record))
            ->schema([
                Textarea::make('response_note')
                    ->label('Reason (optional)')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->modalHeading('Deny request')
            ->modalDescription('The requester will be notified that the request was not approved.')
            ->modalSubmitActionLabel('Deny')
            ->action(function (Request $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    $record->update([
                        'status' => 'denied',
                        'responded_at' => now(),
                        'response_note' => $data['response_note'] ?? null,
                    ]);
                    // Circle / organisation status intentionally left unchanged.
                });

                $sent = static::attemptEmail(
                    $record,
                    'email.organisation_approval_denied',
                    $record->requester?->email,
                    [
                        'requester_name' => (string) ($record->requester?->name ?? ''),
                        'organisation_name' => (string) ($record->requestable?->name ?? ''),
                    ],
                );

                static::notify($sent, 'Request denied', 'Request denied — email not sent');
            });
    }

    /** Regenerate the token/expiry and resend the approval email to the respondent. */
    protected static function resendAction(): Action
    {
        return Action::make('resend')
            ->label('Resend')
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->visible(fn (Request $record): bool => in_array($record->status, ['pending', 'expired'], true) && static::userMayActOn($record))
            ->requiresConfirmation()
            ->modalHeading('Resend approval email')
            ->modalDescription('This regenerates the approval link (valid for 7 days) and resends it to the respondent.')
            ->modalSubmitActionLabel('Resend')
            ->action(function (Request $record): void {
                $record->update([
                    'token' => Str::random(64),
                    'token_expires_at' => now()->addDays(7),
                    'status' => $record->status === 'expired' ? 'pending' : $record->status,
                ]);

                $sent = static::attemptEmail(
                    $record,
                    'email.organisation_approval_request',
                    $record->respondent_email,
                    [
                        'contact_name' => (string) ($record->metadata['contact_name'] ?? ''),
                        'organisation_name' => (string) ($record->requestable?->name ?? ''),
                        'requester_name' => (string) ($record->requester?->name ?? ''),
                        'review_url' => route('requests.confirm', $record->token),
                        'expires_at' => $record->token_expires_at->format('d M Y'),
                    ],
                );

                static::notify($sent, 'Approval email resent', 'Could not resend the approval email');
            });
    }

    /**
     * Send one template email, logging the outcome on the request. Never throws.
     *
     * @param  array<string, string|int|float>  $variables
     */
    protected static function attemptEmail(Request $record, string $template, ?string $recipient, array $variables): bool
    {
        if (! $recipient) {
            $record->logEmail($template, '', 'failed', 'No recipient address.');

            return false;
        }

        try {
            app(EmailServiceHandler::class)->sendTemplate($template, $recipient, $variables);
            $record->logEmail($template, $recipient, 'sent');

            return true;
        } catch (Throwable $e) {
            $record->logEmail($template, $recipient, 'failed', $e->getMessage());

            return false;
        }
    }

    /** Success notification when the email sent, warning otherwise. */
    protected static function notify(bool $sent, string $successTitle, string $warningTitle): void
    {
        Notification::make()
            ->title($sent ? $successTitle : $warningTitle)
            ->body($sent ? 'The email was sent.' : 'The action completed, but the email could not be sent. See the request email log.')
            ->{$sent ? 'success' : 'warning'}()
            ->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->color(fn (string $state): string => match ($state) {
                        'organisation_approval' => 'info',
                        'circle_join' => 'success',
                        'location_request' => 'warning',
                        'circle_association' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'denied' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string|array => $state === 'external' ? Color::Indigo : 'gray')
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label('Requester')
                    ->searchable(),

                TextColumn::make('circle.name')
                    ->label('Circle')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('responsibleAdmin.name')
                    ->label('Responsible admin')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('respondent_email')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('responded_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('token_expires_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(static::STATUS_OPTIONS),
                SelectFilter::make('type')->options(static::TYPE_OPTIONS),
                SelectFilter::make('direction')->options(static::DIRECTION_OPTIONS),
                Filter::make('mine')
                    ->label('Assigned to me')
                    ->query(fn (Builder $query): Builder => $query->where('responsible_admin_id', auth()->id())),
            ])
            ->recordActions([
                ViewAction::make(),
                static::approveAction(),
                static::denyAction(),
                static::resendAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRequests::route('/'),
            'view' => ViewRequest::route('/{record}'),
        ];
    }
}
