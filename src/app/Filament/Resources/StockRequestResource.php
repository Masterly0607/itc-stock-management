<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockRequestResource\Pages;
use App\Models\{Branch, StockRequest};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class StockRequestResource extends BaseResource
{
    protected static ?string $model = StockRequest::class;
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-up-right';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int    $navigationSort  = 20;

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u?->hasAnyRole(['Super Admin', 'Admin', 'Distributor']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        $u = auth()->user();
        // Allow Admin & Distributor to create requests
        return $u?->hasAnyRole(['Admin', 'Distributor']) ?? false;
    }

    public static function form(Form $form): Form
    {
        $u       = auth()->user();
        $isSA    = $u?->hasRole('Super Admin') ?? false;
        $isAdmin = $u?->hasRole('Admin') ?? false;

        // Resolve HQ and (for a district) its parent province branch
        $hqId = Branch::where('type', 'HQ')->value('id')
            ?? Branch::query()->orderBy('id')->value('id'); // fallback

        $userBranchId = $u?->branch_id;
        $userBranch   = $userBranchId ? Branch::find($userBranchId) : null;

        // If a distributor at a DISTRICT branch, parent is its PROVINCE
        $parentId = null;
        if ($userBranch && $userBranch->type === 'DISTRICT') {
            $parentId = Branch::where('type', 'PROVINCE')
                ->where('province_id', $userBranch->province_id)
                ->value('id');
        }

        // Source:
        // - Admin at province → HQ
        // - Distributor → its parent province (or HQ fallback)
        $autoSourceId = $isAdmin ? $hqId : ($parentId ?: $hqId);

        return $form->schema([
            // REQUESTING BRANCH
            Forms\Components\Select::make('request_branch_id')
                ->label('Requesting Branch')
                ->options(function () use ($isSA, $userBranchId) {
                    $q = Branch::query()->orderBy('name');

                    // Non-SA: only their own branch
                    if (! $isSA && $userBranchId) {
                        $q->whereKey($userBranchId);
                    }

                    return $q->pluck('name', 'id');
                })
                ->default($userBranchId)          // auto-fill with user's branch
                ->required()
                ->disabled(! $isSA)               // SA can change; others locked
                ->dehydrated(true)
                ->afterStateHydrated(function ($component, $state) use ($userBranchId, $isSA) {
                    if (! $isSA && blank($state)) {
                        $component->state($userBranchId);
                    }
                }),

            // SOURCE (PARENT / HQ)
            Select::make('supply_branch_id')
                ->label('Source (Parent/HQ)')
                ->options(function () use ($isSA, $autoSourceId) {
                    return $isSA
                        ? Branch::orderBy('name')->pluck('name', 'id')
                        : Branch::whereKey($autoSourceId)->pluck('name', 'id');
                })
                ->default($autoSourceId)
                ->helperText($isSA ? null : ($autoSourceId ? 'Auto: Parent / HQ' : null))
                ->required()
                ->disabled(! $isSA)
                ->dehydrated(true),

            Forms\Components\Textarea::make('note')
                ->label('Reason')
                ->rows(2)
                ->columnSpanFull(),

            Repeater::make('items')
                ->relationship('items')
                ->schema([
                    Select::make('product_id')
                        ->relationship('product', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(2),

                    Select::make('unit_id')
                        ->relationship('unit', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(1),

                    TextInput::make('qty_requested')
                        ->label('Qty requested')
                        ->numeric()
                        ->minValue(0.001)
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
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requestBranch.name')
                    ->label('Requesting')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplyBranch.name')
                    ->label('Source')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'DRAFT',
                        'warning' => 'SUBMITTED',
                        'success' => 'APPROVED',
                        'danger'  => 'REJECTED',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->since(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make()
                    ->visible(fn(StockRequest $r) => $r->status === 'DRAFT'),

                // DRAFT → SUBMITTED (requesting side)
                Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn(StockRequest $r) => $r->status === 'DRAFT')
                    ->requiresConfirmation()
                    ->action(function (StockRequest $record) {
                        app(\App\Services\StockRequestService::class)->submit($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Submitted')
                            ->success()
                            ->send();
                    }),

                //  Approve + create Transfer
                // - Super Admin at HQ (or wherever they are) can approve when THEIR branch is the Source
                // - Province Admin can approve when THEIR branch is the Source
                Action::make('approve')
                    ->label('Approve → Transfer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (StockRequest $record): bool {
                        $u = auth()->user();
                        if (! $u) {
                            return false;
                        }

                        // Only for SUBMITTED requests
                        if ($record->status !== 'SUBMITTED') {
                            return false;
                        }

                        // Only Admin or Super Admin
                        if (! $u->hasAnyRole(['Admin', 'Super Admin'])) {
                            return false;
                        }

                        // Must belong to the source branch
                        if (! $u->branch_id || ! $record->supply_branch_id) {
                            return false;
                        }

                        // Super Admin can approve ANY request
                        if ($u->hasRole('Super Admin')) {
                            return true;
                        }

                        // Admin must belong to the source branch
                        return (int) $u->branch_id === (int) $record->supply_branch_id;
                    })
                    ->requiresConfirmation()
                    ->action(function (StockRequest $record) {
                        // Approve all with requested qty by default
                        $map = $record->items()
                            ->pluck('qty_requested', 'id')
                            ->map(fn($q) => (float) $q)
                            ->toArray();

                        $transfer = app(\App\Services\StockRequestService::class)
                            ->approveAndCreateTransfer($record, $map, $record->supply_branch_id);

                        \Filament\Notifications\Notification::make()
                            ->title('Approved')
                            ->body('Transfer #' . $transfer->id . ' created.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * Scoping:
     * - SA: all
     * - Admin (province): all requests from branches in their province (province + districts)
     * - Distributor: only their own requests
     */
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with(['requestBranch', 'supplyBranch']);
        $u = auth()->user();

        if (! $u) {
            return $q->whereRaw('1 = 0');
        }

        if ($u->hasRole('Super Admin')) {
            return $q;
        }

        // Distributor → only own branch
        if ($u->hasRole('Distributor') && $u->branch_id) {
            return $q->where('request_branch_id', $u->branch_id);
        }

        // Province Admin → province + all child districts
        if ($u->hasRole('Admin') && $u->branch_id) {
            $branch = $u->relationLoaded('branch')
                ? $u->branch
                : $u->branch()->first();

            if (! $branch) {
                return $q->where('request_branch_id', $u->branch_id);
            }

            if ($branch->type === 'PROVINCE' && $branch->province_id) {
                // all branches in same province (province + districts)
                $branchIds = Branch::query()
                    ->where(function ($b) use ($branch) {
                        $b->where('id', $branch->id)
                            ->orWhere('province_id', $branch->province_id);
                    })
                    ->pluck('id')
                    ->all();

                return $q->whereIn('request_branch_id', $branchIds);
            }

            // fallback: just their branch
            return $q->where('request_branch_id', $branch->id);
        }

        // default: nothing
        return $q->whereRaw('1 = 0');
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
