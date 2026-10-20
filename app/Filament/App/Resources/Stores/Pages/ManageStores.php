<?php

namespace App\Filament\App\Resources\Stores\Pages;

use App\Filament\App\Resources\Stores\StoreResource;
use App\Filament\Support\TenantAccess;
use App\Models\Store;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStores extends ManageRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        $remaining = TenantAccess::remaining('stores');

        return [
            CreateAction::make()
                ->label('เพิ่มร้าน'.($remaining !== null ? " (เหลือ {$remaining})" : ''))
                ->using(fn (array $data, CreateAction $action) => TenantAccess::createWithinPlan(fn () => Store::create($data), $action)),
        ];
    }
}
