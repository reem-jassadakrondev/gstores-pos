<?php

namespace App\Filament\App\Resources\Shifts;

use App\Enums\PaymentMethod;
use App\Filament\App\Resources\Shifts\Pages\ListShifts;
use App\Filament\App\Resources\Shifts\Pages\ViewShift;
use App\Filament\Support\TenantAccess;
use App\Models\Shift;
use App\Support\Access\Permission;
use App\Support\Sales\ShiftSummary;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'การขาย';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'กะ';

    protected static ?string $pluralModelLabel = 'กะและรายงาน X/Z';

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToBranchesWith(parent::getEloquentQuery(), Permission::REPORTS_SALES);
    }

    /** Z figures for closed shifts; live X figures for open ones. */
    public static function summaryOf(Shift $shift): array
    {
        return $shift->summary ?? app(ShiftSummary::class)->build($shift);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['branch', 'terminal', 'openedBy']))
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('opened_at')->label('เปิดกะ')->dateTime('d/m/Y H:i', 'Asia/Bangkok')->sortable(),
                TextColumn::make('closed_at')->label('ปิดกะ')->dateTime('d/m/Y H:i', 'Asia/Bangkok')->placeholder('ยังเปิดอยู่'),
                TextColumn::make('branch.name')->label('สาขา'),
                TextColumn::make('terminal.name')->label('เครื่อง'),
                TextColumn::make('openedBy.name')->label('แคชเชียร์'),
                TextColumn::make('z_number')->label('Z #')->placeholder('—'),
                TextColumn::make('summary.net_sales')->label('ยอดขายสุทธิ')->money('THB')->placeholder('—'),
                TextColumn::make('summary.difference')->label('เงินขาด/เกิน')->money('THB')->placeholder('—')
                    ->color(fn ($state) => $state === null || abs((float) $state) < 0.005 ? null : ((float) $state < 0 ? 'danger' : 'warning')),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label('สาขา')->relationship('branch', 'name'),
                TernaryFilter::make('open')->label('ยังเปิดอยู่')
                    ->queries(true: fn ($q) => $q->whereNull('closed_at'), false: fn ($q) => $q->whereNotNull('closed_at')),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn (string $key) => fn (Shift $record) => number_format((float) data_get(self::summaryOf($record), $key, 0), 2);

        return $schema->components([
            Section::make(fn (Shift $record) => $record->isOpen() ? 'รายงาน X (ระหว่างกะ)' : "รายงาน Z #{$record->z_number}")
                ->columns(4)
                ->schema([
                    TextEntry::make('terminal.name')->label('เครื่อง'),
                    TextEntry::make('openedBy.name')->label('เปิดโดย'),
                    TextEntry::make('opened_at')->label('เปิดกะ')->dateTime('d/m/Y H:i', 'Asia/Bangkok'),
                    TextEntry::make('closed_at')->label('ปิดกะ')->dateTime('d/m/Y H:i', 'Asia/Bangkok')->placeholder('ยังเปิดอยู่'),
                    TextEntry::make('receipts')->label('จำนวนบิล')->state(fn (Shift $r) => self::summaryOf($r)['receipts']['count']),
                    TextEntry::make('net_sales')->label('ยอดขายสุทธิ')->state($money('net_sales')),
                    TextEntry::make('net_vat')->label('VAT สุทธิ')->state($money('net_vat')),
                    TextEntry::make('discount')->label('ส่วนลด')->state($money('receipts.discount')),
                    TextEntry::make('credit_notes')->label('ใบลดหนี้')->state(fn (Shift $r) => self::summaryOf($r)['credit_notes']['count'].' ใบ / '.number_format(self::summaryOf($r)['credit_notes']['total'], 2)),
                    TextEntry::make('voids')->label('บิลยกเลิก')->state(fn (Shift $r) => self::summaryOf($r)['voids']['count'].' ใบ / '.number_format(self::summaryOf($r)['voids']['total'], 2)),
                    TextEntry::make('range')->label('เลขที่บิล')->state(fn (Shift $r) => (self::summaryOf($r)['first_number'] ?? '—').' ถึง '.(self::summaryOf($r)['last_number'] ?? '—'))->columnSpan(2),
                ]),
            Section::make('เงินสดในลิ้นชัก')->columns(4)->schema([
                TextEntry::make('cash_opening')->label('เงินทอนตั้งต้น')->state($money('cash.opening')),
                TextEntry::make('cash_sales')->label('รับเงินสดสุทธิ')->state($money('cash.sales_net')),
                TextEntry::make('cash_in_out')->label('นำเข้า / นำออก')->state(fn (Shift $r) => number_format(self::summaryOf($r)['cash']['pay_in'], 2).' / '.number_format(self::summaryOf($r)['cash']['pay_out'], 2)),
                TextEntry::make('cash_expected')->label('ควรมี')->state($money('cash.expected'))->weight('bold'),
                TextEntry::make('counted_cash')->label('นับได้')->money('THB')->placeholder('—'),
                TextEntry::make('summary.difference')->label('ขาด / เกิน')->money('THB')->placeholder('—'),
            ]),
            Section::make('ยอดตามวิธีชำระ')->schema([
                KeyValueEntry::make('payments')->hiddenLabel()
                    ->state(fn (Shift $r) => collect(self::summaryOf($r)['payments'])->mapWithKeys(fn ($v, $k) => [
                        PaymentMethod::from($k)->label() => number_format($v['net'], 2),
                    ])->all()),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'view' => ViewShift::route('/{record}'),
        ];
    }
}
