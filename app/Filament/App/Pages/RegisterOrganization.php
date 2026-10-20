<?php

namespace App\Filament\App\Pages;

use App\Actions\CreateOrganization;
use App\Enums\BusinessType;
use App\Enums\LegalEntityType;
use App\Rules\ThaiTaxId;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** Onboarding: the first screen after sign-up, and "add organization" later on. */
class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'เริ่มต้นใช้งาน '.config('brand.name');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('กิจการของคุณ')->schema([
                TextInput::make('name')->label('ชื่อกิจการ / กลุ่มร้าน')->required()->maxLength(120),
                Select::make('business_type')->label('ประเภทร้านแรก')->options(BusinessType::options())->required()->native(false),
                TextInput::make('store_name')->label('ชื่อร้านแรก')->placeholder('ใช้ชื่อกิจการถ้าเว้นว่าง')->maxLength(120),
            ]),
            Section::make('ข้อมูลผู้เสียภาษี')
                ->description('ใช้พิมพ์บนใบเสร็จและใบกำกับภาษี แก้ไขภายหลังได้')
                ->collapsible()
                ->schema([
                    Select::make('entity_type')->label('ประเภท')->options(LegalEntityType::options())
                        ->default(LegalEntityType::Individual->value)->required()->native(false),
                    TextInput::make('entity_name')->label('ชื่อผู้เสียภาษี / ชื่อบริษัท')->placeholder('ใช้ชื่อกิจการถ้าเว้นว่าง')->maxLength(200),
                    TextInput::make('tax_id')->label('เลขประจำตัวผู้เสียภาษี (13 หลัก)')->rule(new ThaiTaxId)->maxLength(13),
                    Toggle::make('vat_registered')->label('จดทะเบียนภาษีมูลค่าเพิ่ม (VAT)'),
                ]),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        return app(CreateOrganization::class)->handle(auth()->user(), array_filter($data, fn ($v) => $v !== null && $v !== ''));
    }
}
