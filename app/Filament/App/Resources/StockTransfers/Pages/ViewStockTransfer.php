<?php

namespace App\Filament\App\Resources\StockTransfers\Pages;

use App\Filament\App\Resources\StockTransfers\StockTransferResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStockTransfer extends ViewRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [...StockTransferResource::workflowActions(), EditAction::make()];
    }
}
