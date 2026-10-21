<?php

namespace App\Filament\App\Resources\Products;

use App\Actions\CopyProductToStore;
use App\Enums\ProductType;
use App\Enums\VatType;
use App\Filament\App\Resources\Products\Pages\CreateProduct;
use App\Filament\App\Resources\Products\Pages\EditProduct;
use App\Filament\App\Resources\Products\Pages\ListProducts;
use App\Filament\App\Resources\Products\RelationManagers\BarcodesRelationManager;
use App\Filament\App\Resources\Products\RelationManagers\PricesRelationManager;
use App\Filament\App\Resources\Products\RelationManagers\UnitsRelationManager;
use App\Filament\App\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Support\TenantAccess;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Support\Access\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'สินค้า';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'สินค้า';

    protected static ?string $pluralModelLabel = 'สินค้า';

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToAccessibleStores(parent::getEloquentQuery());
    }

    /** Fields shared by the create and edit pages. */
    public static function productFields(): array
    {
        return [
            Select::make('store_id')->label('ร้าน')->required()->native(false)->live()
                ->options(fn () => TenantAccess::storeOptions(Permission::CATALOG_MANAGE))
                ->disabledOn('edit'),
            Select::make('category_id')->label('หมวดหมู่')->native(false)->placeholder('— ไม่ระบุ —')
                ->options(fn ($get) => $get('store_id') ? Category::where('store_id', $get('store_id'))->orderBy('sort')->pluck('name', 'id') : []),
            TextInput::make('name')->label('ชื่อสินค้า')->required()->maxLength(200)->columnSpanFull(),
            Select::make('type')->label('ประเภท')->options(ProductType::options())->default(ProductType::Goods->value)->required()->native(false)->live(),
            TextInput::make('base_unit')->label('หน่วยฐาน')->default('ชิ้น')->required()->maxLength(30)
                ->helperText('หน่วยที่เล็กที่สุดที่นับสต็อก เช่น ชิ้น, ขวด, กรัม'),
            Select::make('vat_type')->label('ภาษี')->options(VatType::options())->default(VatType::Vat->value)->required()->native(false),
            Toggle::make('track_stock')->label('ตัดสต็อก')->default(true)
                ->visible(fn ($get) => $get('type') !== ProductType::Service->value && $get('type') !== ProductType::Service),
            Toggle::make('track_lots')->label('ติดตามล็อต / วันหมดอายุ'),
            Toggle::make('is_weighable')->label('สินค้าชั่งน้ำหนัก')->live(),
            TextInput::make('plu')->label('รหัส PLU (เครื่องชั่ง)')->regex('/^\d{5}$/')->helperText('5 หลัก ตรงกับที่ตั้งในเครื่องชั่ง')
                ->visible(fn ($get) => (bool) $get('is_weighable'))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, $get) => $rule->where('store_id', $get('store_id'))),
            Toggle::make('is_active')->label('เปิดขาย')->default(true),
            Textarea::make('description')->label('รายละเอียด')->rows(2)->columnSpanFull(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('ข้อมูลสินค้า')->columns(2)->schema(self::productFields()),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['store', 'category', 'variants']))
            ->columns([
                TextColumn::make('name')->label('สินค้า')->searchable()->sortable()
                    ->description(fn (Product $r) => $r->variants->count() > 1 ? $r->variants->count().' ตัวเลือก' : null),
                TextColumn::make('sku')->label('SKU')->getStateUsing(fn (Product $r) => $r->variants->first()?->sku)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('variants', fn ($q) => $q->where('sku', 'like', "%{$search}%"))),
                TextColumn::make('price')->label('ราคา')->getStateUsing(fn (Product $r) => $r->variants->first()?->price)->money('THB'),
                TextColumn::make('stock_levels_sum_qty')->label('คงเหลือรวม')->sum('stockLevels', 'qty')->numeric(3)->placeholder('—'),
                TextColumn::make('category.name')->label('หมวด')->placeholder('—'),
                TextColumn::make('store.name')->label('ร้าน')->toggleable(),
                IconColumn::make('is_active')->label('เปิดขาย')->boolean(),
            ])
            ->filters([
                SelectFilter::make('store_id')->label('ร้าน')->relationship('store', 'name'),
                SelectFilter::make('category_id')->label('หมวด')->relationship('category', 'name'),
                TernaryFilter::make('is_active')->label('เปิดขาย'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('copy')
                    ->label('คัดลอกไปร้านอื่น')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->visible(fn (Product $r) => count(self::copyTargets($r)) > 0)
                    ->schema(fn (Product $record) => [
                        Select::make('store_id')->label('ร้านปลายทาง')->options(self::copyTargets($record))->required()->native(false),
                    ])
                    ->action(function (Product $record, array $data, Action $action) {
                        try {
                            app(CopyProductToStore::class)->handle($record, Store::findOrFail($data['store_id']));
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->halt();
                        }
                        Notification::make()->success()->title('คัดลอกสินค้าแล้ว')->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    /** @return array<string, string> */
    private static function copyTargets(Product $product): array
    {
        return collect(TenantAccess::storeOptions(Permission::CATALOG_MANAGE))->except($product->store_id)->all();
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
            UnitsRelationManager::class,
            BarcodesRelationManager::class,
            PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
