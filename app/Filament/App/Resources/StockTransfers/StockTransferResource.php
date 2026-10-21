<?php

namespace App\Filament\App\Resources\StockTransfers;

use App\Enums\DocumentStatus;
use App\Exceptions\InsufficientStock;
use App\Filament\App\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\App\Resources\StockTransfers\Pages\EditStockTransfer;
use App\Filament\App\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Filament\App\Resources\StockTransfers\Pages\ViewStockTransfer;
use App\Filament\Support\TenantAccess;
use App\Filament\Support\VariantPicker;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Support\Access\AccessManager;
use App\Support\Access\Permission;
use App\Support\Inventory\StockService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'สต็อก';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'ใบโอนสต็อก';

    protected static ?string $pluralModelLabel = 'โอนสต็อก';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $ids = app(AccessManager::class)->branchIdsWith(TenantAccess::user(), TenantAccess::organization(), Permission::STOCK_TRANSFER);

        return $ids === null ? $query : $query->where(fn ($q) => $q->whereIn('from_branch_id', $ids)->orWhereIn('to_branch_id', $ids));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('from_branch_id')->label('จากสาขา')->required()->native(false)->live()
                    ->options(fn () => TenantAccess::branchOptions(Permission::STOCK_TRANSFER))
                    ->afterStateUpdated(fn ($set) => $set('to_branch_id', null)),
                Select::make('to_branch_id')->label('ไปสาขา')->required()->native(false)
                    ->options(fn ($get) => ($from = Branch::find($get('from_branch_id')))
                        ? Branch::where('store_id', $from->store_id)->whereKeyNot($from->getKey())->pluck('name', 'id')
                        : []),
                Textarea::make('note')->label('หมายเหตุ')->rows(2)->columnSpanFull(),
            ]),
            Section::make('รายการ')->schema([
                Repeater::make('items')->hiddenLabel()->relationship()->minItems(1)->addActionLabel('เพิ่มสินค้า')
                    ->columns(3)
                    ->schema([
                        VariantPicker::make('variant_id', fn ($get) => $get('../../from_branch_id'))->columnSpan(2),
                        TextInput::make('qty')->label('จำนวน (หน่วยฐาน)')->numeric()->minValue(0.001)->required(),
                    ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['fromBranch', 'toBranch'])->withCount('items'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('เลขที่')->searchable(),
                TextColumn::make('fromBranch.name')->label('จาก'),
                TextColumn::make('toBranch.name')->label('ไป'),
                TextColumn::make('items_count')->label('รายการ'),
                TextColumn::make('status')->label('สถานะ')->badge()
                    ->formatStateUsing(fn (DocumentStatus $state) => $state->label())->color(fn (DocumentStatus $state) => $state->color()),
                TextColumn::make('created_at')->label('สร้างเมื่อ')->date('d/m/Y'),
            ])
            ->filters([
                SelectFilter::make('status')->label('สถานะ')->options(collect(DocumentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    /** Send / receive / cancel, shared by the view and edit pages. */
    public static function workflowActions(): array
    {
        $run = function (string $method, string $done) {
            return function (StockTransfer $record, Action $action) use ($method, $done) {
                try {
                    app(StockService::class)->{$method}($record);
                } catch (InsufficientStock|\InvalidArgumentException|\LogicException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }
                Notification::make()->success()->title($done)->send();
            };
        };

        return [
            Action::make('send')->label('ส่งของ (ตัดสต็อกต้นทาง)')->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->visible(fn (StockTransfer $record) => $record->status === DocumentStatus::Draft && auth()->user()->can('send', $record))
                ->action($run('sendTransfer', 'ส่งของแล้ว')),
            Action::make('receive')->label('รับของ (เข้าสต็อกปลายทาง)')->icon(Heroicon::OutlinedInboxArrowDown)->color('success')
                ->requiresConfirmation()
                ->visible(fn (StockTransfer $record) => $record->status === DocumentStatus::Sent && auth()->user()->can('receive', $record))
                ->action($run('receiveTransfer', 'รับของแล้ว')),
            Action::make('cancel')->label('ยกเลิก')->icon(Heroicon::OutlinedXMark)->color('danger')
                ->requiresConfirmation()
                ->modalDescription('ถ้าส่งของแล้ว สต็อกจะคืนกลับสาขาต้นทาง')
                ->visible(fn (StockTransfer $record) => in_array($record->status, [DocumentStatus::Draft, DocumentStatus::Sent], true)
                    && auth()->user()->can('send', $record))
                ->action($run('cancelTransfer', 'ยกเลิกแล้ว')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransfers::route('/'),
            'create' => CreateStockTransfer::route('/create'),
            'view' => ViewStockTransfer::route('/{record}'),
            'edit' => EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
