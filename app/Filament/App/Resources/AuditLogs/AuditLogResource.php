<?php

namespace App\Filament\App\Resources\AuditLogs;

use App\Filament\App\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'ระบบ';

    protected static ?string $modelLabel = 'บันทึกการใช้งาน';

    protected static ?string $pluralModelLabel = 'บันทึกการใช้งาน (Audit log)';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('เวลา')->dateTime('d/m/Y H:i:s', 'Asia/Bangkok')->sortable(),
                TextColumn::make('user.name')->label('ผู้ใช้')->placeholder('ระบบ'),
                TextColumn::make('terminal.name')->label('เครื่อง')->placeholder('—'),
                TextColumn::make('action')->label('การกระทำ')->badge()->searchable(),
                TextColumn::make('store.name')->label('ร้าน')->placeholder('—'),
                TextColumn::make('meta.ip')->label('IP')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->label('การกระทำ')
                    ->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')),
            ])
            ->recordActions([
                Action::make('details')->label('รายละเอียด')->icon(Heroicon::OutlinedEye)
                    ->modalSubmitAction(false)
                    ->modalContent(fn (AuditLog $record) => new HtmlString(
                        '<pre style="white-space:pre-wrap;font-size:12px">'.e(json_encode(
                            ['before' => $record->before, 'after' => $record->after, 'meta' => $record->meta],
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                        )).'</pre>'
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
