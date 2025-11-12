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
    protected static ?int $navigationSort = 3;

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
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('unit_label')
                    ->label('Unit')
                    ->state(
                        fn($record) =>
                        $record->unit?->name
                            ?? $record->product?->baseUnit?->name
                            ?? '—'
                    ),

                Tables\Columns\TextColumn::make('qty')
                    ->label('On Hand')
                    ->numeric(3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reserved')
                    ->label('Reserved')
                    ->state(fn($record) => (float)($record->reserved ?? 0))
                    ->formatStateUsing(fn($state) => number_format((float)$state, 3)),

                Tables\Columns\TextColumn::make('available')
                    ->label('Available')
                    ->state(
                        fn($record) =>
                        (float)($record->qty ?? 0) - (float)($record->reserved ?? 0)
                    )
                    ->formatStateUsing(fn($state) => number_format((float)$state, 3))
                    ->badge()
                    ->color(fn($state) => $state <= 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Branch'),

                Tables\Filters\SelectFilter::make('product_id')
                    ->relationship('product', 'name')
                    ->label('Product'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $filters = $livewire->getTableFiltersForm()->getState();
                        $branchId = $filters['branch_id']['value'] ?? null; // ✅ fix

                        $rows = \App\Models\StockLevel::query()
                            ->with(['branch', 'product.baseUnit', 'unit'])
                            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                            ->get()
                            ->map(fn($record) => [
                                $record->branch?->name,
                                $record->product?->name,
                                $record->unit?->name ?? $record->product?->baseUnit?->name ?? '—',
                                $record->qty,
                                $record->reserved ?? 0,
                                ($record->qty ?? 0) - ($record->reserved ?? 0),
                            ]);

                        $csv = app(\App\Services\ReportService::class)
                            ->toCsv(['Branch', 'Product', 'Unit', 'On Hand', 'Reserved', 'Available'], $rows);

                        $fileName = 'stock_levels_' . now()->format('Ymd_His') . '.csv';
                        $path = storage_path("app/$fileName");
                        file_put_contents($path, $csv);

                        return response()->download($path)->deleteFileAfterSend(true);
                    })

            ])
            ->actions([])
            ->bulkActions([]);
    }

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
