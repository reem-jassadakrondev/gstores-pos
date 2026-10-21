<?php

namespace App\Filament\App\Resources\Stores;

use App\Enums\BusinessType;
use App\Filament\App\Resources\Stores\Pages\ManageStores;
use App\Filament\Support\TenantAccess;
use App\Models\LegalEntity;
use App\Models\Store;
use App\Support\Sales\PosSettings;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'ร้านค้า';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'ร้าน';

    protected static ?string $pluralModelLabel = 'ร้าน';

    /** Modules that later phases build; listed now so a store's setup is complete from day one. */
    public const MODULES = [
        'barcode' => 'บาร์โค้ด',
        'variants' => 'ตัวเลือกสินค้า (ไซซ์/สี)',
        'modifiers' => 'ตัวเลือกเสริม (หวาน/ท็อปปิ้ง)',
        'queue' => 'บัตรคิว',
        'tables' => 'โต๊ะ / ผังร้าน',
        'kitchen' => 'จอครัว / เครื่องพิมพ์ครัว',
        'service_charge' => 'ค่าบริการ',
        'loyalty' => 'แต้มสะสม',
        'credit' => 'ขายเชื่อ / ลูกหนี้',
        'delivery_routes' => 'สายส่ง / เส้นทางจัดส่ง',
        'multi_unit' => 'หน่วยนับหลายระดับ',
        'billing_notes' => 'ใบวางบิล',
        'appointments' => 'นัดหมาย',
    ];

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToAccessibleStores(parent::getEloquentQuery(), 'id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('ชื่อร้าน')->required()->maxLength(120),
            TextInput::make('code')->label('รหัสร้าน')->required()->maxLength(10)->alphaDash()
                ->helperText('ใช้ขึ้นต้นเลขที่เอกสาร เช่น S01')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('organization_id', TenantAccess::organization()->getKey())),
            Select::make('legal_entity_id')->label('นิติบุคคล / ผู้เสียภาษี')
                ->options(fn () => LegalEntity::pluck('name', 'id'))->required()->native(false),
            Select::make('business_type')->label('ประเภทธุรกิจ')->options(BusinessType::options())->required()->native(false)
                ->live()
                ->afterStateUpdated(fn ($state, $set) => $state && $set('modules', BusinessType::from($state)->defaultModules())),
            CheckboxList::make('modules')->label('โมดูลที่เปิดใช้')->options(self::MODULES)->columns(3)->columnSpanFull(),
            Toggle::make('is_active')->label('เปิดใช้งาน')->default(true),
            Section::make('ตั้งค่าหน้าขาย (POS)')->columns(2)->columnSpanFull()->collapsible()->schema([
                TextInput::make('settings.promptpay_id')->label('พร้อมเพย์ (เบอร์โทร / เลขผู้เสียภาษี)')->maxLength(20)
                    ->helperText('ใช้สร้าง QR รับเงินบนเครื่อง POS · เงินเข้าบัญชีของร้านนี้'),
                Toggle::make('settings.prices_include_vat')->label('ราคาสินค้ารวม VAT แล้ว')->default(PosSettings::DEFAULTS['prices_include_vat'])->inline(false),
                Select::make('settings.rounding_step')->label('ปัดเศษยอดรวม')->native(false)
                    ->options(['0.01' => 'ไม่ปัด (ถึงสตางค์)', '0.25' => '25 สตางค์', '0.5' => '50 สตางค์', '1' => '1 บาท'])->default('0.01'),
                TextInput::make('settings.max_discount_percent')->label('ส่วนลดสูงสุดโดยไม่ต้องใช้ PIN ผู้จัดการ (%)')
                    ->numeric()->minValue(0)->maxValue(100)->default(PosSettings::DEFAULTS['max_discount_percent']),
                Select::make('settings.paper_width')->label('ขนาดกระดาษใบเสร็จ')->native(false)->options(['58' => '58 มม.', '80' => '80 มม.'])->default('80'),
                Select::make('settings.scale_barcode_mode')->label('บาร์โค้ดเครื่องชั่ง')->native(false)
                    ->options(['weight' => 'ฝังน้ำหนัก', 'price' => 'ฝังราคา'])->default('weight'),
                Textarea::make('settings.receipt_header')->label('ข้อความหัวใบเสร็จ')->rows(2),
                Textarea::make('settings.receipt_footer')->label('ข้อความท้ายใบเสร็จ')->rows(2)->default(PosSettings::DEFAULTS['receipt_footer']),
            ]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('รหัส')->sortable(),
                TextColumn::make('name')->label('ชื่อร้าน')->searchable(),
                TextColumn::make('business_type')->label('ประเภท')->badge()->formatStateUsing(fn (BusinessType $state) => $state->label()),
                TextColumn::make('legalEntity.name')->label('นิติบุคคล'),
                TextColumn::make('branches_count')->label('สาขา')->counts('branches'),
                IconColumn::make('is_active')->label('ใช้งาน')->boolean(),
            ])
            ->defaultSort('code')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStores::route('/')];
    }
}
