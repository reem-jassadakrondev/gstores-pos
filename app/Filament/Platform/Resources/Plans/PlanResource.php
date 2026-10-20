<?php

namespace App\Filament\Platform\Resources\Plans;

use App\Filament\Platform\Resources\Plans\Pages\ManagePlans;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $modelLabel = 'แพ็กเกจ';

    protected static ?string $pluralModelLabel = 'แพ็กเกจ';

    public static function form(Schema $schema): Schema
    {
        $limit = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric()->minValue(0)
            ->placeholder('ไม่จำกัด');

        return $schema->components([
            TextInput::make('code')->label('รหัส')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('name')->label('ชื่อ')->required(),
            TextInput::make('price_monthly')->label('ราคา/เดือน')->numeric()->required()->default(0),
            TextInput::make('price_yearly')->label('ราคา/ปี')->numeric()->required()->default(0),
            $limit('max_stores', 'ร้านสูงสุด'),
            $limit('max_branches_per_store', 'สาขาต่อร้าน'),
            $limit('max_terminals', 'เครื่อง POS'),
            $limit('max_members', 'ผู้ใช้'),
            TextInput::make('sort')->label('ลำดับ')->numeric()->default(0),
            Toggle::make('is_public')->label('แสดงในหน้าราคา')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('code')->label('รหัส'),
                TextColumn::make('name')->label('ชื่อ'),
                TextColumn::make('price_monthly')->label('ราคา/เดือน')->money('THB'),
                TextColumn::make('max_stores')->label('ร้าน')->placeholder('ไม่จำกัด'),
                TextColumn::make('max_branches_per_store')->label('สาขา/ร้าน')->placeholder('ไม่จำกัด'),
                TextColumn::make('max_terminals')->label('เครื่อง')->placeholder('ไม่จำกัด'),
                TextColumn::make('max_members')->label('ผู้ใช้')->placeholder('ไม่จำกัด'),
                TextColumn::make('organizations_count')->label('ลูกค้า')->counts('organizations'),
                IconColumn::make('is_public')->label('สาธารณะ')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlans::route('/')];
    }
}
