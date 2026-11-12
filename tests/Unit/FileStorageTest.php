<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

final class FileStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/csrf-warden-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    public function testValuesCanBeStoredReadAndRemoved(): void
    {
        $storage = new FileStorage($this->directory);

        $storage->set('key', 'value');
        self::assertSame('value', $storage->get('key'));

        $storage->remove('key');
        self::assertNull($storage->get('key'));
    }

    public function testAddDoesNotOverwriteAnExistingKey(): void
    {
        $storage = new FileStorage($this->directory);

        self::assertTrue($storage->add('key', 'first'));
        self::assertFalse($storage->add('key', 'second'));
        self::assertSame('first', $storage->get('key'));
    }

    public function testMissingKeysReturnNull(): void
    {
        $storage = new FileStorage($this->directory);

        self::assertNull($storage->get('missing'));
    }
}
