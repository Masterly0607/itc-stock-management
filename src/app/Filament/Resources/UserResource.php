<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\Concerns\HasCrudPermissions;
use App\Models\User;
use App\Models\Province;
use App\Models\District;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 2;


    use HasCrudPermissions;
    protected static string $permPrefix = 'users';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->label('Password')
                        ->required(fn(string $operation): bool => $operation === 'create')
                        ->dehydrateStateUsing(fn($state) => $state ? Hash::make($state) : null)
                        ->dehydrated(fn($state) => filled($state)),

                    // Select role
                    Forms\Components\Select::make('role')
                        ->label('Role')
                        ->options([
                            'Admin' => 'Admin',
                            'Distributor' => 'Distributor',
                        ])
                        ->live()
                        ->required(),

                    // Province selection
                    Forms\Components\Select::make('province_id')
                        ->label('Province')
                        ->options(function (Get $get) {
                            $user = Auth::user();

                            //  If Super Admin is creating an Admin
                            if ($get('role') === 'Admin' && $user->hasRole('Super Admin')) {
                                // show provinces that don’t already have an Admin
                                return \App\Models\Province::whereDoesntHave('mainBranch.users', fn($q) => $q->role('Admin'))
                                    ->pluck('name', 'id');
                            }

                            //  If Super Admin is creating a Distributor
                            if ($get('role') === 'Distributor' && $user->hasRole('Super Admin')) {
                                return \App\Models\Province::pluck('name', 'id');
                            }

                            //  If Admin (creating Distributor) → show only their own province
                            if ($user->hasRole('Admin')) {
                                return \App\Models\Province::where('id', $user->branch->province_id ?? null)->pluck('name', 'id');
                            }

                            //  Distributors can’t create anyone → no province
                            return [];
                        })
                        ->searchable()
                        ->required()
                        ->visible(fn(Get $get) => in_array($get('role'), ['Admin', 'Distributor']))
                        ->live(),


                    // District (only for Distributor, filtered by province)
                    Forms\Components\Select::make('district_id')
                        ->label('District')
                        ->options(
                            fn(Get $get) =>
                            $get('province_id')
                                ? District::where('province_id', $get('province_id'))->pluck('name', 'id')
                                : []
                        )
                        ->searchable()
                        ->required(fn(Get $get) => $get('role') === 'Distributor')
                        ->visible(fn(Get $get) => $get('role') === 'Distributor'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) =>
                $query->with(['branch.province', 'branch.district', 'roles'])
            )

            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('branch.province.name')->label('Province'),
                Tables\Columns\TextColumn::make('branch.district.name')->label('District'),
                Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge()->separator(', '),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('reactivated_at')
                    ->label('Reactivated At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active status')
                    ->trueLabel('Active')->falseLabel('Inactive')->placeholder('All')
                    ->queries(
                        true: fn(Builder $q) => $q->where('is_active', true),
                        false: fn(Builder $q) => $q->where('is_active', false),
                        blank: fn(Builder $q) => $q
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->is_active = ! $record->is_active;
                        $record->reactivated_at = $record->is_active ? now() : null;
                        $record->save();
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make(),
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
}
