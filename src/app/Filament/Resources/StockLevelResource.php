<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLevelResource\Pages;
use App\Models\StockLevel;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;

class StockLevelResource extends BaseResource
{
    protected static ?string $model = StockLevel::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int    $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Admin', 'Super Admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')->sortable()->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')->sortable()->searchable(),

                // Use a computed state so we can safely fall back:
                Tables\Columns\TextColumn::make('unit_label')
                    ->label('Unit')
                    ->state(
                        fn($record) =>
                        $record->unit?->name
                            ?? $record->product?->baseUnit?->name
                            ?? '—'
                    ),

                Tables\Columns\TextColumn::make('qty')
                    ->label('On hand')
                    ->numeric(3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reserved')
                    ->label('Reserved')
                    ->state(fn($record) => (float)($record->reserved ?? 0))
                    ->formatStateUsing(fn($state) => number_format((float)$state, 3))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('available')
                    ->label('Available')
                    ->state(fn($record) => (float)($record->qty ?? 0) - (float)($record->reserved ?? 0))
                    ->formatStateUsing(fn($state) => number_format((float)$state, 3)),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')->relationship('branch', 'name'),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')->relationship('product', 'name'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    // Eager-load relations so the Unit column always has access to baseUnit fallback
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['branch', 'product.baseUnit', 'unit']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLevels::route('/'),
        ];
    }
}
