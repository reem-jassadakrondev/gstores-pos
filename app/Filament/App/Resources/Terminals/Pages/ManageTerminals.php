<?php

namespace App\Filament\App\Resources\Terminals\Pages;

use App\Filament\App\Resources\Terminals\TerminalResource;
use App\Filament\Support\TenantAccess;
use App\Models\Terminal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTerminals extends ManageRecords
{
    protected static string $resource = TerminalResource::class;

    protected function getHeaderActions(): array
    {
        $remaining = TenantAccess::remaining('terminals');

        return [
            CreateAction::make()
                ->label('เพิ่มเครื่อง POS'.($remaining !== null ? " (เหลือ {$remaining})" : ''))
                ->using(fn (array $data, CreateAction $action) => TenantAccess::createWithinPlan(fn () => Terminal::create($data), $action)),
        ];
    }
}
