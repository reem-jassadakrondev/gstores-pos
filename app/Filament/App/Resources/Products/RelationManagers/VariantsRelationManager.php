<?php

namespace App\Filament\App\Resources\Products\RelationManagers;

use App\Filament\Support\TenantAccess;
use App\Support\Access\Permission;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'ตัวเลือก / SKU';

    protected static ?string $modelLabel = 'ตัวเลือก';

    public function form(Schema $schema): Schema
    {
        $storeId = $this->getOwnerRecord()->store_id;

        return $schema->components([
            TextInput::make('name')->label('ชื่อตัวเลือก')->placeholder('เช่น แดง / L (เว้นว่างถ้ามีแบบเดียว)')->maxLength(100)->default(''),
            TextInput::make('sku')->label('SKU')->required()->maxLength(64)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('store_id', $storeId)),
            TextInput::make('price')->label('ราคาขาย (ต่อหน่วยฐาน)')->numeric()->minValue(0)->required()->prefix('฿'),
            TextInput::make('sort')->label('ลำดับ')->numeric()->default(0),
            Toggle::make('is_active')->label('เปิดขาย')->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        $showCost = TenantAccess::can(Permission::REPORTS_FINANCE, $this->getOwnerRecord()->store);

        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('name')->label('ตัวเลือก')->placeholder('—'),
                TextColumn::make('price')->label('ราคา')->money('THB'),
                TextColumn::make('cost')->label('ต้นทุนเฉลี่ย')->numeric(4)->visible($showCost),
                TextColumn::make('stock_levels_sum_qty')->label('คงเหลือ')->sum('stockLevels', 'qty')->numeric(3)->placeholder('0'),
                IconColumn::make('is_active')->label('เปิดขาย')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make(),
                // The last variant can't go: every product needs one.
                DeleteAction::make()->visible(fn () => $this->getOwnerRecord()->variants()->count() > 1),
            ]);
    }
}
