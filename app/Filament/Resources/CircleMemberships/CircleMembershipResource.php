<?php

namespace App\Filament\Resources\CircleMemberships;

use App\Filament\Resources\CircleMemberships\Pages\ListCircleMemberships;
use App\Models\Circles\CircleMembership;
use BackedEnum;
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
 * Read-only history of every circle membership (open and closed). Memberships
 * are created/closed through the app's join/leave flow — never edited here.
 */
class CircleMembershipResource extends Resource
{
    protected static ?string $model = CircleMembership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Governance';

    protected static ?string $navigationLabel = 'Memberships';

    /** Admin/superadmin only (mirrors the other Governance/Platform resources). */
    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return (bool) $user?->hasAnyRole(['admin', 'superadmin']);
    }

    /** Eager-load the relations shown in the table (avoid N+1). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'circle']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),

                TextColumn::make('circle.name')
                    ->label('Circle')
                    ->searchable(),

                TextColumn::make('internal_role')
                    ->label('Internal role')
                    ->placeholder('—')
                    ->badge(),

                IconColumn::make('active')
                    ->label('Active')
                    ->state(fn (CircleMembership $record): bool => $record->left_at === null)
                    ->boolean(),

                TextColumn::make('joined_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('left_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('circle_id')
                    ->label('Circle')
                    ->relationship('circle', 'name')
                    ->searchable()
                    ->preload(false),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(false),

                TernaryFilter::make('active')
                    ->label('Membership status')
                    ->placeholder('All')
                    ->trueLabel('Active (not left)')
                    ->falseLabel('Closed (left)')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('left_at'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('left_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->defaultSort('joined_at', 'desc');
    }

    public static function getPages(): array
    {
        // List-only: read-only history, no create/edit/view pages.
        return [
            'index' => ListCircleMemberships::route('/'),
        ];
    }
}
