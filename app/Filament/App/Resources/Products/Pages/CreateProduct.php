<?php

namespace App\Filament\App\Resources\Products\Pages;

use App\Actions\CreateProduct as CreateProductAction;
use App\Filament\App\Resources\Products\ProductResource;
use App\Models\Barcode;
use App\Models\ProductVariant;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('ข้อมูลสินค้า')->columns(2)->schema(ProductResource::productFields()),
            Section::make('รหัสสินค้าและราคา')
                ->description('เพิ่มตัวเลือก (สี/ไซซ์), หน่วยนับ และราคาตามช่องทางได้หลังบันทึก')
                ->columns(3)
                ->schema([
                    TextInput::make('sku')->label('รหัสสินค้า (SKU)')->required()->maxLength(64)
                        ->unique(ProductVariant::class, 'sku', modifyRuleUsing: fn (Unique $rule, $get) => $rule->where('store_id', $get('store_id'))),
                    TextInput::make('barcode')->label('บาร์โค้ด')->maxLength(64)
                        ->unique(Barcode::class, 'code', modifyRuleUsing: fn (Unique $rule, $get) => $rule->where('store_id', $get('store_id'))),
                    TextInput::make('price')->label('ราคาขาย')->numeric()->minValue(0)->required()->prefix('฿'),
                ]),
        ])->columns(1);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $variant = ['sku' => $data['sku'], 'barcode' => $data['barcode'] ?? null, 'price' => $data['price']];
        unset($data['sku'], $data['barcode'], $data['price']);

        return app(CreateProductAction::class)->handle($data, $variant);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
