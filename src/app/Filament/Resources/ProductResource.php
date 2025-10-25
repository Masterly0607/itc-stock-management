<?php

namespace App\Filament\Resources;

use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

class ProductResource extends BaseResource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 40;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sku')->required()->maxLength(50),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('brand')->maxLength(100),
            Forms\Components\TextInput::make('barcode')->maxLength(100),

            // Category
            Select::make('category_id')
                ->label('Category')
                ->relationship(
                    name: 'category',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn($query) => $query->where('is_active', true),
                )
                ->searchable()
                ->preload()
                ->required(),

            // Supplier
            Select::make('supplier_id')
                ->label('Supplier')

                ->relationship(
                    name: 'supplier',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn($query) => $query->where('is_active', true),
                )
                ->searchable()
                ->preload()
                ->required()

                ->rules([
                    Rule::exists('suppliers', 'id')->where('is_active', true),
                ])

                ->getOptionLabelFromRecordUsing(fn(Supplier $record) => $record->name),


            // Unit
            // app/Filament/Resources/ProductResource.php (inside form)
            Select::make('unit_id')
                ->label('Unit')
                ->relationship(
                    name: 'unit',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn($query) => $query->where('is_active', true),
                )
                ->searchable()
                ->preload()
                ->required(),


            Forms\Components\Toggle::make('is_active')
                ->label('Is active')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('category.name')->label('Category'),
            Tables\Columns\TextColumn::make('supplier.name')->label('Supplier'),
            Tables\Columns\TextColumn::make('unit.name')->label('Unit'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['Super Admin', 'Admin', 'Distributor']) ?? false;
    }
}
