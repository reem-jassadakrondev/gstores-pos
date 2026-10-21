<?php

namespace App\Filament\App\Resources\StockCounts\RelationManagers;

use App\Enums\DocumentStatus;
use App\Models\StockCountItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'รายการตรวจนับ';

    public function table(Table $table): Table
    {
        $editable = $this->getOwnerRecord()->status === DocumentStatus::Draft;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('variant.product'))
            ->paginated([50, 100, 250])
            ->columns([
                TextColumn::make('variant.sku')->label('SKU')->searchable(),
                TextColumn::make('product')->label('สินค้า')->getStateUsing(fn (StockCountItem $r) => $r->variant->displayName())
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas('variant.product', fn ($q) => $q->where('name', 'like', "%{$search}%"))),
                TextColumn::make('expected_qty')->label('ในระบบ (ตอนเริ่ม)')->numeric(3),
                TextInputColumn::make('counted_qty')->label('นับได้')->type('number')->rules(['nullable', 'numeric', 'min:0'])
                    ->disabled(! $editable),
                TextColumn::make('diff')->label('ส่วนต่าง')
                    ->getStateUsing(fn (StockCountItem $r) => $r->counted_qty === null ? null : round((float) $r->counted_qty - (float) $r->expected_qty, 3))
                    ->numeric(3)->placeholder('—')
                    ->color(fn ($state) => $state === null || abs($state) < 0.0005 ? null : ($state < 0 ? 'danger' : 'success')),
            ])
            ->filters([
                TernaryFilter::make('counted')->label('กรอกแล้ว')
                    ->queries(true: fn ($q) => $q->whereNotNull('counted_qty'), false: fn ($q) => $q->whereNull('counted_qty')),
            ]);
    }
}
