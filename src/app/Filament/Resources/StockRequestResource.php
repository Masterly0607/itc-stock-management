<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockRequestResource\Pages;
use App\Models\Branch;
use App\Models\StockRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class StockRequestResource extends BaseResource
{
    protected static ?string $model = StockRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort = 10;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Distributor', 'Admin', 'Super Admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Distributor', 'Admin']) ?? false;
    }

    public static function form(Form $form): Form
    {
        $u = auth()->user();
        $isDistributor = $u?->hasRole('Distributor') ?? false;
        $isAdmin       = $u?->hasRole('Admin') ?? false;

        $uBranch = $u?->branch_id ? Branch::find($u->branch_id) : null;

        // Parent = province branch of the same province (for Distributor)
        $parentId = null;
        if ($isDistributor && $uBranch) {
            $parentId = Branch::where('type', 'PROVINCE')
                ->where('province_id', $uBranch->province_id)
                ->value('id');
        }

        // HQ id by type
        $hqId = Branch::where('type', 'HQ')->value('id');

        return $form->schema([
            // Request branch — fixed to user's branch, hidden
            Select::make('request_branch_id')
                ->label('Request branch')
                ->options(
                    $u?->branch_id
                        ? Branch::whereKey($u->branch_id)->pluck('name', 'id')->toArray()
                        : []
                )
                ->default($u?->branch_id)
                ->hidden($isDistributor || $isAdmin)
                ->dehydrated(true)
                ->required(),

            // Supply branch — strict list by role
            Select::make('supply_branch_id')
                ->label('Supply branch')
                ->options(function () use ($isDistributor, $isAdmin, $parentId, $hqId) {
                    if ($isDistributor) {
                        return $parentId
                            ? Branch::whereKey($parentId)->pluck('name', 'id')->toArray()
                            : [];
                    }
                    if ($isAdmin) {
                        return $hqId
                            ? Branch::whereKey($hqId)->pluck('name', 'id')->toArray()
                            : [];
                    }
                    // Super Admin
                    return Branch::orderBy('name')->pluck('name', 'id')->toArray();
                })
                ->preload()
                ->required(),

            TextInput::make('ref_no')->maxLength(50),
            Textarea::make('note')->columnSpanFull(),

            Repeater::make('items')
                ->relationship()
                ->schema([
                    Select::make('product_id')->relationship('product', 'name')->required(),
                    Select::make('unit_id')->relationship('unit', 'name')->required()->columnSpan(1),
                    TextInput::make('qty_requested')->numeric()->minValue(0.001)->step('0.001')->required(),
                    TextInput::make('qty_approved')->numeric()->minValue(0)->step('0.001')
                        ->helperText('Admin fills on approval'),
                ])
                ->columns(4)->minItems(1)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ref_no')->label('Ref')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('requestBranch.name')->label('Request From')->searchable(),
                Tables\Columns\TextColumn::make('supplyBranch.name')->label('Supply From')->searchable(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->since(),
                Tables\Columns\TextColumn::make('approved_at')->dateTime()->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn($r) => in_array($r->status, ['DRAFT', 'SUBMITTED'])),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn($r) => auth()->user()?->hasAnyRole(['Admin', 'Super Admin']) && $r->status === 'SUBMITTED')
                    ->requiresConfirmation()
                    ->action(function (StockRequest $record) {
                        try {
                            app(\App\Services\StockRequestService::class)->approve($record->id, auth()->id());
                            \Filament\Notifications\Notification::make()
                                ->title('Approved')->body('Approved and transfer created.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Approval failed')->body($e->getMessage())
                                ->danger()->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('Cancel')->color('warning')
                    ->visible(fn($r) => in_array($r->status, ['DRAFT', 'SUBMITTED']))
                    ->requiresConfirmation()
                    ->action(function (StockRequest $record) {
                        try {
                            app(\App\Services\StockRequestService::class)->cancel($record->id, auth()->id());
                            \Filament\Notifications\Notification::make()
                                ->title('Cancelled')->body('Stock request cancelled.')
                                ->success()->send();
                        } catch (\Throwable $e) {
                            report($e);
                            \Filament\Notifications\Notification::make()
                                ->title('Cancel failed')->body($e->getMessage())
                                ->danger()->send();
                        }
                    }),

                Tables\Actions\ViewAction::make(),
            ]);
    }

    /** List scoping: DI/Admin see their own requests; SA sees all */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        $u = auth()->user();

        if ($u?->hasRole('Distributor')) {
            return $q->where('request_branch_id', $u->branch_id);
        }
        if ($u?->hasRole('Admin')) {
            return $q->where('request_branch_id', $u->branch_id);
        }
        return $q;
    }

    /** Server-side guard in case UI is bypassed */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $u = auth()->user();
        $branchId = $u?->branch_id;

        if ($u?->hasRole('Distributor')) {
            $uBranch = $branchId ? Branch::find($branchId) : null;
            $parentId = $uBranch
                ? Branch::where('type', 'PROVINCE')->where('province_id', $uBranch->province_id)->value('id')
                : null;

            $data['request_branch_id'] = $branchId;

            if ($parentId && (int)$data['supply_branch_id'] !== (int)$parentId) {
                throw ValidationException::withMessages([
                    'supply_branch_id' => 'Distributor can request only from their province branch.',
                ]);
            }
        }

        if ($u?->hasRole('Admin')) {
            $hqId = Branch::where('type', 'HQ')->value('id');
            $data['request_branch_id'] = $branchId;

            if ($hqId && (int)$data['supply_branch_id'] !== (int)$hqId) {
                throw ValidationException::withMessages([
                    'supply_branch_id' => 'Admin may request only from HQ.',
                ]);
            }
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStockRequests::route('/'),
            'create' => Pages\CreateStockRequest::route('/create'),
            'edit'   => Pages\EditStockRequest::route('/{record}/edit'),
        ];
    }
}
