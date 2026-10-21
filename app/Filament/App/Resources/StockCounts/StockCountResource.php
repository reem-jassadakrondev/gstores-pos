<?php

namespace App\Filament\App\Resources\StockCounts;

use App\Enums\DocumentStatus;
use App\Filament\App\Resources\StockCounts\Pages\CreateStockCount;
use App\Filament\App\Resources\StockCounts\Pages\EditStockCount;
use App\Filament\App\Resources\StockCounts\Pages\ListStockCounts;
use App\Filament\App\Resources\StockCounts\RelationManagers\ItemsRelationManager;
use App\Filament\Support\TenantAccess;
use App\Models\Branch;
use App\Models\Category;
use App\Models\StockCount;
use App\Support\Access\Permission;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockCountResource extends Resource
{
    protected static ?string $model = StockCount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'สต็อก';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'ใบตรวจนับ';

    protected static ?string $pluralModelLabel = 'ตรวจนับสต็อก';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToBranchesWith(parent::getEloquentQuery(), Permission::STOCK_ADJUST);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('branch_id')->label('สาขา')->required()->native(false)->live()
                ->options(fn () => TenantAccess::branchOptions(Permission::STOCK_ADJUST))
                ->disabledOn('edit'),
            Select::make('category_id')->label('เฉพาะหมวด')->native(false)->placeholder('ทุกสินค้า')
                ->options(fn ($get) => ($b = Branch::find($get('branch_id'))) ? Category::where('store_id', $b->store_id)->pluck('name', 'id') : [])
                ->disabledOn('edit'),
            Textarea::make('note')->label('หมายเหตุ')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['branch', 'category'])->withCount('items'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('เลขที่')->searchable(),
                TextColumn::make('branch.name')->label('สาขา'),
                TextColumn::make('category.name')->label('หมวด')->placeholder('ทุกสินค้า'),
                TextColumn::make('items_count')->label('รายการ'),
                TextColumn::make('status')->label('สถานะ')->badge()
                    ->formatStateUsing(fn (DocumentStatus $state) => $state->label())->color(fn (DocumentStatus $state) => $state->color()),
                TextColumn::make('created_at')->label('สร้างเมื่อ')->date('d/m/Y'),
            ])
            ->recordActions([EditAction::make()->label('เปิด')]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockCounts::route('/'),
            'create' => CreateStockCount::route('/create'),
            'edit' => EditStockCount::route('/{record}/edit'),
        ];
    }
}
