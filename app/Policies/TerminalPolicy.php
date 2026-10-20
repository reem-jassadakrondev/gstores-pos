<?php

namespace App\Policies;

use App\Models\Terminal;
use App\Models\User;
use App\Policies\Concerns\ChecksAccess;
use App\Support\Access\Permission;

class TerminalPolicy
{
    use ChecksAccess;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, Permission::STORE_TERMINALS);
    }

    public function view(User $user, Terminal $terminal): bool
    {
        return $this->allowsOn($user, Permission::STORE_TERMINALS, $terminal, $terminal->branch);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, Permission::STORE_TERMINALS);
    }

    public function update(User $user, Terminal $terminal): bool
    {
        return $this->allowsOn($user, Permission::STORE_TERMINALS, $terminal, $terminal->branch);
    }

    public function delete(User $user, Terminal $terminal): bool
    {
        return $this->allowsOn($user, Permission::STORE_TERMINALS, $terminal, $terminal->branch);
    }
}
