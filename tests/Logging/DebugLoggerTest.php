<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests\Logging;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Http\Request;
use AsterMD\VrioClient\Http\Response;
use AsterMD\VrioClient\Logging\DebugLogger;
use AsterMD\VrioClient\Logging\FileSink;
use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Logging\DebugLogger::class)]
final class DebugLoggerTest extends ClientTestCase
{
    /**
     * @var string[]
     */
    private $entries = [];

    /**
     * @var string
     */
    private $directory;

    protected function setUp(): void
    {
        parent::setUp();

        FileSink::resetPruneState();
        $this->entries   = [];
        $this->directory = sys_get_temp_dir() . '/vrio-client-logs-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        $entries = glob($this->directory . '/*');
        foreach (($entries === false ? [] : $entries) as $entry) {
            unlink($entry);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        FileSink::resetPruneState();
        parent::tearDown();
    }

    public function testLoggingIsOffByDefault(): void
    {
        $this->client()->addOrder(['email' => 'buyer@example.test']);

        self::assertSame([], $this->entries);
    }

    public function testADisabledLoggerNeverCallsItsSink(): void
    {
        $logger = new DebugLogger(false, true, $this->sink());
        $logger->log(new Request('GET', 'https://api.vrio.app/orders'), new Response(200, '{}'));

        self::assertFalse($logger->isEnabled());
        self::assertSame([], $this->entries);
    }

    public function testEnablingDebugWithoutADestinationIsRejected(): void
    {
        $this->expectException(VrioException::class);
        $this->expectExceptionMessage('debugFile');

        $this->client(['debug' => true]);
    }

    public function testANonCallableSinkIsRejected(): void
    {
        $this->expectException(VrioException::class);
        $this->expectExceptionMessage('callable');

        $this->client(['debug' => true, 'debugSink' => 'not a callable at all']);
    }

    public function testAnUnknownTimezoneIsRejected(): void
    {
        $this->expectException(VrioException::class);

        new DebugLogger(true, true, $this->sink(), 'Nowhere/Nothing');
    }

    public function testASinkClosureReplacesTheFileSinkEntirely(): void
    {
        $this->client([
            'debug'     => true,
            'debugFile' => $this->directory . '/should-not-be-written.log',
            'debugSink' => $this->sink(),
        ])->searchOrder();

        self::assertCount(1, $this->entries);
        self::assertSame([], (array) glob($this->directory . '/*'));
    }

    public function testTheBuiltInFileSinkWritesADatedFile(): void
    {
        $this->client([
            'debug'     => true,
            'debugFile' => $this->directory . '/vrio.log',
        ])->searchOrder();

        $files = (array) glob($this->directory . '/vrio-*.log');
        self::assertCount(1, $files);

        $contents = (string) file_get_contents((string) $files[0]);
        self::assertStringContainsString('curl --location --request GET', $contents);
    }

    public function testEntriesAreRedactedByDefault(): void
    {
        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->addOrder(['email' => 'buyer@example.test', 'apiKey' => 'inner-secret-placeholder']);

        $entry = $this->entries[0];

        self::assertStringContainsString('X-Api-Key: [REDACTED]', $entry);
        self::assertStringNotContainsString(self::API_KEY, $entry);
        self::assertStringContainsString('"apiKey":"[REDACTED]"', $entry);
        self::assertStringNotContainsString('inner-secret-placeholder', $entry);
    }

    public function testTheUrlIsLoggedVerbatim(): void
    {
        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->getPaypalData(['cart_token' => 'crt_1', 'payment_token_id' => 'pt_2']);

        self::assertStringContainsString(
            "'https://api.vrio.app/carts/crt_1/payment_tokens/pt_2'",
            $this->entries[0]
        );
    }

    public function testResponseTokensAreRedacted(): void
    {
        $this->http->queue(new Response(200, '{"access_token":"at-placeholder","id":"crt_1"}', ['http_code' => 200]));

        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->createCart(['campaign_id' => 'camp_1']);

        self::assertStringContainsString('"access_token":"[REDACTED]"', $this->entries[0]);
        self::assertStringNotContainsString('at-placeholder', $this->entries[0]);
    }

    public function testOptingOutOfRedactionLogsVerbatim(): void
    {
        $this->client([
            'debug'       => true,
            'debugRedact' => false,
            'debugSink'   => $this->sink(),
        ])->addOrder(['email' => 'buyer@example.test']);

        self::assertStringContainsString('X-Api-Key: ' . self::API_KEY, $this->entries[0]);
        self::assertStringContainsString('"email":"buyer@example.test"', $this->entries[0]);
    }

    public function testRedactionDoesNotAlterTheRealRequestOrResponse(): void
    {
        $providerBody = '{"access_token":"at-placeholder","id":"ord_1"}';
        $this->http->queue(new Response(200, $providerBody, ['http_code' => 200]));

        $client = $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ]);
        $client->addOrder(['email' => 'buyer@example.test', 'apiKey' => 'inner-secret-placeholder']);

        // What actually went over the wire is untouched.
        $sent = $this->http->lastRequest();
        self::assertSame(
            '{"email":"buyer@example.test","apiKey":"inner-secret-placeholder"}',
            $sent->getBody()
        );
        self::assertContains('X-Api-Key: ' . self::API_KEY, $sent->getHeaders());

        // What the caller receives is untouched.
        /** @var array<string, mixed> $envelope */
        $envelope = $client->getInArray()['response'];
        /** @var array<string, mixed> $data */
        $data = $envelope['data'];

        self::assertSame('at-placeholder', $data['access_token']);
        self::assertSame('ord_1', $data['id']);

        // Only the log entry is masked.
        self::assertStringContainsString('[REDACTED]', $this->entries[0]);
        self::assertStringNotContainsString('at-placeholder', $this->entries[0]);
    }

