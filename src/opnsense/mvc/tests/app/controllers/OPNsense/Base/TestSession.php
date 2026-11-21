<?php

namespace tests\OPNsense\Base;

use OPNsense\Mvc\Session;

/**
 * Session kept in memory, so a controller can run without a PHP session
 */
class TestSession extends Session
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function get(string $name, ?string $default_value = null): string|array|null
    {
        return $this->data[$name] ?? $default_value;
    }

    public function set($name, $value): void
    {
        $this->data[$name] = $value;
    }

    public function remove(string $name): void
    {
        unset($this->data[$name]);
    }

    public function close(): void
    {
    }
}
