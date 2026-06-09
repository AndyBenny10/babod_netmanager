<?php

declare(strict_types=1);

namespace Babod\NetManager\Http;

final class Auth
{
    public function __construct(private readonly string $adminPassword)
    {
    }

    public function check(): bool
    {
        return !empty($_SESSION['authenticated']);
    }

    public function attempt(string $password): bool
    {
        if (!hash_equals($this->adminPassword, $password)) {
            return false;
        }

        $_SESSION['authenticated'] = true;
        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['authenticated']);
    }

    public function requireLogin(): void
    {
        if (!$this->check()) {
            View::redirect('/login');
        }
    }
}
