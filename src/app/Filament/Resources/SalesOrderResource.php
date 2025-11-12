<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesOrderResource\Pages;
use App\Models\SalesOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class SalesOrderResource extends BaseResource
{
    protected static ?string $model = SalesOrder::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 30;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Distributor', 'Admin', 'Super Admin']) ?? false;
    }
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
    public static function canCreate(): bool
    {
        $u = auth()->user();
        return $u?->hasRole('Distributor') ?? false;   // Distributor can create
    }


    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('branch_id')->relationship('branch', 'name')->required(),
            TextInput::make('customer_name')->required(),
            Toggle::make('requires_prepayment')->default(true),
            TextInput::make('currency')->default('USD')->maxLength(3),
            Repeater::make('items')->relationship()->schema([
                Select::make('product_id')->relationship('product', 'name')->required(),
                Select::make('unit_id')->relationship('unit', 'name')->required(),
                TextInput::make('qty')->numeric()->minValue(0.001)->step('0.001')->required(),
                TextInput::make('unit_price')->numeric()->minValue(0)->step('0.01')->required(),
                TextInput::make('line_total')->numeric()->readOnly()
                    ->afterStateUpdated(null)
                    ->dehydrated(false)
                    ->helperText('Auto = qty * unit_price on save'),
            ])->minItems(1)->columns(5)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
            Tables\Columns\TextColumn::make('customer_name')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('total_amount')->money('usd', true),
        ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn($record) => $record->status === 'DRAFT'),
                Tables\Actions\ViewAction::make(),
                Action::make('deliver')
                    ->label('Deliver')
                    ->color('success')
                    ->icon('heroicon-o-truck')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === 'POSTED')
                    ->action(function (SalesOrder $record) {
                        try {
                            app(\App\Services\SalesService::class)->deliver($record, auth()->id());


                            Notification::make()
                                ->title('Delivered & posted to ledger.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Delivery failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('post')
                    ->label('Post Order')
                    ->color('primary')
                    ->visible(fn(SalesOrder $record) => $record->status === 'DRAFT')
                    ->requiresConfirmation()
                    ->action(function (SalesOrder $record) {
                        try {
                            app(\App\Services\SalesService::class)->post($record->id, auth()->id());

                            Notification::make()
                                ->title('Order posted successfully.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesOrders::route('/'),
            'create' => Pages\CreateSalesOrder::route('/create'),
            'edit' => Pages\EditSalesOrder::route('/{record}/edit'),

        ];
    }
}
