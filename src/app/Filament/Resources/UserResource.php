<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Branch;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int    $navigationSort  = 90;

    public static function shouldRegisterNavigation(): bool
    {
        // Users menu visible to SA & Admin; Distributor hidden
        return auth()->user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /** Scope what rows are visible */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        $me = auth()->user();

        if (! $me) {
            return $q->whereRaw('1=0');
        }

        if ($me->hasRole('Super Admin')) {
            return $q;
        }

        // Admin: only users within same province; exclude Super Admins
        $provinceId = $me->branch?->province_id;

        return $q
            ->whereHas('branch', fn(Builder $b) => $b->where('province_id', $provinceId))
            ->whereDoesntHave('roles', fn(Builder $r) => $r->where('name', 'Super Admin'));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(100),

            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $context): bool => $context === 'create'),

            // Branch restricted by role
            // Branch restricted by role (and hide HQ / inactive)
            FormSelect::make('branch_id')
                ->label('Branch')
                ->options(function () {
                    $me = auth()->user();
                    if (! $me) return [];

                    $q = Branch::query()
                        ->where('is_active', true)     // hide inactive branches
                        ->orderBy('name');

                    if (! $me->hasRole('Super Admin')) {
                        // Admin: same province only, no HQ
                        $q->where('province_id', $me->branch?->province_id)
                            ->where('type', '!=', 'HQ');
                    }

                    return $q->pluck('name', 'id');
                })
                ->searchable()
                ->preload()
                ->required()
                // Prefill admin with their own branch when creating:
                ->default(fn() => auth()->user()?->hasRole('Admin') ? auth()->user()?->branch_id : null)
                // On edit, Admin cannot move users between branches:
                ->disabled(
                    fn(?\App\Models\User $record) =>
                    auth()->user()?->hasRole('Admin') && filled($record),
                ),



            // Single role UX via virtual field `role`
            // use statements stay the same

            FormSelect::make('role')
                ->label('Role')
                ->options(function () {
                    $me = auth()->user();
                    if ($me?->hasRole('Super Admin')) {
                        return Role::orderBy('name')->pluck('name', 'name'); // keys are role NAMES
                    }
                    return Role::whereIn('name', ['Admin', 'Distributor'])
                        ->orderBy('name')
                        ->pluck('name', 'name');
                })
                ->native(false)
                ->required()
                ->dehydrated(false) // ← important: virtual field (not saved to users table)
                ->afterStateHydrated(function ($component, ?\App\Models\User $record) {
                    // When EDITING, prefill the select with the user's current role
                    if ($record) {
                        $component->state($record->getRoleNames()->first());
                    }
                }),



            Forms\Components\Toggle::make('status')
                ->label('Is Active')
                ->default(false) // false = INACTIVE
                ->afterStateHydrated(function ($component, $state, $set) {
                    // DB: ACTIVE/INACTIVE → Toggle: true/false
                    $set('status', $state === 'ACTIVE');
                })
                ->dehydrateStateUsing(function ($state) {
                    // Toggle: true/false → DB: ACTIVE/INACTIVE
                    return $state ? 'ACTIVE' : 'INACTIVE';
                })
                ->dehydrated(true)

        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'ACTIVE',
                        'warning' => 'INACTIVE',
                    ]),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state),
            ])
            ->actions([
                EditAction::make()
                    ->visible(function (User $record) {
                        $me = auth()->user();
                        if (! $me) return false;
                        if ($me->hasRole('Super Admin')) return true;

                        // Admin cannot edit Super Admins and only within same province
                        $sameProvince = $record->branch?->province_id === $me->branch?->province_id;
                        $notSA = ! $record->hasRole('Super Admin');

                        return $me->hasRole('Admin') && $sameProvince && $notSA;
                    }),

                DeleteAction::make()
                    ->visible(fn() => auth()->user()?->hasRole('Super Admin') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn() => auth()->user()?->hasRole('Super Admin') ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }
    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAnyRole('Super Admin', 'Admin') ?? false;
    }
}
