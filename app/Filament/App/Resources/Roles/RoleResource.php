<?php

namespace App\Filament\App\Resources\Roles;

use App\Filament\App\Resources\Roles\Pages\ManageRoles;
use App\Models\Role;
use App\Support\Access\Permission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'ทีมงาน';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'บทบาท';

    protected static ?string $pluralModelLabel = 'บทบาทและสิทธิ์';

    /** @return array<string, string> "group · label" for every store permission */
    public static function permissionOptions(): array
    {
        $options = [];
        foreach (Permission::storeGroups() as $group => $permissions) {
            foreach ($permissions as $key => $label) {
                $options[$key] = "{$group} · {$label}";
            }
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('ชื่อบทบาท')->required()->maxLength(60),
            CheckboxList::make('permissions')->label('สิทธิ์')
                ->options(self::permissionOptions())
                ->bulkToggleable()->searchable()->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('บทบาท')->searchable(),
                TextColumn::make('permissions')->label('จำนวนสิทธิ์')->getStateUsing(fn (Role $r) => count($r->permissions ?? [])),
                TextColumn::make('assignments_count')->label('ผู้ใช้')->counts('assignments'),
                IconColumn::make('is_default')->label('ค่าเริ่มต้น')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function generateKey(string $name): string
    {
        return 'custom_'.(Str::slug($name, '_') ?: Str::lower(Str::random(6))).'_'.Str::lower(Str::random(4));
    }

    public static function getPages(): array
    {
        return ['index' => ManageRoles::route('/')];
    }
}
