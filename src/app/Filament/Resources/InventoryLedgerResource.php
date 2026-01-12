<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryLedgerResource\Pages;
use App\Models\InventoryLedger;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryLedgerResource extends BaseResource
{
    protected static ?string $model = InventoryLedger::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int    $navigationSort = 5;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Admin', 'Super Admin']) ?? false;
    }
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }
    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('posted_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('posted_at')
                    ->label('When')
                    ->dateTime()
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),

                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->unit->name ?? optional($record->product->baseUnit)->name ?? '—'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('movement')
                    ->label('Movement')
                    ->colors([
                        'success' => fn($state): bool => str_contains(strtoupper($state), 'IN'),
                        'danger'  => fn($state): bool => str_contains(strtoupper($state), 'OUT'),
                        'warning' => fn($state): bool => str_contains(strtoupper($state), 'ADJ'),
                    ]),

                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty ±')
                    ->numeric(4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->numeric(4)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('source_id')
                    ->label('Ref #')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Posted by')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Branch'),

                Tables\Filters\SelectFilter::make('product_id')
                    ->relationship('product', 'name')
                    ->label('Product'),

                Tables\Filters\SelectFilter::make('movement')->options([
                    'ADJ_IN'       => 'ADJ_IN',
                    'ADJ_OUT'      => 'ADJ_OUT',
                    'SALE_OUT'     => 'SALE_OUT',
                    'TRANSFER_IN'  => 'TRANSFER_IN',
                    'TRANSFER_OUT' => 'TRANSFER_OUT',
                    'IN'           => 'IN',
                    'OUT'          => 'OUT',
                ]),

                Tables\Filters\Filter::make('posted_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from  = $data['from']  ?? null;
                        $until = $data['until'] ?? null;

                        return $query
                            ->when($from,  fn($q) => $q->whereDate('posted_at', '>=', $from))
                            ->when($until, fn($q) => $q->whereDate('posted_at', '<=', $until));
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $filters = $livewire->getTableFiltersForm()->getState();
                        $from  = $filters['posted_at']['from']  ?? null;
                        $until = $filters['posted_at']['until'] ?? null;

                        $query = static::getEloquentQuery()
                            ->with(['branch', 'product', 'unit', 'user']);

                        $rows = $query
                            ->when($from,  fn($q) => $q->whereDate('posted_at', '>=', $from))
                            ->when($until, fn($q) => $q->whereDate('posted_at', '<=', $until))
                            ->orderBy('posted_at')
                            ->get()
                            ->map(fn($r) => [
                                $r->posted_at,
                                $r->branch?->name,
                                $r->product?->name,
                                $r->unit?->name ?? $r->product?->baseUnit?->name ?? '—',
                                $r->movement,
                                $r->qty,
                                $r->balance_after,
                                $r->source_type,
                                $r->source_id,
                                $r->user?->name ?? 'System',
                            ]);

                        $csv = app(\App\Services\ReportService::class)->toCsv(
                            ['Date', 'Branch', 'Product', 'Unit', 'Movement', 'Qty', 'Balance', 'Source', 'Ref', 'User'],
                            $rows
                        );

                        $fileName = 'inventory_ledger_' . now()->format('Ymd_His') . '.csv';
                        $path = storage_path("app/$fileName");
                        file_put_contents($path, $csv);

                        return response()->download($path)->deleteFileAfterSend(true);
                    }),
            ])

            ->actions([])
            ->bulkActions([]);
    }



    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['branch', 'product', 'unit']);

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

        if ($user->hasRole('Admin') && $branch && $branch->province_id) {
            return $query->whereHas('branch', function (Builder $b) use ($branch) {
                $b->where('province_id', $branch->province_id);
            });
        }

        if ($user->hasRole('Distributor') && $user->branch_id) {
            return $query->where('branch_id', $user->branch_id);
        }

        return $query->whereRaw('1 = 0');
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryLedgers::route('/'),
        ];
    }
}
