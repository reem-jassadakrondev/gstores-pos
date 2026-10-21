<?php

namespace App\Filament\App\Resources\Sales\Pages;

use App\Filament\App\Resources\Sales\SaleResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [SaleResource::reviewAction()];
    }
}
