<?php

namespace App\Filament\App\Resources\Products\RelationManagers;

use App\Models\Barcode;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class BarcodesRelationManager extends RelationManager
{
    protected static string $relationship = 'barcodes';

    protected static ?string $title = 'บาร์โค้ด';

    protected static ?string $modelLabel = 'บาร์โค้ด';

    public function form(Schema $schema): Schema
    {
        $product = $this->getOwnerRecord();

        return $schema->components([
            Select::make('variant_id')->label('ตัวเลือก')->required()->native(false)
                ->options(fn () => $product->variants()->get()->mapWithKeys(fn ($v) => [$v->getKey() => $v->name ?: $v->sku]))
                ->default(fn () => $product->variants()->value('id')),
            Select::make('unit_id')->label('หน่วย')->native(false)->placeholder($product->base_unit.' (หน่วยฐาน)')
                ->options(fn () => $product->units()->pluck('name', 'id')),
            TextInput::make('code')->label('บาร์โค้ด')->required()->maxLength(64)
                ->unique(Barcode::class, 'code', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('store_id', $product->store_id)),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')->label('บาร์โค้ด')->searchable()->copyable(),
                TextColumn::make('variant.sku')->label('SKU'),
                TextColumn::make('unit.name')->label('หน่วย')->placeholder($this->getOwnerRecord()->base_unit),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
