<?php

namespace App\Filament\App\Resources\Sales;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Filament\App\Resources\Sales\Pages\ListSales;
use App\Filament\App\Resources\Sales\Pages\ViewSale;
use App\Filament\Support\TenantAccess;
use App\Models\Sale;
use App\Support\Access\Permission;
use App\Support\Audit\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'การขาย';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'ใบเสร็จ';

    protected static ?string $pluralModelLabel = 'ใบเสร็จ / บิลขาย';

    protected static ?string $recordTitleAttribute = 'number';

    /** Labels for the server's review flags (see PosSync). */
    public const FLAGS = [
        'totals_mismatch' => 'ยอดคำนวณไม่ตรงกับที่เครื่องพิมพ์',
        'payment_mismatch' => 'ยอดชำระไม่ตรงกับยอดบิล',
        'not_authorized' => 'ผู้ขายไม่มีสิทธิ์',
        'discount_over_limit' => 'ส่วนลดเกินเพดานโดยไม่มีผู้อนุมัติ',
        'over_refund' => 'คืนเงินเกินจำนวนที่ขาย',
        'refund_of_voided_sale' => 'คืนเงินบิลที่ถูกยกเลิก',
        'void_not_authorized' => 'ผู้ยกเลิกไม่มีสิทธิ์',
        'vat_setting_mismatch' => 'การตั้งค่า VAT ในเครื่องไม่ตรงกับร้าน',
    ];

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToBranchesWith(parent::getEloquentQuery(), Permission::REPORTS_SALES);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['branch', 'terminal', 'cashier']))
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('เลขที่')->searchable(),
                TextColumn::make('sold_at')->label('เวลา')->dateTime('d/m/Y H:i', 'Asia/Bangkok')->sortable(),
                TextColumn::make('type')->label('ประเภท')->badge()->formatStateUsing(fn (SaleType $state) => $state->label())
                    ->color(fn (SaleType $state) => $state === SaleType::CreditNote ? 'warning' : 'gray'),
                TextColumn::make('branch.name')->label('สาขา'),
                TextColumn::make('terminal.name')->label('เครื่อง')->toggleable(),
                TextColumn::make('cashier.name')->label('ผู้ขาย'),
                TextColumn::make('total')->label('ยอด')->money('THB')->sortable(),
                TextColumn::make('status')->label('สถานะ')->badge()->formatStateUsing(fn (SaleStatus $state) => $state->label())
                    ->color(fn (SaleStatus $state) => $state === SaleStatus::Voided ? 'danger' : 'success'),
                TextColumn::make('review_flags')->label('ต้องตรวจสอบ')->badge()->color('danger')
                    ->formatStateUsing(fn ($state) => self::FLAGS[$state] ?? $state)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label('สาขา')->relationship('branch', 'name'),
                SelectFilter::make('type')->label('ประเภท')->options(collect(SaleType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
                SelectFilter::make('status')->label('สถานะ')->options(collect(SaleStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Filter::make('flagged')->label('ต้องตรวจสอบ')->query(fn (Builder $q) => $q->whereNotNull('review_flags')),
                Filter::make('date')->schema([
                    DatePicker::make('from')->label('ตั้งแต่'),
                    DatePicker::make('until')->label('ถึง'),
                ])->query(fn (Builder $q, array $data) => $q
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->where('sold_at', '>=', now()->parse($d, 'Asia/Bangkok')->startOfDay()->utc()))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->where('sold_at', '<=', now()->parse($d, 'Asia/Bangkok')->endOfDay()->utc()))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                TextEntry::make('number')->label('เลขที่'),
                TextEntry::make('sold_at')->label('เวลา')->dateTime('d/m/Y H:i:s', 'Asia/Bangkok'),
                TextEntry::make('type')->label('ประเภท')->formatStateUsing(fn (SaleType $state) => $state->label()),
                TextEntry::make('status')->label('สถานะ')->formatStateUsing(fn (SaleStatus $state) => $state->label()),
                TextEntry::make('branch.name')->label('สาขา'),
                TextEntry::make('terminal.name')->label('เครื่อง'),
                TextEntry::make('cashier.name')->label('ผู้ขาย'),
                TextEntry::make('refersTo.number')->label('อ้างอิงใบเสร็จ')->placeholder('—'),
                TextEntry::make('void_reason')->label('เหตุผลที่ยกเลิก')->placeholder('—')->visible(fn (Sale $r) => $r->status === SaleStatus::Voided),
                TextEntry::make('review_flags')->label('ต้องตรวจสอบ')->badge()->color('danger')
                    ->formatStateUsing(fn ($state) => self::FLAGS[$state] ?? $state)->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('รายการ')->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(6)->schema([
                    TextEntry::make('sku')->label('SKU'),
                    TextEntry::make('name')->label('สินค้า')->columnSpan(2),
                    TextEntry::make('qty')->label('จำนวน')->numeric(3),
                    TextEntry::make('unit_price')->label('ราคา')->money('THB'),
                    TextEntry::make('line_total')->label('รวม')->money('THB'),
                ]),
            ]),
            Section::make('ยอด')->columns(4)->schema([
                TextEntry::make('subtotal')->label('รวมก่อนส่วนลด')->money('THB'),
                TextEntry::make('discount_total')->label('ส่วนลด')->money('THB'),
                TextEntry::make('vat_amount')->label('VAT')->money('THB'),
                TextEntry::make('total')->label('ยอดสุทธิ')->money('THB')->weight('bold'),
                RepeatableEntry::make('payments')->label('การชำระ')->columns(3)->columnSpanFull()->schema([
                    TextEntry::make('method')->label('วิธี')->formatStateUsing(fn (PaymentMethod $state) => $state->label()),
                    TextEntry::make('amount')->label('จำนวน')->money('THB'),
                    TextEntry::make('reference')->label('อ้างอิง')->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function reviewAction(): Action
    {
        return Action::make('review')
            ->label('ตรวจสอบแล้ว')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->requiresConfirmation()
            ->visible(fn (Sale $record) => auth()->user()->can('review', $record))
            ->action(function (Sale $record) {
                $flags = $record->review_flags;
                $record->forceFill(['review_flags' => null])->save();
                app(AuditLogger::class)->record('sale.reviewed', $record, before: ['flags' => $flags]);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'view' => ViewSale::route('/{record}'),
        ];
    }
}
