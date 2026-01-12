<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferResource\Pages;
use App\Models\Transfer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class TransferResource extends BaseResource
{
    protected static ?string $model = Transfer::class;
    protected static ?string $navigationIcon  = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort  = 30;

    /** Who can see the menu / list page */
    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Super Admin', 'Admin', 'Distributor']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /** Only Super Admin can create manual transfers */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        $u    = auth()->user();
        $isSA = $u?->hasRole('Super Admin') ?? false;

        return $form->schema([
            Select::make('from_branch_id')
                ->label('From (Source)')
                ->relationship('fromBranch', 'name')
                ->required()
                ->disabled(! $isSA) // Only SA can choose / edit
                ->dehydrated(true),

            Select::make('to_branch_id')
                ->label('To (Destination)')
                ->relationship('toBranch', 'name')
                ->required()
                ->disabled(! $isSA)
                ->dehydrated(true),

            TextInput::make('ref_no')
                ->label('Ref')
                ->maxLength(50)
                ->columnSpanFull(),

            Repeater::make('items')
                ->relationship('items')
                ->schema([
                    Select::make('product_id')
                        ->relationship('product', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),

                    Select::make('unit_id')
                        ->relationship('unit', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(1),

                    TextInput::make('qty')
                        ->label('Qty')
                        ->numeric()
                        ->minValue(0.001)
                        ->step('0.001')
                        ->required()
                        ->columnSpan(1),
                ])
                ->columns(4)
                ->minItems(1)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('ref_no')
                    ->label('Ref')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fromBranch.name')
                    ->label('From')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('toBranch.name')
                    ->label('To')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'DRAFT',
                        'warning' => 'DISPATCHED',
                        'success' => 'RECEIVED',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(
                        fn(Transfer $record) =>
                        auth()->user()?->hasRole('Super Admin')
                            && $record->status === 'DRAFT'
                    ),

                // DISPATCH: source OUT
                Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->visible(function (Transfer $record): bool {
                        $u = auth()->user();

                        if (! $u || $record->status !== 'DRAFT') {
                            return false;
                        }

                        // Super Admin can always dispatch
                        if ($u->hasRole('Super Admin')) {
                            return true;
                        }

                        // OPTIONAL: allow Admin/Distributor at source branch to dispatch as well
                        if ($u->hasAnyRole(['Admin', 'Distributor']) && $u->branch_id) {
                            return $u->branch_id === $record->from_branch_id;
                        }

                        return false;
                    })
                    ->requiresConfirmation()
                    ->action(function (Transfer $record) {
                        try {
                            app(\App\Services\TransferService::class)->dispatch($record);

                            \Filament\Notifications\Notification::make()
                                ->title('Transfer dispatched')
                                ->body("Transfer #{$record->id} dispatched.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            // Show *why* dispatch failed (e.g. insufficient stock)
                            \Filament\Notifications\Notification::make()
                                ->title('Dispatch failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // RECEIVE: destination IN
                Action::make('receive')
                    ->label('Receive')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (Transfer $record): bool {
                        $u = auth()->user();

                        if (! $u || $record->status !== 'DISPATCHED') {
                            return false;
                        }

                        // Only Admin/Distributor at destination branch
                        if (! $u->hasAnyRole(['Admin', 'Distributor'])) {
                            return false;
                        }

                        return $u->branch_id
                            && $u->branch_id === $record->to_branch_id;
                    })
                    ->requiresConfirmation()
                    ->action(function (Transfer $record) {
                        try {
                            app(\App\Services\TransferService::class)->receive($record);

                            \Filament\Notifications\Notification::make()
                                ->title('Transfer received')
                                ->body("Transfer #{$record->id} received by destination branch.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Receive failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    /**
     * SA  -> all transfers
     * Admin / Distributor -> transfers where their branch is FROM or TO
     */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['fromBranch', 'toBranch']);
        $u = auth()->user();

        if (! $u) {
            return $q->whereRaw('1 = 0');
        }

        if ($u->hasRole('Super Admin')) {
            return $q;
        }

        if ($u->branch_id) {
            return $q->where(function (Builder $sub) use ($u) {
                $sub->where('from_branch_id', $u->branch_id)
                    ->orWhere('to_branch_id', $u->branch_id);
            });
        }

        return $q->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'edit'   => Pages\EditTransfer::route('/{record}/edit'),
        ];
    }
}
