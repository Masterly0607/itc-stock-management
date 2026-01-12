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
    protected static ?string $slug            = 'low-stock';

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
        // SA, Admin, Distributor can see this report
        return $u?->hasAnyRole(['Super Admin', 'Admin', 'Distributor']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /**
     * Base query: only rows where Available < 50,
     * with branch-scoping:
     *  - SA: all branches
     *  - Admin: their province (province + its districts)
     *  - Distributor: only their own branch
     */
    public static function getEloquentQuery(): Builder
    {
        // Figure out which columns are used on stock_levels
        $qtyCol = Schema::hasColumn('stock_levels', 'on_hand')
            ? 'stock_levels.on_hand'
            : (Schema::hasColumn('stock_levels', 'qty')
                ? 'stock_levels.qty'
                : 'stock_levels.quantity');

        $resCol = Schema::hasColumn('stock_levels', 'reserved')
            ? 'COALESCE(stock_levels.reserved, 0)'
            : '0';

        $availExpr = "($qtyCol - $resCol)";

        $query = StockLevel::query()
            ->with(['branch', 'product.baseUnit', 'unit'])
            ->whereRaw("$availExpr < 50"); // threshold

        $user = auth()->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Super Admin: see everything
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        // Load user's branch (province or district)
        $branch = $user->relationLoaded('branch')
            ? $user->branch
            : $user->branch()->first();

        // Province Admin: see their province + all its districts
        if ($user->hasRole('Admin') && $branch && $branch->province_id) {
            return $query->whereHas('branch', function (Builder $b) use ($branch) {
                $b->where('province_id', $branch->province_id);
            });
        }

        // Distributor: only own branch
        if ($user->hasRole('Distributor') && $user->branch_id) {
            return $query->where('branch_id', $user->branch_id);
        }

        // Fallback: nothing
        return $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Branch name (from relationship)
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                // Product name
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),

                // Unit label: unit.name OR product.baseUnit.name OR '-'
                Tables\Columns\TextColumn::make('unit_label')
                    ->label('Unit')
                    ->state(function ($record) {
                        return $record->unit?->name
                            ?? $record->product?->baseUnit?->name
                            ?? '-';
                    }),

                // On hand
                // Tables\Columns\TextColumn::make('on_hand')
                //     ->label('On Hand')
                //     ->state(function ($record) {
                //         if (Schema::hasColumn('stock_levels', 'on_hand')) {
                //             return (float) ($record->on_hand ?? 0);
                //         }
                //         if (Schema::hasColumn('stock_levels', 'quantity')) {
                //             return (float) ($record->quantity ?? 0);
                //         }
                //         return (float) ($record->qty ?? 0);
                //     })
                //     ->formatStateUsing(fn($state) => number_format((float) $state, 3))
                //     ->sortable(),

                // // Reserved
                // Tables\Columns\TextColumn::make('reserved')
                //     ->label('Reserved')
                //     ->state(fn($record) => (float) ($record->reserved ?? 0))
                //     ->formatStateUsing(fn($state) => number_format((float) $state, 3))
                //     ->sortable(),

                // Available = on_hand - reserved
                Tables\Columns\TextColumn::make('available')
                    ->label('Available')
                    ->state(function ($record) {
                        if (Schema::hasColumn('stock_levels', 'on_hand')) {
                            $qty = (float) ($record->on_hand ?? 0);
                        } elseif (Schema::hasColumn('stock_levels', 'quantity')) {
                            $qty = (float) ($record->quantity ?? 0);
                        } else {
                            $qty = (float) ($record->qty ?? 0);
                        }

                        $reserved = (float) ($record->reserved ?? 0);
                        return $qty - $reserved;
                    })
                    ->formatStateUsing(fn($state) => number_format((float) $state, 3))
                    ->badge()
                    ->color(
                        fn($state) =>
                        $state <= 0 ? 'danger'
                            : ($state < 50 ? 'warning' : 'success')
                    )
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $filters = $livewire->getTableFiltersForm()->getState();

                        // branch_id filter can be: null, int, or ['value' => int]
                        $branchFilter = $filters['branch_id'] ?? null;
                        $branchId = is_array($branchFilter)
                            ? ($branchFilter['value'] ?? null)
                            : $branchFilter;

                        // use the SAME scoped query as the table
                        $query = static::getEloquentQuery()
                            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

                        $rows = $query->with(['branch', 'product.baseUnit', 'unit'])
                            ->get()
                            ->map(function ($record) {
                                // calculate Available only
                                if (\Illuminate\Support\Facades\Schema::hasColumn('stock_levels', 'on_hand')) {
                                    $qty = (float) ($record->on_hand ?? 0);
                                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('stock_levels', 'quantity')) {
                                    $qty = (float) ($record->quantity ?? 0);
                                } else {
                                    $qty = (float) ($record->qty ?? 0);
                                }

                                $reserved  = (float) ($record->reserved ?? 0);
                                $available = $qty - $reserved;

                                return [
                                    $record->branch?->name,
                                    $record->product?->name,
                                    $record->unit?->name ?? $record->product?->baseUnit?->name ?? '-',
                                    $available, // ONLY export Available
                                ];
                            });

                        $csv = app(\App\Services\ReportService::class)
                            ->toCsv(
                                ['Branch', 'Product', 'Unit', 'Available'], // 4 columns
                                $rows
                            );

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
