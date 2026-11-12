<?php

declare(strict_types=1);

namespace CsrfWarden\Tests;

use CsrfWarden\Exception\SessionNotStarted;
use CsrfWarden\Storage\ArrayStorage;
use CsrfWarden\Storage\SessionStorage;
use PHPUnit\Framework\TestCase;

final class StorageTest extends TestCase
{
    public function testArrayStorageAddDoesNotOverwrite(): void
    {
        $storage = new ArrayStorage();

        self::assertTrue($storage->add('key', 'first'));
        self::assertFalse($storage->add('key', 'second'));
        self::assertSame('first', $storage->get('key'));
    }

    public function testArrayStorageRemoveMakesAddAvailableAgain(): void
    {
        $storage = new ArrayStorage();
        $storage->add('key', 'first');

        $storage->remove('key');

        self::assertNull($storage->get('key'));
        self::assertTrue($storage->add('key', 'second'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionStorageRequiresAnActiveSession(): void
    {
        $this->expectException(SessionNotStarted::class);

        (new SessionStorage())->get('key');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSessionStorageStoresValues(): void
    {
        session_id('csrf-warden-test-' . bin2hex(random_bytes(4)));
        session_start();

        $storage = new SessionStorage('test');
        self::assertTrue($storage->add('key', 'first'));
        self::assertFalse($storage->add('key', 'second'));
        self::assertSame('first', $storage->get('key'));

        $storage->remove('key');
        self::assertNull($storage->get('key'));

        session_write_close();
    }
}
