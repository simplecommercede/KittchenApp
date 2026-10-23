<?php

declare(strict_types=1);

namespace Kitchen\Model;

final readonly class User
{
    /** @param list<string> $roles */
    public function __construct(public int $id, public string $name, public array $roles)
    {
    }
    public static function fromArray(array $data): self
    {
        return new self((int)($data['id'] ?? 0), (string)($data['name'] ?? ''), $data['roles']);
    }
    public function allows(string $role): bool
    {
        return in_array('admin', $this->roles, true) || in_array($role, $this->roles, true);
    }
}
