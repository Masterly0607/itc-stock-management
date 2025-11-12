<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LowStockResource\Pages;
use App\Models\StockLevel;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class LowStockResource extends BaseResource
{
    protected static ?string $model = StockLevel::class;
    protected static ?string $navigationIcon  = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $navigationLabel = 'Low Stock';
    protected static ?string $slug = 'low-stock';

    public static function getModelLabel(): string
    {
        return 'Low Stock';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Low Stock';
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Admin', 'Super Admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    // 🔹 Base query — only products where Available < 50
    public static function getEloquentQuery(): Builder
    {
        $qtyCol = Schema::hasColumn('stock_levels', 'on_hand')
            ? 'stock_levels.on_hand'
            : 'stock_levels.qty';
        $resCol = Schema::hasColumn('stock_levels', 'reserved')
            ? 'COALESCE(stock_levels.reserved, 0)'
            : '0';
        $avail  = "($qtyCol - $resCol)";

        return StockLevel::query()
            ->join('branches', 'branches.id', '=', 'stock_levels.branch_id')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('units', 'units.id', '=', 'stock_levels.unit_id')
            ->whereRaw("CAST($avail AS DECIMAL(12,3)) < 50") //  Always filter below 50
            ->selectRaw("
                stock_levels.id,
                branches.name  AS branch,
                products.name  AS product,
                COALESCE(units.name, '-') AS unit,
                $qtyCol        AS on_hand,
                $resCol        AS reserved,
                $avail         AS available
            ");
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('product')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit'),

                Tables\Columns\TextColumn::make('on_hand')
                    ->label('On Hand')
                    ->numeric(3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reserved')
                    ->label('Reserved')
                    ->numeric(3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('available')
                    ->label('Available')
                    ->numeric(3)
                    ->badge()
                    ->color(fn($state) => $state <= 0 ? 'danger' : ($state < 50 ? 'warning' : 'success'))
                    ->sortable(),
            ])
            ->filters([
                // 🔹 Optional branch filter
                Tables\Filters\Filter::make('branch_id')
                    ->label('Branch')
                    ->form([
                        \Filament\Forms\Components\Select::make('id')
                            ->options(\App\Models\Branch::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $q, array $data) {
                        return empty($data['id'])
                            ? $q
                            : $q->where('stock_levels.branch_id', (int) $data['id']);
                    })
                    ->indicateUsing(function (array $data) {
                        if (empty($data['id'])) return null;
                        $name = \App\Models\Branch::whereKey($data['id'])->value('name');
                        return $name ? ["Branch: $name"] : null;
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $filters = $livewire->getTableFiltersForm()->getState();

                        $branchId  = $filters['branch_id']['id'] ?? null;
                        $threshold = 50;

                        $csv = app(\App\Services\ReportService::class)
                            ->lowStockCsv($branchId, $threshold);

                        //  Instead of returning response(), trigger download properly
                        $fileName = 'low_stock_' . now()->format('Ymd_His') . '.csv';
                        $path = storage_path("app/$fileName");
                        file_put_contents($path, $csv);

                        return response()->download($path)->deleteFileAfterSend(true);
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLowStocks::route('/'),
        ];
    }
}
