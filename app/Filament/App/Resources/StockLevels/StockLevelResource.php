<?php

namespace App\Filament\App\Resources\StockLevels;

use App\Filament\App\Resources\StockLevels\Pages\ListStockLevels;
use App\Filament\Support\TenantAccess;
use App\Models\StockLevel;
use App\Support\Access\Permission;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockLevelResource extends Resource
{
    protected static ?string $model = StockLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'สต็อก';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'สต็อกคงเหลือ';

    protected static ?string $pluralModelLabel = 'สต็อกคงเหลือ';

    protected static ?string $slug = 'stock';

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToBranchesWith(parent::getEloquentQuery(), Permission::STOCK_VIEW);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('reorder_point')->label('จุดสั่งซื้อ (แจ้งเตือนเมื่อเหลือไม่เกิน)')->numeric()->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['branch.store', 'variant.product']))
            ->defaultSort('qty')
            ->columns([
                TextColumn::make('variant.sku')->label('SKU')->searchable(),
                TextColumn::make('product')->label('สินค้า')->getStateUsing(fn (StockLevel $r) => $r->variant->displayName())
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas('variant.product', fn ($q) => $q->where('name', 'like', "%{$search}%"))),
                TextColumn::make('branch.name')->label('สาขา')->description(fn (StockLevel $r) => $r->branch->store->name),
                TextColumn::make('qty')->label('คงเหลือ')->numeric(3)->sortable()
                    ->color(fn (StockLevel $r) => (float) $r->qty < 0 ? 'danger' : ($r->isLow() ? 'warning' : null))
                    ->suffix(fn (StockLevel $r) => ' '.$r->variant->product->base_unit),
                TextColumn::make('reorder_point')->label('จุดสั่งซื้อ')->numeric(3)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label('สาขา')->relationship('branch', 'name'),
                Filter::make('low')->label('ใกล้หมด / ติดลบ')
                    ->query(fn (Builder $q) => $q->where(fn ($q) => $q->where('qty', '<', 0)
                        ->orWhere(fn ($q) => $q->whereNotNull('reorder_point')->whereColumn('qty', '<=', 'reorder_point')))),
            ])
            ->recordActions([EditAction::make()->label('จุดสั่งซื้อ')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockLevels::route('/')];
    }
}
