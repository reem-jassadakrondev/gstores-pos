<?php

namespace App\Support\Tenancy;

use App\Models\Organization;
use App\Models\Terminal;

/**
 * The organization the current request acts for. Set by the Filament tenant middleware
 * (back-office) and by EnsureTerminal (POS API). Scoped per request in the container.
 */
class CurrentTenant
{
    private ?Organization $organization = null;

    private ?Terminal $terminal = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function setTerminal(Terminal $terminal): void
    {
        $this->terminal = $terminal;
        $this->organization = $terminal->organization;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->getKey();
    }

    public function terminal(): ?Terminal
    {
        return $this->terminal;
    }

    public function clear(): void
    {
        $this->organization = null;
        $this->terminal = null;
    }
}
