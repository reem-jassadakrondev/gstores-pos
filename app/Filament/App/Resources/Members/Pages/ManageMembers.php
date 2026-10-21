<?php

namespace App\Filament\App\Resources\Members\Pages;

use App\Enums\OrgRole;
use App\Filament\App\Resources\Members\MemberResource;
use App\Filament\Support\TenantAccess;
use App\Models\OrganizationMember;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageMembers extends ManageRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        $remaining = TenantAccess::remaining('members');

        return [
            CreateAction::make()
                ->label('เพิ่มผู้ใช้'.($remaining !== null ? " (เหลือ {$remaining})" : ''))
                ->using(function (array $data, CreateAction $action) {
                    $fail = function (string $message) use ($action) {
                        Notification::make()->danger()->title($message)->send();
                        $action->halt();
                    };

                    if (TenantAccess::remaining('members') === 0) {
                        $fail('แพ็กเกจปัจจุบันเพิ่มผู้ใช้ได้เต็มแล้ว กรุณาอัปเกรดแพ็กเกจ');
                    }

                    $user = User::where('email', $data['user_email'])->first();
                    if ($user && OrganizationMember::where('user_id', $user->getKey())->exists()) {
                        $fail('ผู้ใช้นี้อยู่ในกิจการแล้ว');
                    }
                    if (! $user && blank($data['user_password'] ?? null)) {
                        $fail('ผู้ใช้ใหม่ต้องตั้งรหัสผ่านเริ่มต้น');
                    }

                    $user ??= User::create([
                        'name' => $data['user_name'],
                        'email' => $data['user_email'],
                        'password' => $data['user_password'],
                    ]);

                    return TenantAccess::createWithinPlan(fn () => OrganizationMember::create([
                        'user_id' => $user->getKey(),
                        // Store managers can only add plain members.
                        'org_role' => MemberResource::managesWholeOrganization()
                            ? OrgRole::from($data['org_role'] instanceof OrgRole ? $data['org_role']->value : $data['org_role'])
                            : OrgRole::Member,
                        'is_active' => $data['is_active'] ?? true,
                    ]), $action);
                }),
        ];
    }
}
