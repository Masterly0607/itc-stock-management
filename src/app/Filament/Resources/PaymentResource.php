<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\SalesOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends BaseResource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon  = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort  = 40;

    /** Who can see Payments menu / list page */
    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /** Allow SA + Admin to create payments (override BaseResource) */
    public static function canCreate(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Admin']) ?? false;
    }


    public static function form(Form $form): Form
    {
        $user = auth()->user();

        return $form->schema([
            Select::make('sales_order_id')
                ->label('Sales Order')
                ->relationship(
                    name: 'order',
                    titleAttribute: 'id',
                    modifyQueryUsing: function (Builder $q) use ($user) {
                        $q->with('branch');

                        if (! $user) {
                            return $q->whereRaw('1 = 0');
                        }

                        // SA → all orders
                        if ($user->hasRole('Super Admin')) {
                            return;
                        }

                        // Admin → only their branch orders
                        if ($user->branch_id) {
                            $q->where('branch_id', $user->branch_id);
                        } else {
                            $q->whereRaw('1 = 0');
                        }
                    }
                )
                ->getOptionLabelUsing(function ($value) {
                    $order = SalesOrder::with('branch')->find($value);
                    if (! $order) {
                        return '';
                    }

                    $branch   = $order->branch?->name ?? 'Unknown branch';
                    $customer = $order->customer_name ?: 'N/A';
                    $total    = number_format((float) $order->total_amount, 2);
                    $paid     = number_format((float) $order->paid_amount, 2);

                    return "#{$order->id} – {$customer} ({$branch}) – {$paid}/{$total}";
                })

                ->required(),

            TextInput::make('amount')
                ->numeric()
                ->minValue(0.01)
                ->step('0.01')
                ->required(),

            TextInput::make('currency')
                ->maxLength(3)
                ->default('USD')
                ->required(),

            TextInput::make('method')
                ->label('Method')
                ->placeholder('Cash, Transfer, etc.')
                ->maxLength(50),

            DateTimePicker::make('received_at')
                ->default(now())
                ->required(),
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

                Tables\Columns\TextColumn::make('order.branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('order.customer_name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn($record) => $record->currency ?? 'USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('received_at')
                    ->label('Received at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),

            ])
            ->bulkActions([]);
    }

    /**
     * SA  -> all payments
     * Admin -> payments for orders in their branch
     */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['order.branch']);
        $u = auth()->user();

        if (! $u) {
            return $q->whereRaw('1 = 0');
        }

        if ($u->hasRole('Super Admin')) {
            return $q;
        }

        if ($u->hasRole('Admin') && $u->branch_id) {
            return $q->whereHas('order', function (Builder $orderQ) use ($u) {
                $orderQ->where('branch_id', $u->branch_id);
            });
        }

        return $q->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit'   => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
