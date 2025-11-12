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
                Tables\Columns\TextColumn::make('posted_at')->label('When')->dateTime()->since()->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable(),

                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->unit->name
                            ?? optional($record->product->baseUnit)->name
                            ?? '—'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('movement')
                    ->colors([
                        'success' => fn($state) => str_contains(strtoupper($state), 'IN'),
                        'danger'  => fn($state) => str_contains(strtoupper($state), 'OUT'),
                        'warning' => fn($state) => str_contains(strtoupper($state), 'ADJ'),
                    ]),

                Tables\Columns\TextColumn::make('qty')->label('Qty ±')->numeric(4)->sortable(),
                Tables\Columns\TextColumn::make('balance_after')->label('Balance after')->numeric(4)
                    ->toggleable(isToggledHiddenByDefault: true),

                // Correct source columns
                Tables\Columns\TextColumn::make('source_type')->label('Source')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('source_id')->label('Ref #')->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')->label('Posted by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')->relationship('branch', 'name')->label('Branch'),
                Tables\Filters\SelectFilter::make('product_id')->relationship('product', 'name')->label('Product'),
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
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from']  ?? null, fn($q, $d) => $q->whereDate('posted_at', '>=', $d))
                            ->when($data['until'] ?? null, fn($q, $d) => $q->whereDate('posted_at', '<=', $d));
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['branch', 'product', 'unit', 'user']);
        $u = auth()->user();

        if ($u?->hasRole('Admin') && $u?->branch_id) {
            return $q->where('branch_id', $u->branch_id);
        }

        return $q;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryLedgers::route('/'),
        ];
    }
}
