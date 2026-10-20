<?php

namespace App\Filament\App\Resources\Branches\Pages;

use App\Filament\App\Resources\Branches\BranchResource;
use App\Filament\Support\TenantAccess;
use App\Models\Branch;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBranches extends ManageRecords
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('เพิ่มสาขา')
                ->using(fn (array $data, CreateAction $action) => TenantAccess::createWithinPlan(fn () => Branch::create($data), $action)),
        ];
    }
}
