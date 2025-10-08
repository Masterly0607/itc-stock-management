<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Filament\Support\Concerns\HasCrudPermissions;
use App\Models\Branch;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;
    use HasCrudPermissions;
    protected static string $permPrefix = 'branches';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 1;
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $q) {
                $q->with([
                    // only strings here — no closures on nested relations
                    'province',
                    'district',
                    'users.roles',                // roles for users in this branch
                    'province.mainBranch.users.roles', // roles for users on the province's main branch
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('province.name')->label('Province'),
                Tables\Columns\TextColumn::make('district.name')->label('District'),

                Tables\Columns\TextColumn::make('users.roles.name')
                    ->label('Roles')->badge()->separator(', ')->toggleable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')->label('Users'),

                Tables\Columns\TextColumn::make('admin_display')
                    ->label('Admin')
                    ->getStateUsing(function (Branch $record) {
                        // If this is the Head Office, show Super Admin instead of "No Admin"
                        if ($record->code === 'HQ' || strtolower($record->name) === 'head office') {
                            return '🟢 Super Admin';
                        }

                        // Load needed relations
                        $record->loadMissing('province.mainBranch.users.roles', 'users.roles');

                        // Determine the main branch for the province
                        $mainBranch = $record->district_id === null
                            ? $record
                            : $record->province?->mainBranch;

                        if (! $mainBranch) return '⚠️ No Main Branch';

                        // Find the province Admin
                        $admin = $mainBranch->users->first(fn($u) => $u->hasRole('Admin'));
                        if (! $admin) return '⚠️ No Admin';

                        $label = $admin->name ?: $admin->email;
                        return $admin->is_active ? '🟢 ' . $label : '🔴 ' . $label . ' (Inactive)';
                    }),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit'   => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
