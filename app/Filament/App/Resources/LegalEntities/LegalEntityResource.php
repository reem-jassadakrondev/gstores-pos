<?php

namespace App\Filament\App\Resources\LegalEntities;

use App\Enums\LegalEntityType;
use App\Filament\App\Resources\LegalEntities\Pages\ManageLegalEntities;
use App\Models\LegalEntity;
use App\Rules\ThaiTaxId;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LegalEntityResource extends Resource
{
    protected static ?string $model = LegalEntity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'ร้านค้า';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'นิติบุคคล / ผู้เสียภาษี';

    protected static ?string $pluralModelLabel = 'นิติบุคคล / ผู้เสียภาษี';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('ประเภท')->options(LegalEntityType::options())->required()->native(false),
            TextInput::make('name')->label('ชื่อ (ตามที่จดทะเบียน)')->required()->maxLength(200),
            TextInput::make('tax_id')->label('เลขประจำตัวผู้เสียภาษี')->rule(new ThaiTaxId)->maxLength(13),
            Toggle::make('vat_registered')->label('จดทะเบียน VAT')->live(),
            TextInput::make('vat_rate')->label('อัตรา VAT (%)')->numeric()->default(7)->minValue(0)->maxValue(100)
                ->visible(fn ($get) => $get('vat_registered')),
            TextInput::make('phone')->label('เบอร์โทร')->tel()->maxLength(30),
            Textarea::make('address')->label('ที่อยู่ (พิมพ์บนใบกำกับภาษี)')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('ชื่อ')->searchable(),
                TextColumn::make('type')->label('ประเภท')->formatStateUsing(fn (LegalEntityType $state) => $state->label()),
                TextColumn::make('tax_id')->label('เลขผู้เสียภาษี')->placeholder('—'),
                IconColumn::make('vat_registered')->label('VAT')->boolean(),
                TextColumn::make('stores_count')->label('จำนวนร้าน')->counts('stores'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLegalEntities::route('/')];
    }
}
