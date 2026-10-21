<?php

namespace App\Filament\App\Resources\Members;

use App\Enums\OrgRole;
use App\Filament\App\Resources\Members\Pages\ManageMembers;
use App\Filament\Support\TenantAccess;
use App\Models\Branch;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Policies\OrganizationMemberPolicy;
use App\Support\Access\Permission;
use App\Support\Audit\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use UnitEnum;

class MemberResource extends Resource
{
    protected static ?string $model = OrganizationMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'ทีมงาน';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'ผู้ใช้';

    protected static ?string $pluralModelLabel = 'ผู้ใช้และพนักงาน';

    protected static ?string $slug = 'members';

    public static function managesWholeOrganization(): bool
    {
        return TenantAccess::can(Permission::ORG_MEMBERS);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        if (self::managesWholeOrganization()) {
            return $query;
        }
        $stores = OrganizationMemberPolicy::staffStores(TenantAccess::user(), TenantAccess::organization()->getKey());

        // Never themselves: nobody edits their own permissions.
        return $query->where('org_role', OrgRole::Member)
            ->where('user_id', '!=', TenantAccess::user()->getKey())
            ->whereIn('user_id', RoleAssignment::whereIn('store_id', $stores)->select('user_id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('บัญชีผู้ใช้')->visibleOn('create')->columns(3)->schema([
                TextInput::make('user_name')->label('ชื่อ')->required()->maxLength(120),
                TextInput::make('user_email')->label('อีเมล')->email()->required(),
                TextInput::make('user_password')->label('รหัสผ่านเริ่มต้น')->password()->revealable()->minLength(8)
                    ->helperText('ใส่เมื่อเป็นผู้ใช้ใหม่ ถ้าอีเมลมีบัญชีอยู่แล้วจะใช้บัญชีเดิม'),
            ]),
            Section::make('สิทธิ์ระดับกิจการ')->columns(2)->visible(fn () => self::managesWholeOrganization())->schema([
                Select::make('org_role')->label('บทบาท')->options(OrgRole::assignable())
                    ->default(OrgRole::Member->value)->required()->native(false)->live()
                    ->helperText('ผู้ดูแลระบบและนักบัญชีเห็นทุกร้าน · พนักงานเห็นเฉพาะร้านที่กำหนดด้านล่าง'),
                Toggle::make('is_active')->label('เปิดใช้งาน')->default(true),
            ]),
            Section::make('บทบาทในร้าน / สาขา')
                ->visible(fn ($get) => ! self::managesWholeOrganization() || in_array($get('org_role'), [OrgRole::Member->value, OrgRole::Member], true))
                ->schema([
                    Repeater::make('roleAssignments')->hiddenLabel()
                        // A store manager only sees (and so can only change) assignments in their own stores.
                        ->relationship(modifyQueryUsing: fn (Builder $query) => self::managesWholeOrganization()
                            ? $query
                            : $query->whereIn('store_id', OrganizationMemberPolicy::staffStores(TenantAccess::user(), TenantAccess::organization()->getKey())))
                        ->minItems(fn () => self::managesWholeOrganization() ? 0 : 1)
                        ->addActionLabel('เพิ่มบทบาท')
                        ->columns(3)
                        ->schema([
                            Select::make('role_id')->label('บทบาท')->required()->native(false)
                                ->options(fn () => Role::orderBy('name')->pluck('name', 'id')),
                            Select::make('store_id')->label('ร้าน')->required()->native(false)->live()
                                ->options(fn () => TenantAccess::storeOptions(Permission::STORE_STAFF, Permission::ORG_MEMBERS))
                                ->afterStateUpdated(fn ($set) => $set('branch_id', null)),
                            Select::make('branch_id')->label('สาขา')->placeholder('ทุกสาขา')->native(false)
                                ->options(fn ($get) => $get('store_id')
                                    ? Branch::where('store_id', $get('store_id'))->orderBy('code')->pluck('name', 'id')
                                    : []),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'roleAssignments.role', 'roleAssignments.store']))
            ->columns([
                TextColumn::make('user.name')->label('ชื่อ')->searchable(),
                TextColumn::make('user.email')->label('อีเมล')->searchable(),
                TextColumn::make('org_role')->label('บทบาท')->badge()
                    ->formatStateUsing(fn (OrgRole $state) => $state->label())
                    ->color(fn (OrgRole $state) => match ($state) {
                        OrgRole::Owner => 'warning', OrgRole::Admin => 'primary', OrgRole::Accountant => 'info', default => 'gray',
                    }),
                TextColumn::make('assignments')->label('บทบาทในร้าน')->wrap()
                    ->getStateUsing(fn (OrganizationMember $r) => $r->roleAssignments
                        ->map(fn ($a) => $a->role->name.' @ '.$a->store->name)->join(', ') ?: '—'),
                IconColumn::make('pin')->label('PIN')->boolean()->getStateUsing(fn (OrganizationMember $r) => $r->hasPin()),
                IconColumn::make('is_active')->label('ใช้งาน')->boolean(),
            ])
            ->recordActions([
                Action::make('setPin')
                    ->label('ตั้ง PIN')
                    ->icon(Heroicon::OutlinedKey)
                    ->visible(fn (OrganizationMember $r) => auth()->user()->can('update', $r) || $r->user_id === auth()->id())
                    ->schema([
                        TextInput::make('pin')->label('PIN 4–6 หลัก')->password()->revealable()->required()->regex('/^\d{4,6}$/'),
                        TextInput::make('pin_confirmation')->label('ยืนยัน PIN')->password()->required()->same('pin'),
                    ])
                    ->action(function (OrganizationMember $record, array $data, Action $action) {
                        try {
                            $record->setPin($data['pin']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->halt();
                        }
                        app(AuditLogger::class)->record('member.pin_set', $record);
                        Notification::make()->success()->title('ตั้ง PIN แล้ว')->send();
                    }),
                EditAction::make(),
                DeleteAction::make()->label('นำออก')
                    ->after(fn (OrganizationMember $record) => $record->roleAssignments()->delete()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMembers::route('/')];
    }
}
