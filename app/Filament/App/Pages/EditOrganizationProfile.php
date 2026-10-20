<?php

namespace App\Filament\App\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class EditOrganizationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'ตั้งค่ากิจการ';
    }

    public function form(Schema $schema): Schema
    {
        $org = $this->tenant;

        return $schema->components([
            Section::make('ข้อมูลกิจการ')->schema([
                TextInput::make('name')->label('ชื่อกิจการ')->required()->maxLength(120),
                Toggle::make('shared_loyalty')->label('ให้สมาชิกสะสมและใช้แต้มข้ามทุกร้านในกิจการ'),
            ]),
            Section::make('แพ็กเกจ')->schema([
                Text::make(fn () => 'แพ็กเกจ: '.$org->plan->name.' · สถานะ: '.$org->status->label()
                    .($org->trial_ends_at ? ' · ทดลองใช้ถึง '.$org->trial_ends_at->timezone('Asia/Bangkok')->format('d/m/Y') : '')),
            ]),
        ]);
    }
}
