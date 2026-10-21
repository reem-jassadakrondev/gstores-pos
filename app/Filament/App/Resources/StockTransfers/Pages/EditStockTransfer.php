<?php

namespace App\Filament\App\Resources\StockTransfers\Pages;

use App\Filament\App\Resources\StockTransfers\StockTransferResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStockTransfer extends EditRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [...StockTransferResource::workflowActions(), DeleteAction::make()];
    }
}
