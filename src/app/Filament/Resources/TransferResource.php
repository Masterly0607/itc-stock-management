<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferResource\Pages;
use App\Models\Transfer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class TransferResource extends BaseResource
{
    protected static ?string $model = Transfer::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort = 15;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Distributor', 'Admin', 'Super Admin']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('from_branch_id')->relationship('fromBranch', 'name')->required(),
            Forms\Components\Select::make('to_branch_id')->relationship('toBranch', 'name')->required(),
            Forms\Components\TextInput::make('ref_no'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ref_no')->label('Ref')->searchable(),
                Tables\Columns\TextColumn::make('fromBranch.name')->label('From'),
                Tables\Columns\TextColumn::make('toBranch.name')->label('To'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Action::make('dispatch')
                    ->label('Dispatch')->icon('heroicon-o-truck')->color('success')
                    ->visible(fn(Transfer $r) => $r->status === 'DRAFT')
                    ->requiresConfirmation()
                    ->action(function (Transfer $record) {
                        try {
                            app(\App\Services\TransferService::class)->dispatch($record->id, auth()->id());
                            \Filament\Notifications\Notification::make()
                                ->title('Dispatched')->body('Stock moved out from source.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Dispatch failed')->body($e->getMessage())
                                ->danger()->send();
                        }
                    }),

                Action::make('receive')
                    ->label('Receive')->icon('heroicon-o-inbox-arrow-down')->color('success')
                    ->visible(fn(Transfer $r) => $r->status === 'DISPATCHED')
                    ->requiresConfirmation()
                    ->action(function (Transfer $record) {
                        try {
                            app(\App\Services\TransferService::class)->receive($record->id, auth()->id());
                            \Filament\Notifications\Notification::make()
                                ->title('Received')->body('Stock posted into destination.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Receive failed')->body($e->getMessage())
                                ->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTransfers::route('/'),

            'edit'   => Pages\EditTransfer::route('/{record}/edit'),
        ];
    }
}
