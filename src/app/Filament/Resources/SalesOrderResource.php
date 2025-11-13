<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesOrderResource\Pages;
use App\Models\{Branch, SalesOrder};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class SalesOrderResource extends BaseResource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationIcon  = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort  = 40;

    /** Who can see the menu / list page */
    public static function canViewAny(): bool
    {
        $u = auth()->user();

        // SA, Admin, Distributor can see sales orders
        return $u?->hasAnyRole(['Super Admin', 'Admin', 'Distributor']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /** Who can create orders */
    public static function canCreate(): bool
    {
        $u = auth()->user();

        // SA + Admin + Distributor can create / edit sales orders
        return $u?->hasAnyRole(['Super Admin', 'Admin', 'Distributor']) ?? false;
    }

    public static function form(Form $form): Form
    {
        $user      = auth()->user();
        $isSA      = $user?->hasRole('Super Admin') ?? false;
        $branchId  = $user?->branch_id;

        return $form->schema([
            // BRANCH
            Select::make('branch_id')
                ->label('Branch')
                ->options(function () use ($isSA, $branchId) {
                    $q = Branch::query()->orderBy('name');

                    // Non-SA: lock to their branch only
                    if (! $isSA && $branchId) {
                        $q->whereKey($branchId);
                    }

                    return $q->pluck('name', 'id');
                })
                ->default($branchId)
                ->required()
                ->disabled(! $isSA)      // SA can pick; others locked
                ->dehydrated(true)
                ->afterStateHydrated(function ($component, $state) use ($branchId, $isSA) {
                    // Ensure non-SA always gets their branch
                    if (! $isSA && blank($state)) {
                        $component->state($branchId);
                    }
                }),

            // CUSTOMER
            TextInput::make('customer_name')
                ->label('Customer name')
                ->required()
                ->maxLength(255),

            // PAY-BEFORE-DELIVER FLAG
            Forms\Components\Toggle::make('requires_prepayment')
                ->label('Pay before deliver?')
                ->default(true)
                ->helperText('If enabled, delivery is blocked until the order is fully paid.'),

            // CURRENCY
            TextInput::make('currency')
                ->label('Currency')
                ->default('USD')
                ->maxLength(3)
                ->required(),

            // ITEMS
            Repeater::make('items')
                ->relationship('items')
                ->schema([
                    Select::make('product_id')
                        ->relationship('product', 'name')
                        ->label('Product')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(3),

                    Select::make('unit_id')
                        ->relationship('unit', 'name')
                        ->label('Unit')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),

                    TextInput::make('qty')
                        ->label('Qty')
                        ->numeric()
                        ->minValue(0.001)
                        ->step('0.001')
                        ->required()
                        ->columnSpan(2),

                    TextInput::make('unit_price')
                        ->label('Unit price')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.01')
                        ->required()
                        ->columnSpan(2),

                    // Display only — model events keep line_total correct
                    TextInput::make('line_total')
                        ->label('Line total')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(3)
                        ->extraAttributes(['class' => 'text-right text-gray-500']),
                ])
                ->columns(12)
                ->minItems(1)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'DRAFT',
                        'warning' => 'CONFIRMED',
                        'success' => 'PAID',
                        'primary' => 'DELIVERED',
                    ]),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->state(fn(SalesOrder $record) => (float) $record->total_amount)
                    ->formatStateUsing(
                        fn($state, SalesOrder $record) =>
                        number_format((float) $state, 2) . ' ' . $record->currency
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->state(fn(SalesOrder $record) => (float) $record->paid_amount)
                    ->formatStateUsing(
                        fn($state, SalesOrder $record) =>
                        number_format((float) $state, 2) . ' ' . $record->currency
                    )
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
                        fn(SalesOrder $record) =>
                        $record->status === 'DRAFT'
                    ),

                // Deliver action → posts to ledger & updates stock
                Action::make('deliver')
                    ->label('Deliver')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(function (SalesOrder $record): bool {
                        $u = auth()->user();
                        if (! $u) {
                            return false;
                        }

                        // already delivered → hide
                        if ($record->status === 'DELIVERED') {
                            return false;
                        }

                        // SA can deliver any branch
                        if ($u->hasRole('Super Admin')) {
                            return true;
                        }

                        // Admin / Distributor can only deliver from their own branch
                        if (! $u->hasAnyRole(['Admin', 'Distributor'])) {
                            return false;
                        }

                        return $u->branch_id && $u->branch_id === $record->branch_id;
                    })
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        // Domain rule (pay-before-deliver, stock checks, ledger, etc.)
                        app(\App\Services\SalesService::class)->deliver($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Order delivered')
                            ->body("Sales order #{$record->id} delivered and posted to inventory.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    /**
     * Scoping:
     * - SA: all orders
     * - Admin / Distributor: only their branch's orders
     */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with('branch');
        $u = auth()->user();

        if (! $u) {
            return $q->whereRaw('1 = 0');
        }

        if ($u->hasRole('Super Admin')) {
            return $q;
        }

        if ($u->branch_id) {
            return $q->where('branch_id', $u->branch_id);
        }

        return $q->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'edit'   => Pages\EditSalesOrder::route('/{record}/edit'),
        ];
    }
}
