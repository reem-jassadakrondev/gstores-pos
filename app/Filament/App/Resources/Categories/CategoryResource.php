<?php

namespace App\Filament\App\Resources\Categories;

use App\Filament\App\Resources\Categories\Pages\ManageCategories;
use App\Filament\Support\TenantAccess;
use App\Models\Category;
use App\Support\Access\Permission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'สินค้า';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'หมวดหมู่';

    protected static ?string $pluralModelLabel = 'หมวดหมู่';

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToAccessibleStores(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('store_id')->label('ร้าน')->required()->native(false)->live()
                ->options(fn () => TenantAccess::storeOptions(Permission::CATALOG_MANAGE))
                ->disabledOn('edit'),
            Select::make('parent_id')->label('อยู่ภายใต้หมวด')->native(false)->placeholder('— หมวดหลัก —')
                ->options(fn ($get, $record) => $get('store_id')
                    ? Category::where('store_id', $get('store_id'))->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->pluck('name', 'id')
                    : []),
            TextInput::make('name')->label('ชื่อหมวด')->required()->maxLength(100),
            TextInput::make('sort')->label('ลำดับ')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('store.name')->label('ร้าน'),
                TextColumn::make('name')->label('หมวด')->searchable(),
                TextColumn::make('parent.name')->label('หมวดหลัก')->placeholder('—'),
                TextColumn::make('products_count')->label('สินค้า')->counts('products'),
            ])
            ->filters([SelectFilter::make('store_id')->label('ร้าน')->relationship('store', 'name')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
