<?php

namespace App\Filament\Resources;

use App\Models\District;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use App\Filament\Resources\DistrictResource\Pages;

class DistrictResource extends BaseResource
{
    protected static ?string $model = District::class;
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('province_id')
                ->label('Province')
                ->relationship('province', 'name')
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(120),

            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(20),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('province.name')->label('Province'),
                Tables\Columns\TextColumn::make('branches_count')
                    ->counts('branches')
                    ->label('Branches')
                    ->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDistricts::route('/'),
            'create' => Pages\CreateDistrict::route('/create'),
            'edit'   => Pages\EditDistrict::route('/{record}/edit'),
        ];
    }
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }
}
