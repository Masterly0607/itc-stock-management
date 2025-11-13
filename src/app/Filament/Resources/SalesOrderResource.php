<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesOrderResource\Pages;
use App\Models\{Branch, SalesOrder};
use App\Services\SalesService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class SalesOrderResource extends BaseResource
{
    protected static ?string $model = SalesOrder::class;
    protected static ?string $navigationIcon  = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort  = 40;

    /** Who can see the Sales Orders menu / list page */
    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Super Admin', 'Admin', 'Distributor']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /** Admin + Distributor can create orders */
    public static function canCreate(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Admin', 'Distributor']) ?? false;
    }

    public static function form(Form $form): Form
    {
        $u       = auth()->user();
        $isSA    = $u?->hasRole('Super Admin') ?? false;
        $branchId = $u?->branch_id;

        return $form
            ->schema([
                // Branch
                Select::make('branch_id')
                    ->label('Branch')
                    ->options(function () use ($isSA, $branchId) {
                        return $isSA
                            ? Branch::orderBy('name')->pluck('name', 'id')
                            : Branch::whereKey($branchId)->pluck('name', 'id');
                    })
                    ->default($branchId)
                    ->required()
                    ->disabled(! $isSA)
                    ->dehydrated(true),

                TextInput::make('customer_name')
                    ->label('Customer')
                    ->required()
                    ->maxLength(255),

                Toggle::make('requires_prepayment')
                    ->label('Requires prepayment')
                    ->default(true),

                TextInput::make('currency')
                    ->label('Currency')
                    ->maxLength(3)
                    ->default('USD'),

                Section::make('Items')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3),

                                Select::make('unit_id')
                                    ->label('Unit')
                                    ->relationship('unit', 'name')
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
                                    ->prefix('USD')
                                    ->columnSpan(2),

                                TextInput::make('line_total')
                                    ->label('Line total')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->afterStateHydrated(function (TextInput $component, $state, $record) {
                                        if ($record) {
                                            $component->state($record->line_total);
                                        }
                                    })
                                    ->columnSpan(2),
                            ])
                            ->columns(9)
                            ->minItems(1)
                            ->columnSpanFull(),
                    ]),
            ])
            ->columns(2);
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
                    ->colors([
                        'gray'    => 'DRAFT',
                        'warning' => 'PAID',
                        'success' => 'DELIVERED',
                    ])
                    ->label('Status'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('usd', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('usd', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(fn(SalesOrder $order) => $order->status === 'DRAFT'),

                // DELIVER action — now with graceful error handling
                Action::make('deliver')
                    ->label('Deliver')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(fn(SalesOrder $order) => $order->status !== 'DELIVERED')
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        try {
                            app(SalesService::class)->deliver($record);

                            Notification::make()
                                ->title('Order delivered')
                                ->body("Order #{$record->id} delivered and stock updated.")
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            // Take first validation error message (e.g. insufficient stock)
                            $msg = collect($e->errors())->flatten()->first()
                                ?: $e->getMessage();

                            Notification::make()
                                ->title('Cannot deliver order')
                                ->body($msg)
                                ->danger()
                                ->send();
                        } catch (\DomainException $e) {
                            // Business rule errors like "Pay-before-deliver: order is not PAID."
                            Notification::make()
                                ->title('Cannot deliver order')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    /**
     * SA   -> all orders
     * Admin / Distributor -> only their branch
     */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['branch']);
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
