<?php

namespace App\Filament\App\Resources\StockLevels\Pages;

use App\Exceptions\InsufficientStock;
use App\Filament\App\Resources\StockLevels\StockLevelResource;
use App\Filament\Support\TenantAccess;
use App\Filament\Support\VariantPicker;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Support\Access\Permission;
use App\Support\Inventory\StockService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStockLevels extends ListRecords
{
    protected static string $resource = StockLevelResource::class;

    protected function getHeaderActions(): array
    {
        $branches = fn () => TenantAccess::branchOptions(Permission::STOCK_ADJUST);

        $lotFields = [
            TextInput::make('lot_no')->label('เลขล็อต')->maxLength(64)
                ->visible(fn ($get) => $this->tracksLots($get('variant_id'))),
            DatePicker::make('expires_on')->label('วันหมดอายุ')
                ->visible(fn ($get) => $this->tracksLots($get('variant_id'))),
        ];

        return [
            Action::make('adjust')
                ->label('ปรับสต็อก')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->visible(fn () => $branches() !== [])
                ->schema([
                    Select::make('branch_id')->label('สาขา')->options($branches)->required()->native(false)->live(),
                    VariantPicker::make('variant_id', fn ($get) => $get('branch_id'))->live(),
                    TextInput::make('qty')->label('เพิ่ม (+) / ลด (−) จำนวนหน่วยฐาน')->numeric()->required()->notIn([0]),
                    TextInput::make('note')->label('เหตุผล')->required()->maxLength(200)->placeholder('เช่น ของเสีย, แตก, พบเพิ่ม'),
                    ...$lotFields,
                ])
                ->action(fn (array $data, Action $action) => $this->post($action, fn () => app(StockService::class)->adjust(
                    Branch::findOrFail($data['branch_id']),
                    ProductVariant::findOrFail($data['variant_id']),
                    (float) $data['qty'],
                    $data['note'],
                    array_filter(['lot_no' => $data['lot_no'] ?? null, 'expires_on' => $data['expires_on'] ?? null]),
                ))),
            Action::make('opening')
                ->label('ยอดยกมา')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('gray')
                ->visible(fn () => $branches() !== [])
                ->modalDescription('ใช้ตอนเริ่มใช้ระบบ ต้นทุนที่ใส่จะเข้าไปคำนวณต้นทุนเฉลี่ย')
                ->schema([
                    Select::make('branch_id')->label('สาขา')->options($branches)->required()->native(false)->live(),
                    VariantPicker::make('variant_id', fn ($get) => $get('branch_id'))->live(),
                    TextInput::make('qty')->label('จำนวน (หน่วยฐาน)')->numeric()->minValue(0.001)->required(),
                    TextInput::make('unit_cost')->label('ต้นทุนต่อหน่วยฐาน')->numeric()->minValue(0)->required()->prefix('฿'),
                    ...$lotFields,
                ])
                ->action(fn (array $data, Action $action) => $this->post($action, fn () => app(StockService::class)->opening(
                    Branch::findOrFail($data['branch_id']),
                    ProductVariant::findOrFail($data['variant_id']),
                    (float) $data['qty'],
                    (float) $data['unit_cost'],
                    array_filter(['lot_no' => $data['lot_no'] ?? null, 'expires_on' => $data['expires_on'] ?? null]),
                ))),
        ];
    }

    private function tracksLots(?string $variantId): bool
    {
        return $variantId && (bool) ProductVariant::with('product')->find($variantId)?->product->track_lots;
    }

    private function post(Action $action, callable $move): void
    {
        try {
            $move();
        } catch (InsufficientStock|\InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();
        }
        Notification::make()->success()->title('บันทึกสต็อกแล้ว')->send();
    }
}
