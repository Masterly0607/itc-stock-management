<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends BaseResource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 35;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Distributor', 'Admin', 'Super Admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        $u = auth()->user();
        return $u?->hasRole('Distributor') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }


    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('sales_order_id')
                ->label('Order')
                ->relationship('order', 'id')
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(function ($state, Set $set) {
                    if (!$state) return;
                    $order = \App\Models\SalesOrder::with('payments')->find($state);
                    if ($order) {
                        $set('total_display', number_format($order->total_amount, 2));
                        $set('paid_display', number_format($order->paid_amount, 2));
                        $set('balance_display', number_format($order->balance, 2));
                        $set('amount', max(0, $order->balance)); // auto-fill balance
                    }
                }),

            Forms\Components\TextInput::make('total_display')
                ->label('Order total')
                ->disabled(),

            Forms\Components\TextInput::make('paid_display')
                ->label('Paid so far')
                ->disabled(),

            Forms\Components\TextInput::make('balance_display')
                ->label('Balance')
                ->disabled(),

            Forms\Components\TextInput::make('amount')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->rule(function (Get $get) {
                    $orderId = $get('sales_order_id');
                    if (!$orderId) return null;
                    $order = \App\Models\SalesOrder::find($orderId);
                    return $order ? "max:{$order->balance}" : null;
                }),

            Forms\Components\Select::make('method')
                ->options([
                    'CASH' => 'Cash',
                    'BANK' => 'Bank',
                    'CARD' => 'Card',
                ])
                ->required(),

            Forms\Components\DateTimePicker::make('received_at')
                ->default(now())
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sales_order_id')->label('Order #')->sortable(),
            Tables\Columns\TextColumn::make('amount')->money('usd', true),
            Tables\Columns\TextColumn::make('method'),
            Tables\Columns\TextColumn::make('received_at')->dateTime(),
        ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
