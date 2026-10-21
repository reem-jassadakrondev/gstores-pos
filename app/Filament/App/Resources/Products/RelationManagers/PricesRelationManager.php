<?php

namespace App\Filament\App\Resources\Products\RelationManagers;

use App\Enums\SalesChannel;
use App\Models\Branch;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'ราคาตามช่องทาง / สาขา';

    protected static ?string $modelLabel = 'ราคา';

    public function form(Schema $schema): Schema
    {
        $product = $this->getOwnerRecord();

        return $schema->components([
            Select::make('variant_id')->label('ตัวเลือก')->required()->native(false)
                ->options(fn () => $product->variants()->get()->mapWithKeys(fn ($v) => [$v->getKey() => $v->name ?: $v->sku]))
                ->default(fn () => $product->variants()->value('id')),
            Select::make('unit_id')->label('หน่วย')->native(false)->placeholder($product->base_unit.' (หน่วยฐาน)')
                ->options(fn () => $product->units()->pluck('name', 'id')),
            Select::make('channel')->label('ช่องทาง')->options(SalesChannel::options())->default(SalesChannel::Store->value)->required()->native(false),
            Select::make('branch_id')->label('สาขา')->native(false)->placeholder('ทุกสาขา')
                ->options(fn () => Branch::where('store_id', $product->store_id)->pluck('name', 'id')),
            TextInput::make('min_qty')->label('ซื้อตั้งแต่ (จำนวน)')->numeric()->minValue(0)->default(1)->required()
                ->helperText('ใช้ทำราคาขายส่งแบบขั้นบันได'),
            TextInput::make('price')->label('ราคา')->numeric()->minValue(0)->required()->prefix('฿'),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant.sku')->label('SKU'),
                TextColumn::make('unit.name')->label('หน่วย')->placeholder($this->getOwnerRecord()->base_unit),
                TextColumn::make('channel')->label('ช่องทาง')->badge()->formatStateUsing(fn (SalesChannel $state) => $state->label()),
                TextColumn::make('branch.name')->label('สาขา')->placeholder('ทุกสาขา'),
                TextColumn::make('min_qty')->label('ตั้งแต่')->numeric(3),
                TextColumn::make('price')->label('ราคา')->money('THB'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
