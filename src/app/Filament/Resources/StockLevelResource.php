<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLevelResource\Pages;
use App\Models\StockLevel;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class StockLevelResource extends BaseResource
{
    protected static ?string $model = StockLevel::class;
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int    $navigationSort  = 3;

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

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        // No create/edit via UI
        return $form;
    }

    /**
     * SA        -> all branches
     * Admin     -> their province (province + children districts)
     * Distributor -> their own branch only
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['branch', 'product', 'product.baseUnit', 'unit'])
            ->whereNotNull('unit_id'); // enforce unit presence

        $user = auth()->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        $branch = $user->relationLoaded('branch')
            ? $user->branch
            : $user->branch()->first();

        // Province admin sees own province + districts
        if ($user->hasRole('Admin') && $branch && $branch->province_id) {
            return $query->whereHas('branch', function (Builder $b) use ($branch) {
                $b->where('province_id', $branch->province_id);
            });
        }

        // Distributor sees only their branch
        if ($user->hasRole('Distributor') && $user->branch_id) {
            return $query->where('branch_id', $user->branch_id);
        }

        // default: nothing
        return $query->whereRaw('1 = 0');
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

                // With Option B every row has a single base unit
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->sortable()
                    ->searchable(),

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

                // Tables\Columns\TextColumn::make('reserved')
                //     ->label('Reserved')
                //     ->state(fn($record) => (float) ($record->reserved ?? 0))
                //     ->formatStateUsing(fn($state) => number_format((float) $state, 3))
                //     ->sortable(),

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

                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $filters = $livewire->getTableFiltersForm()->getState();

                        // Decode Filament filter state safely
                        $branchFilter  = $filters['branch_id'] ?? null;
                        $productFilter = $filters['product_id'] ?? null;

                        $branchId = is_array($branchFilter)
                            ? ($branchFilter['value'] ?? null)
                            : $branchFilter;

                        $productId = is_array($productFilter)
                            ? ($productFilter['value'] ?? null)
                            : $productFilter;

                        //  start from the SAME scoped query as the table
                        $query = static::getEloquentQuery();

                        $rows = $query
                            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                            ->when($productId, fn($q) => $q->where('product_id', $productId))
                            ->with(['branch', 'product', 'unit'])
                            ->get()
                            ->map(function ($record) {
                                if (Schema::hasColumn('stock_levels', 'on_hand')) {
                                    $qty = (float) ($record->on_hand ?? 0);
                                } elseif (Schema::hasColumn('stock_levels', 'quantity')) {
                                    $qty = (float) ($record->quantity ?? 0);
                                } else {
                                    $qty = (float) ($record->qty ?? 0);
                                }

                                $reserved  = (float) ($record->reserved ?? 0);
                                $available = $qty - $reserved;

                                return [
                                    $record->branch?->name,
                                    $record->product?->name,
                                    $record->unit?->name,
                                    $available,
                                ];
                            });

                        $csv = app(\App\Services\ReportService::class)
                            ->toCsv(
                                ['Branch', 'Product', 'Unit', 'Available'],
                                $rows
                            );

                        $fileName = 'stock_levels_' . now()->format('Ymd_His') . '.csv';
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
            'index' => Pages\ListStockLevels::route('/'),
        ];
    }
}
