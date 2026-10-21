<?php

namespace App\Filament\App\Resources\PurchaseOrders;

use App\Enums\DocumentStatus;
use App\Filament\App\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\App\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\App\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\App\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Support\TenantAccess;
use App\Filament\Support\VariantPicker;
use App\Models\Branch;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Access\Permission;
use App\Support\Inventory\StockService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'จัดซื้อ';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'ใบสั่งซื้อ';

    protected static ?string $pluralModelLabel = 'ใบสั่งซื้อ';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        if (TenantAccess::can(Permission::PURCHASING)) {
            return TenantAccess::scopeToBranchesWith($query, Permission::PURCHASING);
        }

        return TenantAccess::scopeToBranchesWith($query, Permission::STOCK_RECEIVE);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                Select::make('branch_id')->label('รับของที่สาขา')->required()->native(false)->live()
                    ->options(fn () => TenantAccess::branchOptions(Permission::PURCHASING))
                    ->afterStateUpdated(function ($state, $set) {
                        $entity = Branch::with('store.legalEntity')->find($state)?->store->legalEntity;
                        $set('vat_rate', $entity?->vat_registered ? (float) $entity->vat_rate : 7);
                    })
                    ->disabledOn('edit'),
                Select::make('supplier_id')->label('ผู้ขาย')->required()->native(false)->searchable()
                    ->options(fn () => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id')),
                DatePicker::make('expected_on')->label('กำหนดรับ'),
                Toggle::make('prices_include_vat')->label('ราคาที่กรอกรวม VAT แล้ว')->inline(false),
                TextInput::make('vat_rate')->label('อัตรา VAT (%)')->numeric()->minValue(0)->maxValue(100)->default(7)->required(),
                TextInput::make('discount')->label('ส่วนลดท้ายบิล')->numeric()->minValue(0)->default(0)->prefix('฿'),
                Textarea::make('note')->label('หมายเหตุ')->rows(2)->columnSpanFull(),
            ]),
            Section::make('รายการสั่งซื้อ')->schema([
                Repeater::make('items')->hiddenLabel()->relationship()->minItems(1)->addActionLabel('เพิ่มสินค้า')
                    ->columns(6)
                    ->schema([
                        VariantPicker::make('variant_id', fn ($get) => $get('../../branch_id'))->columnSpan(3)->live()
                            ->afterStateUpdated(fn ($set) => $set('unit_id', null)),
                        Select::make('unit_id')->label('หน่วย')->native(false)
                            ->placeholder(fn ($get) => ($v = ProductVariant::with('product')->find($get('variant_id'))) ? $v->product->base_unit : 'หน่วยฐาน')
                            ->options(fn ($get) => ($v = ProductVariant::find($get('variant_id')))
                                ? ProductUnit::where('product_id', $v->product_id)->pluck('name', 'id') : []),
                        TextInput::make('qty')->label('จำนวน')->numeric()->minValue(0.001)->required(),
                        TextInput::make('unit_cost')->label('ราคาต่อหน่วย')->numeric()->minValue(0)->required(),
                    ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['branch', 'supplier']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('เลขที่')->searchable(),
                TextColumn::make('supplier.name')->label('ผู้ขาย')->searchable(),
                TextColumn::make('branch.name')->label('สาขา'),
                TextColumn::make('total')->label('ยอดรวม')->money('THB'),
                TextColumn::make('status')->label('สถานะ')->badge()
                    ->formatStateUsing(fn (DocumentStatus $state) => $state->label())->color(fn (DocumentStatus $state) => $state->color()),
                TextColumn::make('expected_on')->label('กำหนดรับ')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('created_at')->label('สร้างเมื่อ')->date('d/m/Y'),
            ])
            ->filters([
                SelectFilter::make('status')->label('สถานะ')->options(collect(DocumentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('supplier_id')->label('ผู้ขาย')->relationship('supplier', 'name'),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    /** Confirm / receive / cancel, shared by the view and edit pages. */
    public static function workflowActions(): array
    {
        return [
            Action::make('order')->label('ยืนยันสั่งซื้อ')->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->visible(fn (PurchaseOrder $record) => $record->status === DocumentStatus::Draft && auth()->user()->can('manage', $record))
                ->action(function (PurchaseOrder $record) {
                    $record->forceFill(['status' => DocumentStatus::Ordered, 'ordered_on' => now()->toDateString()])->save();
                    Notification::make()->success()->title('ยืนยันสั่งซื้อแล้ว')->send();
                }),
            Action::make('receive')->label('รับสินค้า')->icon(Heroicon::OutlinedInboxArrowDown)->color('success')
                ->visible(fn (PurchaseOrder $record) => in_array($record->status, [DocumentStatus::Draft, DocumentStatus::Ordered, DocumentStatus::Partial], true)
                    && auth()->user()->can('receive', $record))
                ->modalDescription('กรอกจำนวนที่ได้รับจริง (หน่วยตามใบสั่งซื้อ) รับบางส่วนได้')
                ->schema(fn (PurchaseOrder $record) => $record->items()->with(['variant.product', 'unit'])->get()
                    ->filter(fn ($item) => $item->outstanding() > 0)
                    ->map(fn ($item) => Fieldset::make($item->variant->displayName().' — ค้างรับ '.rtrim(rtrim(number_format($item->outstanding(), 3), '0'), '.').' '.($item->unit?->name ?? $item->variant->product->base_unit))
                        ->columns(3)
                        ->schema(array_filter([
                            TextInput::make("lines.{$item->getKey()}.qty")->label('รับครั้งนี้')->numeric()->minValue(0)
                                ->maxValue($item->outstanding())->default($item->outstanding()),
                            $item->variant->product->track_lots ? TextInput::make("lines.{$item->getKey()}.lot_no")->label('เลขล็อต') : null,
                            $item->variant->product->track_lots ? DatePicker::make("lines.{$item->getKey()}.expires_on")->label('วันหมดอายุ') : null,
                        ])))
                    ->values()->all())
                ->action(function (PurchaseOrder $record, array $data, Action $action) {
                    try {
                        app(StockService::class)->receivePurchaseOrder($record, $data['lines'] ?? []);
                    } catch (\InvalidArgumentException|\LogicException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->halt();
                    }
                    Notification::make()->success()->title('รับสินค้าเข้าสต็อกแล้ว')->send();
                }),
            Action::make('cancel')->label('ยกเลิก')->icon(Heroicon::OutlinedXMark)->color('danger')
                ->requiresConfirmation()
                ->visible(fn (PurchaseOrder $record) => in_array($record->status, [DocumentStatus::Draft, DocumentStatus::Ordered], true)
                    && auth()->user()->can('manage', $record))
                ->action(fn (PurchaseOrder $record) => $record->forceFill(['status' => DocumentStatus::Cancelled])->save()),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
