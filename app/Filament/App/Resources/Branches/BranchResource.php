<?php

namespace App\Filament\App\Resources\Branches;

use App\Enums\BranchKind;
use App\Filament\App\Resources\Branches\Pages\ManageBranches;
use App\Filament\Support\TenantAccess;
use App\Models\Branch;
use App\Support\Access\Permission;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'ร้านค้า';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'สาขา';

    protected static ?string $pluralModelLabel = 'สาขา';

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToAccessibleStores(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('store_id')->label('ร้าน')->required()->native(false)
                ->options(fn () => TenantAccess::storeOptions(Permission::STORE_SETTINGS, Permission::ORG_STORES))
                ->disabledOn('edit'),
            TextInput::make('name')->label('ชื่อสาขา')->required()->maxLength(120),
            TextInput::make('code')->label('รหัสสาขา')->required()->maxLength(10)->alphaDash()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, $get) => $rule->where('store_id', $get('store_id'))),
            Select::make('kind')->label('ประเภท')->options(BranchKind::options())->default(BranchKind::Store->value)->required()->native(false),
            TextInput::make('tax_branch_code')->label('เลขที่สาขา (กรมสรรพากร)')->default('00000')->required()
                ->regex('/^\d{5}$/')->helperText('00000 = สำนักงานใหญ่'),
            TextInput::make('phone')->label('เบอร์โทร')->tel()->maxLength(30),
            Textarea::make('address')->label('ที่อยู่สาขา')->rows(2)->columnSpanFull(),
            Toggle::make('is_active')->label('เปิดใช้งาน')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')->label('ร้าน')->sortable(),
                TextColumn::make('code')->label('รหัส'),
                TextColumn::make('name')->label('สาขา')->searchable(),
                TextColumn::make('kind')->label('ประเภท')->formatStateUsing(fn (BranchKind $state) => $state->label()),
                TextColumn::make('tax_branch_code')->label('เลขที่สาขา'),
                TextColumn::make('terminals_count')->label('เครื่อง POS')->counts('terminals'),
                IconColumn::make('is_active')->label('ใช้งาน')->boolean(),
            ])
            ->filters([
                SelectFilter::make('store_id')->label('ร้าน')->relationship('store', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBranches::route('/')];
    }
}
