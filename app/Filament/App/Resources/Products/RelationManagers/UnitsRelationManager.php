<?php

namespace App\Filament\App\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'หน่วยนับ';

    protected static ?string $modelLabel = 'หน่วยนับ';

    public function form(Schema $schema): Schema
    {
        $product = $this->getOwnerRecord();

        return $schema->components([
            TextInput::make('name')->label('ชื่อหน่วย')->placeholder('เช่น แพ็ค, ลัง')->required()->maxLength(30)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $product->getKey())),
            TextInput::make('factor')->label("เท่ากับกี่ {$product->base_unit}")->numeric()->minValue(0.001)->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        $base = $this->getOwnerRecord()->base_unit;

        return $table
            ->recordTitleAttribute('name')
            ->description("หน่วยฐาน: {$base} · ราคาหน่วยใหญ่ = ราคาหน่วยฐาน × จำนวน (ตั้งราคาพิเศษได้ที่แท็บราคา)")
            ->columns([
                TextColumn::make('name')->label('หน่วย'),
                TextColumn::make('factor')->label("จำนวน {$base}")->numeric(3),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
