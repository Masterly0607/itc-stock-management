<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitResource extends BaseResource
{
    protected static ?string $model = Unit::class;
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?int $navigationSort = 25; // between Categories(20) and Suppliers(30)

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(80),

            Forms\Components\TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(20),

            Forms\Components\Toggle::make('is_active')
                ->label('Is active')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit'   => Pages\EditUnit::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }
}
