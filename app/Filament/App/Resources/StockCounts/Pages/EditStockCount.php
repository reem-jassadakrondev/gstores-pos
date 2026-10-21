<?php

namespace App\Filament\App\Resources\StockCounts\Pages;

use App\Enums\DocumentStatus;
use App\Filament\App\Resources\StockCounts\StockCountResource;
use App\Support\Inventory\StockService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditStockCount extends EditRecord
{
    protected static string $resource = StockCountResource::class;

    protected function getHeaderActions(): array
    {
        $draft = fn () => $this->record->status === DocumentStatus::Draft;

        return [
            Action::make('complete')
                ->label('ยืนยันผลตรวจนับ')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn () => 'ปรับสต็อกตามจำนวนที่นับได้ '.$this->record->items()->whereNotNull('counted_qty')->count()
                    .' รายการ (รายการที่ไม่ได้กรอกจะไม่ถูกปรับ) และแก้ไขไม่ได้อีก')
                ->visible($draft)
                ->action(function () {
                    app(StockService::class)->completeCount($this->record);
                    Notification::make()->success()->title('ปรับสต็อกตามผลตรวจนับแล้ว')->send();
                    $this->refreshFormData(['status']);
                }),
            DeleteAction::make()->visible($draft),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->record->status === DocumentStatus::Draft ? parent::getFormActions() : [];
    }
}
