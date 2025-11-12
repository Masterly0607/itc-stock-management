<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdjustmentResource\Pages;
use App\Models\Adjustment;
use App\Models\Product;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdjustmentResource extends BaseResource
{
    protected static ?string $model = Adjustment::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort = 35;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Admin', 'Super Admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return ($record->status ?? 'DRAFT') === 'DRAFT'
            && (auth()->user()?->hasAnyRole(['Admin', 'Super Admin']) ?? false);
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

            Textarea::make('reason')->label('Reason')->rows(2)->columnSpanFull(),

            Repeater::make('items')
                ->relationship('items')
                ->schema([
                    Select::make('product_id')
                        ->relationship('product', 'name')

                        ->required()
                        ->live(debounce: 0)
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('unit_id', Product::find($state)?->base_unit_id);
                        })
                        ->columnSpan(2),

                    Select::make('unit_id')
                        ->label('Unit')

                        ->preload()
                        ->live()
                        ->options(function (Get $get) {
                            $productId = $get('product_id');
                            if (!$productId) {
                                // show all units so the list is never blank
                                return Unit::orderBy('name')->pluck('name', 'id')->toArray();
                            }

                            if (Schema::hasTable('product_units')) {
                                $viaPivot = DB::table('product_units')
                                    ->join('units', 'units.id', '=', 'product_units.unit_id')
                                    ->where('product_units.product_id', $productId)
                                    ->orderBy('units.name')
                                    ->pluck('units.name', 'units.id')
                                    ->toArray();

                                if (!empty($viaPivot)) {
                                    return $viaPivot;
                                }
                            }

                            $base = Product::find($productId)?->baseUnit;
                            return $base ? [$base->id => $base->name] : Unit::orderBy('name')->pluck('name', 'id')->toArray();
                        })
                        ->default(fn(Get $get) => Product::find($get('product_id'))?->base_unit_id)
                        ->required()
                        ->columnSpan(1),

                    TextInput::make('qty_delta')
                        ->label('Qty Δ (+/-)')
                        ->numeric()
                        ->step('0.001')
                        ->required()
                        ->helperText('Positive = Adjust IN, Negative = Adjust OUT')
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

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? ucwords($state) : '—')
                    ->color(fn($state) => match (strtoupper((string)$state)) {
                        'RECEIVE', 'RECEIVE PRODUCT FROM SUPPLIER', 'RECEIVED' => 'success',
                        'DAMAGE' => 'danger',
                        'EXPIRE', 'EXPIRY' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => $state === 'POSTED' ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn(Adjustment $r) => ($r->status ?? 'DRAFT') === 'DRAFT'),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Adjustment $r) => ($r->status ?? 'DRAFT') === 'DRAFT')
                    ->requiresConfirmation()
                    ->action(function (Adjustment $record) {
                        try {
                            app(\App\Services\AdjustmentService::class)->post($record, auth()->id());
                            \Filament\Notifications\Notification::make()
                                ->title('Adjustment posted')
                                ->body('Ledger updated and stock levels refreshed.')
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

        if ($u?->hasRole('Admin')) {
            return $q->where('branch_id', $u->branch_id);
        }

        return $q;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdjustments::route('/'),
            'create' => Pages\CreateAdjustment::route('/create'),
        ];
    }
}
