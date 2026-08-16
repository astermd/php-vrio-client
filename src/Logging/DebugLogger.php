<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Logging;

use AsterMD\VrioClient\Config;
use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Http\Request;
use AsterMD\VrioClient\Http\Response;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Opt-in request/response logging, redacted by default.
 *
 * Each entry is a copy-pasteable cURL command plus the response it produced.
 * The logger reads from the Request and Response value objects and writes a
 * formatted string to a sink; it never mutates either object, so enabling
 * logging cannot change what is sent or what the caller receives.
 *
 * Logging is off unless `debug` is true. When on, headers and bodies are
 * redacted unless `debugRedact` is explicitly set to false.
 *
 * @package AsterMD\VrioClient
 */
final class DebugLogger
{
    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var bool
     */
    private $redact;

    /**
     * @var callable|null
     */
    private $sink;

    /**
     * @var DateTimeZone
     */
    private $timezone;

    /**
     * @var Redactor
     */
    private $redactor;

    /**
     * @param bool          $enabled
     * @param bool          $redact
     * @param callable|null $sink     Receives the formatted entry: function (string $entry): void
     * @param string        $timezone IANA timezone for entry timestamps.
     *
     * @throws VrioException When the timezone is not recognised.
     */
    public function __construct(bool $enabled, bool $redact = true, ?callable $sink = null, string $timezone = 'UTC')
    {
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Throwable $e) {
            throw new VrioException(Config::get('Messages.invalidTimezone'));
        }

        $this->enabled  = $enabled;
        $this->redact   = $redact;
        $this->sink     = $sink;
        $this->redactor = new Redactor();
    }

    /**
     * Build a logger from the client option array.
     *
     * Recognised options:
     *  - debug              (bool)          Master switch. Default false.
     *  - debugRedact        (bool)          Default true. Setting false logs verbatim.
     *  - debugSink          (callable|null) Replaces the file sink entirely.
     *  - debugFile          (string|null)   Base path for the built-in dated file sink.
     *  - debugRetentionDays (int)           Days of history to keep. 0 keeps everything.
     *  - debugTimezone      (string)        IANA timezone. Default "UTC".
     *
     * @param array<string, mixed> $options
     *
     * @return self
     *
     * @throws VrioException When debug is enabled but no destination is configured.
     */
    public static function fromOptions(array $options): self
    {
        $enabled  = !empty($options['debug']);
        $redact   = !isset($options['debugRedact']) || (bool) $options['debugRedact'];
        $timezone = self::stringOption($options, 'debugTimezone', 'UTC');

        if (!$enabled) {
            return new self(false, $redact, null, $timezone);
        }

        if (isset($options['debugSink'])) {
            if (!is_callable($options['debugSink'])) {
                throw new VrioException(Config::get('Messages.invalidDebugSink'));
            }

            return new self(true, $redact, $options['debugSink'], $timezone);
        }

        $file = self::stringOption($options, 'debugFile', '');
        if (trim($file) === '') {
            throw new VrioException(Config::get('Messages.debugFileRequired'));
        }

        $retention = (isset($options['debugRetentionDays']) && is_numeric($options['debugRetentionDays']))
            ? (int) $options['debugRetentionDays']
            : FileSink::DEFAULT_RETENTION_DAYS;

        return new self(true, $redact, new FileSink($file, $retention, $timezone), $timezone);
    }

    /**
     * Read a scalar option as a string, falling back when it is absent or not
     * scalar.
     *
     * @param array<string, mixed> $options
     * @param string               $key
     * @param string               $default
     *
     * @return string
     */
    private static function stringOption(array $options, string $key, string $default): string
    {
        if (!isset($options[$key]) || !is_scalar($options[$key])) {
            return $default;
        }

        return (string) $options[$key];
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return bool
     */
    public function isRedacting(): bool
    {
        return $this->redact;
    }

    /**
     * Record one exchange.
     *
     * Never throws: a logging failure must not break the API call.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return void
     */
    public function log(Request $request, Response $response): void
    {
        if (!$this->enabled || $this->sink === null) {
            return;
        }

        try {
            $sink = $this->sink;
            $sink($this->format($request, $response));
        } catch (Throwable $e) {
            // Intentionally swallowed.
        }
    }

    /**
     * Render one entry without writing it anywhere.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return string
     */
    public function format(Request $request, Response $response): string
    {
        $headers = $this->redact
            ? $this->redactor->redactHeaders($request->getHeaders())
            : $request->getHeaders();

        $body = $this->redact
            ? $this->redactor->redactBody($request->getBody())
            : $request->getBody();

        $responseBody = $this->redact
            ? $this->redactor->redactBody($response->getBody())
            : $response->getBody();

        // The URL is logged verbatim by design; see the README for which calls
        // therefore record identifiers in the path or query string.
        $lines     = ["curl --location --request {$request->getMethod()} '{$request->getUrl()}' \\"];
        $lastIndex = count($headers) - 1;
        foreach ($headers as $index => $headerLine) {
            $continues = ($body !== null) || ($index !== $lastIndex);
            $lines[]   = "  --header '{$headerLine}'" . ($continues ? ' \\' : '');
        }
        if ($body !== null) {
            $lines[] = "  --data '" . str_replace("'", "'\\''", $body) . "'";
        }

        $entry = '[' . $this->timestamp() . "]\n" . implode("\n", $lines) . "\n\n";

        if ($response->hasTransportError()) {
            return $entry . '# Transport error: ' . $response->getTransportError() . "\n\n";
        }

        return $entry . '# Response: HTTP ' . $response->getStatusCode() . "\n" . (string) $responseBody . "\n\n";
    }

    /**
     * @return string
     */
    private function timestamp(): string
    {
        $moment = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', microtime(true)));
        if ($moment === false) {
            $moment = new DateTimeImmutable('now');
        }

        return $moment->setTimezone($this->timezone)->format('Y-m-d H:i:s.u T');
    }
}
