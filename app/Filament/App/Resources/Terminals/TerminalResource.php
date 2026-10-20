<?php

namespace App\Filament\App\Resources\Terminals;

use App\Filament\App\Resources\Terminals\Pages\ManageTerminals;
use App\Filament\Support\TenantAccess;
use App\Models\Terminal;
use App\Support\Access\Permission;
use App\Support\Audit\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class TerminalResource extends Resource
{
    protected static ?string $model = Terminal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'ร้านค้า';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'เครื่อง POS';

    protected static ?string $pluralModelLabel = 'เครื่อง POS';

    public static function getEloquentQuery(): Builder
    {
        return TenantAccess::scopeToAccessibleStores(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('branch_id')->label('สาขา')->required()->native(false)
                ->options(fn () => TenantAccess::branchOptions(Permission::STORE_TERMINALS))
                ->disabledOn('edit'),
            TextInput::make('name')->label('ชื่อเครื่อง')->required()->maxLength(60)->placeholder('เช่น เคาน์เตอร์ 1'),
            TextInput::make('code')->label('รหัสเครื่อง')->required()->maxLength(10)->alphaDash()
                ->helperText('อยู่ในเลขที่เอกสารทุกใบ เช่น T01 (แก้ไขไม่ได้หลังเริ่มขาย)')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, $get) => $rule->where('branch_id', $get('branch_id'))),
            TextInput::make('rd_approval_no')->label('เลขอนุมัติเครื่อง POS (ภ.พ.06)')->maxLength(50)
                ->helperText('ใส่เมื่อได้รับอนุมัติจากกรมสรรพากร'),
            Toggle::make('is_active')->label('เปิดใช้งาน')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('store.name')->label('ร้าน')->sortable(),
                TextColumn::make('branch.name')->label('สาขา'),
                TextColumn::make('code')->label('รหัส'),
                TextColumn::make('name')->label('ชื่อเครื่อง')->searchable(),
                TextColumn::make('device_type')->label('อุปกรณ์')->placeholder('—'),
                IconColumn::make('paired_at')->label('จับคู่แล้ว')->boolean()->getStateUsing(fn (Terminal $r) => $r->isPaired()),
                TextColumn::make('last_seen_at')->label('ออนไลน์ล่าสุด')->since()->placeholder('—'),
                IconColumn::make('is_active')->label('ใช้งาน')->boolean(),
            ])
            ->recordActions([
                Action::make('pair')
                    ->label('รหัสจับคู่')
                    ->icon(Heroicon::OutlinedLink)
                    ->requiresConfirmation()
                    ->modalDescription('ถ้าเครื่องนี้จับคู่อยู่แล้ว เครื่องเดิมจะถูกตัดออกเมื่อจับคู่เครื่องใหม่สำเร็จ')
                    ->visible(fn (Terminal $r) => $r->is_active && auth()->user()->can('update', $r))
                    ->action(function (Terminal $record) {
                        $code = $record->issuePairingCode();
                        app(AuditLogger::class)->record('terminal.pairing_code_issued', $record);
                        Notification::make()->success()->persistent()
                            ->title("รหัสจับคู่: {$code}")
                            ->body('เปิดแอป '.config('brand.name').' บนเครื่อง แล้วใส่รหัสนี้ภายใน '.Terminal::PAIRING_TTL_MINUTES.' นาที')
                            ->send();
                    }),
                Action::make('unpair')
                    ->label('ตัดการเชื่อมต่อ')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Terminal $r) => $r->isPaired() && auth()->user()->can('update', $r))
                    ->action(function (Terminal $record) {
                        $record->unpair();
                        app(AuditLogger::class)->record('terminal.unpaired', $record);
                    }),
                EditAction::make(),
                DeleteAction::make()->before(fn (Terminal $record) => $record->unpair()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTerminals::route('/')];
    }
}
