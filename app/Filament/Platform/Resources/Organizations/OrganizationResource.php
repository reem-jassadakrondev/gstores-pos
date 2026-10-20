<?php

namespace App\Filament\Platform\Resources\Organizations;

use App\Enums\OrganizationStatus;
use App\Filament\Platform\Resources\Organizations\Pages\ManageOrganizations;
use App\Models\Organization;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Subscription metadata only: plan, status, trial. Platform staff don't browse a customer's
 * sales or customer data from here; that needs the customer's permission (PDPA).
 */
class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'ลูกค้า (Organization)';

    protected static ?string $pluralModelLabel = 'ลูกค้า (Organizations)';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('plan_id')->label('แพ็กเกจ')->options(fn () => Plan::orderBy('sort')->pluck('name', 'id'))->required()->native(false),
            Select::make('status')->label('สถานะ')->options(OrganizationStatus::options())->required()->native(false),
            DateTimePicker::make('trial_ends_at')->label('หมดทดลองใช้'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('ชื่อ')->searchable(),
                TextColumn::make('owner.email')->label('เจ้าของ')->searchable(),
                TextColumn::make('plan.name')->label('แพ็กเกจ')->badge(),
                TextColumn::make('status')->label('สถานะ')->badge()->formatStateUsing(fn (OrganizationStatus $state) => $state->label()),
                TextColumn::make('trial_ends_at')->label('หมดทดลอง')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('stores_count')->label('ร้าน')->counts('stores'),
                TextColumn::make('terminals_count')->label('เครื่อง')->counts('terminals'),
                TextColumn::make('created_at')->label('สมัครเมื่อ')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('สถานะ')->options(OrganizationStatus::options()),
                SelectFilter::make('plan_id')->label('แพ็กเกจ')->relationship('plan', 'name'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOrganizations::route('/')];
    }
}
