<?php

namespace App\Filament\Platform\Resources\Users;

use App\Filament\Platform\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $modelLabel = 'ผู้ใช้';

    protected static ?string $pluralModelLabel = 'ผู้ใช้ทั้งหมด';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('is_platform_admin')->label('Platform admin'),
            Toggle::make('can_access_legacy')->label('เข้าระบบเดิม (legacy) ได้'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('ชื่อ')->searchable(),
                TextColumn::make('email')->label('อีเมล')->searchable(),
                TextColumn::make('organizations_count')->label('กิจการ')->counts('organizations'),
                IconColumn::make('app_authentication_secret')->label('2FA')->boolean()
                    ->getStateUsing(fn (User $r) => $r->app_authentication_secret !== null),
                IconColumn::make('is_platform_admin')->label('Admin')->boolean(),
                TextColumn::make('created_at')->label('สมัครเมื่อ')->date('d/m/Y'),
            ])
            ->recordActions([
                // Can't remove your own admin flag and lock yourself out.
                EditAction::make()
                    ->visible(fn (User $r) => $r->getKey() !== auth()->id())
                    // These flags are deliberately not mass-assignable.
                    ->using(function (User $record, array $data) {
                        $before = $record->only(['is_platform_admin', 'can_access_legacy']);
                        $record->forceFill([
                            'is_platform_admin' => (bool) $data['is_platform_admin'],
                            'can_access_legacy' => (bool) $data['can_access_legacy'],
                        ])->save();
                        app(AuditLogger::class)->record('platform.user_flags_updated', $record, $before, $record->only(array_keys($before)));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
