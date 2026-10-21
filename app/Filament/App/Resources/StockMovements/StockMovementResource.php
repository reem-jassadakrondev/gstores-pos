<?php

namespace App\Filament\App\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\App\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Support\TenantAccess;
use App\Models\StockMovement;
use App\Support\Access\Permission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'สต็อก';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'ความเคลื่อนไหวสต็อก';

    protected static ?string $pluralModelLabel = 'ความเคลื่อนไหวสต็อก';

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToBranchesWith(parent::getEloquentQuery(), Permission::STOCK_VIEW);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['branch', 'variant.product', 'lot', 'user']))
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->label('เวลา')->dateTime('d/m/Y H:i', 'Asia/Bangkok')->sortable(),
                TextColumn::make('branch.name')->label('สาขา'),
                TextColumn::make('variant.sku')->label('SKU')->searchable(),
                TextColumn::make('product')->label('สินค้า')->getStateUsing(fn (StockMovement $r) => $r->variant->displayName()),
                TextColumn::make('type')->label('ประเภท')->badge()->formatStateUsing(fn (StockMovementType $state) => $state->label()),
                TextColumn::make('qty')->label('จำนวน')->numeric(3)->color(fn (StockMovement $r) => (float) $r->qty < 0 ? 'danger' : 'success'),
                TextColumn::make('balance_after')->label('คงเหลือ')->numeric(3),
                TextColumn::make('unit_cost')->label('ต้นทุน/หน่วย')->numeric(4)
                    ->visible(fn () => TenantAccess::can(Permission::REPORTS_FINANCE)),
                TextColumn::make('lot.lot_no')->label('ล็อต')->placeholder('—')->toggleable(),
                TextColumn::make('user.name')->label('ผู้ทำรายการ')->placeholder('ระบบ'),
                TextColumn::make('note')->label('หมายเหตุ')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('ประเภท')->options(StockMovementType::options()),
                SelectFilter::make('branch_id')->label('สาขา')->relationship('branch', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}
