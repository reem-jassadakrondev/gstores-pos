<?php

namespace App\Filament\App\Resources\Products\Pages;

use App\Filament\App\Resources\Products\ProductResource;
use App\Filament\Support\TenantAccess;
use App\Models\Branch;
use App\Models\Store;
use App\Support\Access\Permission;
use App\Support\Catalog\ProductImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('import')
                ->label('นำเข้าจาก Excel')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn () => TenantAccess::storeOptions(Permission::CATALOG_MANAGE) !== [])
                ->modalDescription('ไฟล์ .xlsx หรือ .csv แถวแรกเป็นหัวคอลัมน์: รหัสสินค้า, ชื่อสินค้า, บาร์โค้ด, หมวดหมู่, ราคาขาย, ต้นทุน, หน่วย, ยอดยกมา, ภาษี (vat/exempt/zero) · รหัสที่มีอยู่แล้วจะถูกอัปเดต')
                ->schema([
                    Select::make('store_id')->label('ร้าน')->required()->native(false)->live()
                        ->options(fn () => TenantAccess::storeOptions(Permission::CATALOG_MANAGE)),
                    Select::make('branch_id')->label('ลงยอดยกมาที่สาขา')->native(false)->placeholder('— ไม่ลงยอดยกมา —')
                        ->options(fn ($get) => $get('store_id') ? TenantAccess::branchOptions(Permission::STOCK_ADJUST, $get('store_id')) : []),
                    FileUpload::make('file')->label('ไฟล์')->required()->disk('local')->directory('imports')
                        ->acceptedFileTypes([
                            'text/csv', 'text/plain', 'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->maxSize(10240),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);
                    try {
                        $result = app(ProductImporter::class)->import(
                            $path,
                            Store::findOrFail($data['store_id']),
                            filled($data['branch_id'] ?? null) ? Branch::findOrFail($data['branch_id']) : null,
                        );
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    $errors = collect($result['errors'])->take(10)->map(fn ($m, $row) => "แถว {$row}: {$m}")->join("\n");
                    Notification::make()
                        ->{$result['errors'] ? 'warning' : 'success'}()
                        ->title("นำเข้าแล้ว: ใหม่ {$result['created']} · อัปเดต {$result['updated']} · ผิดพลาด ".count($result['errors']))
                        ->body($errors ?: null)
                        ->persistent()
                        ->send();
                }),
            Action::make('template')
                ->label('ไฟล์ตัวอย่าง')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => response()->streamDownload(function () {
                    $writer = new Writer;
                    $writer->openToFile('php://output');
                    $writer->addRow(Row::fromValues(['รหัสสินค้า', 'ชื่อสินค้า', 'บาร์โค้ด', 'หมวดหมู่', 'ราคาขาย', 'ต้นทุน', 'หน่วย', 'ยอดยกมา', 'ภาษี']));
                    $writer->addRow(Row::fromValues(['W600', 'น้ำดื่ม 600ml', '8850999000017', 'เครื่องดื่ม', 7, 4.5, 'ขวด', 120, 'vat']));
                    $writer->close();
                }, 'product-import-template.xlsx')),
        ];
    }
}
