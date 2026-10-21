<?php

namespace App\Filament\App\Resources\Suppliers;

use App\Filament\App\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Supplier;
use App\Rules\ThaiTaxId;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'จัดซื้อ';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'ผู้ขาย';

    protected static ?string $pluralModelLabel = 'ผู้ขาย / ซัพพลายเออร์';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('ชื่อ')->required()->maxLength(200),
            TextInput::make('tax_id')->label('เลขผู้เสียภาษี')->rule(new ThaiTaxId)->maxLength(13),
            TextInput::make('contact_name')->label('ผู้ติดต่อ')->maxLength(120),
            TextInput::make('phone')->label('โทร')->tel()->maxLength(30),
            TextInput::make('email')->label('อีเมล')->email(),
            TextInput::make('payment_terms_days')->label('เครดิต (วัน)')->numeric()->minValue(0)->default(0),
            Textarea::make('address')->label('ที่อยู่')->rows(2)->columnSpanFull(),
            Textarea::make('note')->label('หมายเหตุ')->rows(2)->columnSpanFull(),
            Toggle::make('is_active')->label('ใช้งาน')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('ชื่อ')->searchable(),
                TextColumn::make('contact_name')->label('ผู้ติดต่อ')->placeholder('—'),
                TextColumn::make('phone')->label('โทร')->placeholder('—'),
                TextColumn::make('payment_terms_days')->label('เครดิต (วัน)'),
                TextColumn::make('purchase_orders_count')->label('ใบสั่งซื้อ')->counts('purchaseOrders'),
                IconColumn::make('is_active')->label('ใช้งาน')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSuppliers::route('/')];
    }
}
