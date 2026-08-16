<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests\Logging;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Logging\FileSink;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Logging\FileSink::class)]
final class FileSinkTest extends TestCase
{
    /**
     * @var string
     */
    private $directory;

    protected function setUp(): void
    {
        parent::setUp();

        FileSink::resetPruneState();

        $this->directory = sys_get_temp_dir() . '/vrio-client-tests-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->directory);

        FileSink::resetPruneState();
        parent::tearDown();
    }

    /**
     * @param string $path
     *
     * @return void
     */
    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = glob($path . '/*');
        foreach (($entries === false ? [] : $entries) as $entry) {
            if (is_dir($entry)) {
                $this->removeTree($entry);
                continue;
            }
            unlink($entry);
        }

        rmdir($path);
    }

    public function testTheDateIsInsertedBeforeTheExtension(): void
    {
        $sink = new FileSink($this->directory . '/vrio.log');
        $date = new DateTimeImmutable('2026-08-16', new DateTimeZone('UTC'));

        self::assertSame($this->directory . '/vrio-2026-08-16.log', $sink->pathForDate($date));
    }

    public function testABasePathWithoutAnExtensionStillGetsADate(): void
    {
        $sink = new FileSink($this->directory . '/vrio');
        $date = new DateTimeImmutable('2026-08-16', new DateTimeZone('UTC'));

        self::assertSame($this->directory . '/vrio-2026-08-16', $sink->pathForDate($date));
    }

    public function testEntriesAreAppendedToTodaysFile(): void
    {
        $sink = new FileSink($this->directory . '/vrio.log');
        $sink('first entry' . PHP_EOL);
        $sink('second entry' . PHP_EOL);

        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $path  = $this->directory . '/vrio-' . $today . '.log';

        self::assertFileExists($path);
        self::assertSame('first entry' . PHP_EOL . 'second entry' . PHP_EOL, file_get_contents($path));
        self::assertCount(1, (array) glob($this->directory . '/*.log'));
    }

    public function testAMissingDirectoryIsCreated(): void
    {
        $nested = $this->directory . '/nested';
        $sink   = new FileSink($nested . '/vrio.log');
        $sink('entry');

        self::assertDirectoryExists($nested);
        self::assertCount(1, (array) glob($nested . '/*.log'));
    }

    public function testPruningRemovesOnlyExpiredDatedFiles(): void
    {
        $today  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
        $recent = (new DateTimeImmutable('-2 days', new DateTimeZone('UTC')))->format('Y-m-d');

        $this->write('vrio-2020-01-01.log');
        $this->write('vrio-' . $recent . '.log');
        $this->write('vrio-' . $today . '.log');
        $this->write('vrio-not-a-date.log');
        $this->write('unrelated.log');
        $this->write('other-2020-01-01.log');

        $sink = new FileSink($this->directory . '/vrio.log', 7);

        self::assertSame(1, $sink->prune());
        self::assertFileDoesNotExist($this->directory . '/vrio-2020-01-01.log');
        self::assertFileExists($this->directory . '/vrio-' . $recent . '.log');
        self::assertFileExists($this->directory . '/vrio-' . $today . '.log');
        self::assertFileExists($this->directory . '/vrio-not-a-date.log');
        self::assertFileExists($this->directory . '/unrelated.log');
        self::assertFileExists($this->directory . '/other-2020-01-01.log');
    }

    public function testARetentionOfZeroKeepsEverything(): void
    {
        $this->write('vrio-2020-01-01.log');

        $sink = new FileSink($this->directory . '/vrio.log', 0);

        self::assertSame(0, $sink->prune());
        self::assertFileExists($this->directory . '/vrio-2020-01-01.log');
    }

    public function testTheAgeComesFromTheFilenameNotTheModificationTime(): void
    {
        $path = $this->write('vrio-2020-01-01.log');
        touch($path, time());

        $sink = new FileSink($this->directory . '/vrio.log', 7);

        self::assertSame(1, $sink->prune());
        self::assertFileDoesNotExist($path);
    }

    public function testPruningRunsOncePerProcess(): void
    {
        $sink = new FileSink($this->directory . '/vrio.log', 7);

        $this->write('vrio-2020-01-01.log');
        $sink('first');
        self::assertFileDoesNotExist($this->directory . '/vrio-2020-01-01.log');

        $this->write('vrio-2020-02-02.log');
        $sink('second');
        self::assertFileExists($this->directory . '/vrio-2020-02-02.log');
    }

    public function testAnEmptyBasePathIsRejected(): void
    {
        $this->expectException(VrioException::class);

        new FileSink('   ');
    }

    public function testAnUnknownTimezoneIsRejected(): void
    {
        $this->expectException(VrioException::class);
        $this->expectExceptionMessage('IANA');

        new FileSink($this->directory . '/vrio.log', 7, 'Nowhere/Nothing');
    }

    public function testANegativeRetentionIsTreatedAsKeepEverything(): void
    {
        $this->write('vrio-2020-01-01.log');

        $sink = new FileSink($this->directory . '/vrio.log', -5);

        self::assertSame(0, $sink->prune());
        self::assertFileExists($this->directory . '/vrio-2020-01-01.log');
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function write(string $name): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, 'x');

        return $path;
    }
}
