<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use App\Models\Province;
use App\Models\District;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class BranchResource extends BaseResource
{
    protected static ?string $model = Branch::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(100),

            Forms\Components\TextInput::make('code')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(50),

            // --- Type picker ---
            Select::make('type')
                ->options([

                    'PROVINCE' => 'PROVINCE',
                    'DISTRICT' => 'DISTRICT',
                ])
                ->required()
                ->live(),

            // --- Province picker ---
            // --- Province picker ---
            Select::make('province_id')
                ->label('Province')
                ->options(function (Forms\Get $get, ?\App\Models\Branch $record) {
                    $type = $get('type');

                    if ($type === 'PROVINCE') {
                        // Provinces already used by a PROVINCE branch, EXCEPT this record's province
                        $usedProvinceIds = DB::table('branches')
                            ->where('type', 'PROVINCE')
                            ->whereNotNull('province_id')
                            ->when($record?->province_id, fn($q) => $q->where('province_id', '!=', $record->province_id))
                            ->pluck('province_id');

                        return \App\Models\Province::whereNotIn('id', $usedProvinceIds)
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    }

                    if ($type === 'DISTRICT') {
                        // Only provinces that already have a PROVINCE branch (admin branch)
                        $allowedProvinceIds = DB::table('branches')
                            ->where('type', 'PROVINCE')
                            ->pluck('province_id');

                        return \App\Models\Province::whereIn('id', $allowedProvinceIds)
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    }

                    // HQ: no province
                    return collect();
                })
                ->preload()
                ->required(fn(Forms\Get $get) => in_array($get('type'), ['PROVINCE', 'DISTRICT']))
                ->visible(fn(Forms\Get $get) => in_array($get('type'), ['PROVINCE', 'DISTRICT']))
                ->live(),



            // --- District picker (only for DISTRICT type) ---
            // --- District picker (only for DISTRICT type) ---
            Select::make('district_id')
                ->label('District')
                ->options(function (Forms\Get $get, ?\App\Models\Branch $record) {
                    $provinceId = $get('province_id');
                    if (! $provinceId) {
                        return collect();
                    }

                    // Districts already used by DISTRICT branches, EXCEPT this record's district
                    $usedDistrictIds = DB::table('branches')
                        ->where('type', 'DISTRICT')
                        ->whereNotNull('district_id')
                        ->when($record?->district_id, fn($q) => $q->where('district_id', '!=', $record->district_id))
                        ->pluck('district_id');

                    return \App\Models\District::where('province_id', $provinceId)
                        ->when($usedDistrictIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $usedDistrictIds))
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->required(fn(Forms\Get $get) => $get('type') === 'DISTRICT')
                ->visible(fn(Forms\Get $get) => $get('type') === 'DISTRICT')
                ->preload()
                ->live(),


            Forms\Components\Toggle::make('is_active')
                ->default(true)
                ->label('Is active'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->searchable(),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('type'),
            Tables\Columns\TextColumn::make('province.name')->label('Province'),
            Tables\Columns\TextColumn::make('district.name')->label('District'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit'   => Pages\EditBranch::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }
}
