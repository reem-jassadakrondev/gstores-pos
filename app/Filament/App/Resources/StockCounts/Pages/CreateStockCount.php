<?php

namespace App\Filament\App\Resources\StockCounts\Pages;

use App\Filament\App\Resources\StockCounts\StockCountResource;
use App\Support\Inventory\StockService;
use Filament\Resources\Pages\CreateRecord;

class CreateStockCount extends CreateRecord
{
    protected static string $resource = StockCountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'user_id' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        app(StockService::class)->startCount($this->record);
    }
}
