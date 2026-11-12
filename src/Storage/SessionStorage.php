<?php

declare(strict_types=1);

namespace CsrfWarden\Storage;

use CsrfWarden\Exception\SessionNotStarted;

final class SessionStorage implements AtomicStorage
{
    public function __construct(private string $namespace = '_csrf_warden')
    {
    }

    public function get(string $key): ?string
    {
        $this->assertSessionIsActive();
        $bucket = $_SESSION[$this->namespace] ?? null;
        if (!is_array($bucket)) {
            return null;
        }

        $value = $bucket[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value): void
    {
        $this->assertSessionIsActive();
        $this->ensureBucket();
        $_SESSION[$this->namespace][$key] = $value;
    }

    public function add(string $key, string $value): bool
    {
        $this->assertSessionIsActive();
        $this->ensureBucket();
        if (isset($_SESSION[$this->namespace][$key])) {
            return false;
        }

        $_SESSION[$this->namespace][$key] = $value;

        return true;
    }

    public function remove(string $key): void
    {
        $this->assertSessionIsActive();
        $this->ensureBucket();
        unset($_SESSION[$this->namespace][$key]);
    }

    private function assertSessionIsActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw SessionNotStarted::create();
        }
    }

    private function ensureBucket(): void
    {
        if (!isset($_SESSION[$this->namespace]) || !is_array($_SESSION[$this->namespace])) {
            $_SESSION[$this->namespace] = [];
        }
    }
}
