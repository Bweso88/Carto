<?php

namespace App\Services\Kerberos;

final class ImportResult
{
    /** @var array<string, int> */
    public array $counts = [
        'servers' => 0,
        'hosts_created' => 0,
        'ip_addresses' => 0,
        'applications' => 0,
        'shared_services' => 0,
        'deployments' => 0,
        'flows' => 0,
        'users' => 0,
    ];

    /** @var list<string> */
    public array $warnings = [];

    public function add(string $key, int $n = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $n;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
