<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockCountResource\Pages;
use App\Models\StockCount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class StockCountResource extends BaseResource
{
    protected static ?string $model = StockCount::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort = 30;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }
    public static function canEdit($record): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }
    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }
    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        $u = auth()->user();
        $isAdmin = $u?->hasRole('Admin') ?? false;

        return $form->schema([
            Select::make('branch_id')
                ->label('Branch')
                ->relationship(
                    'branch',
                    'name',
                    modifyQueryUsing: function (Builder $q) use ($u, $isAdmin) {
                        if ($isAdmin && $u?->branch_id) {
                            $q->whereKey($u->branch_id);
                        }
                    }
                )
                ->default($isAdmin ? $u?->branch_id : null)
                ->required()
                ->disabled($isAdmin),

            Repeater::make('items')
                ->relationship('items')
                ->schema([
                    Select::make('product_id')
                        ->relationship('product', 'name')
                        ->required()
                        ->columnSpan(2),

                    Select::make('unit_id')
                        ->relationship('unit', 'name')
                        ->required()
                        ->columnSpan(1),

                    // 👇 Make this match your DB column (qty_counted).
                    TextInput::make('qty_counted')
                        ->label('Qty counted')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.001')
                        ->required()
                        ->columnSpan(1),
                ])
                ->columns(4)
                ->minItems(1)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn(StockCount $r) => ($r->status ?? 'DRAFT') === 'DRAFT'),

                Action::make('post')
                    ->label('Post → Create Adjustment')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn(StockCount $r) => ($r->status ?? 'DRAFT') === 'DRAFT')
                    ->requiresConfirmation()
                    ->action(function (StockCount $record, Action $action) {
                        try {
                            app(\App\Services\StockCountService::class)->post($record->id, auth()->id());
                            // Refresh UI so status flips to POSTED immediately
                            $record->refresh();
                            $action->getLivewire()->dispatch('refreshTable');

                            \Filament\Notifications\Notification::make()
                                ->title('Stock Count posted')
                                ->body('Adjustment created from counted variance.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Post failed')
                                ->body($e->getMessage())
                                ->danger()->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        $u = auth()->user();

        if ($u?->hasRole('Admin') && $u?->branch_id) {
            return $q->where('branch_id', $u->branch_id);
        }

        return $q;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStockCounts::route('/'),
            'create' => Pages\CreateStockCount::route('/create'),
            'edit'   => Pages\EditStockCount::route('/{record}/edit'),
        ];
    }
}