    public function testATransportErrorIsRecorded(): void
    {
        $this->http->queue(new Response(0, '', [], 'Could not resolve host'));

        $this->client([
            'debug'     => true,
            'debugSink' => $this->sink(),
        ])->searchOrder();

        self::assertStringContainsString('# Transport error: Could not resolve host', $this->entries[0]);
    }

    public function testAFailingSinkNeverBreaksTheApiCall(): void
    {
        $client = $this->client([
            'debug'     => true,
            'debugSink' => static function (string $entry): void {
                throw new RuntimeException('sink exploded: ' . $entry);
            },
        ]);

        $client->searchOrder();

        self::assertSame(1, $this->http->count());
        self::assertArrayHasKey('response', $client->getInArray());
    }

    public function testTheFormattedEntryCarriesTheMethodHeadersAndBody(): void
    {
        $logger = new DebugLogger(true, true, $this->sink());

        $entry = $logger->format(
            new Request('POST', 'https://api.vrio.app/orders', ['X-Api-Key: live-placeholder'], '{"a":1}'),
            new Response(201, '{"id":1}', ['http_code' => 201])
        );

        self::assertStringContainsString("curl --location --request POST 'https://api.vrio.app/orders'", $entry);
        self::assertStringContainsString("--header 'X-Api-Key: [REDACTED]'", $entry);
        self::assertStringContainsString('--data \'{"a":1}\'', $entry);
        self::assertStringContainsString('# Response: HTTP 201', $entry);
    }

    public function testIsRedactingReportsTheConfiguredMode(): void
    {
        self::assertTrue((new DebugLogger(true, true, $this->sink()))->isRedacting());
        self::assertFalse((new DebugLogger(true, false, $this->sink()))->isRedacting());
    }

    public function testFromOptionsReturnsADisabledLoggerWhenDebugIsOff(): void
    {
        $logger = DebugLogger::fromOptions([]);

        self::assertFalse($logger->isEnabled());
        self::assertTrue($logger->isRedacting());
    }

    /**
     * @return callable
     */
    private function sink(): callable
    {
        return function (string $entry): void {
            $this->entries[] = $entry;
        };
    }
}
