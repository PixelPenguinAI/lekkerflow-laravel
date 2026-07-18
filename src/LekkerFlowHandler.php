<?php

namespace PixelPenguin\LekkerFlow;

use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Ships log records to the LekkerFlow error webhook.
 *
 * Wired up as the `lekkerflow` log channel (see LekkerFlowServiceProvider).
 * Each record at or above the channel level is POSTed to LekkerFlow, where
 * identical errors are grouped into a single counted issue. The handler never
 * throws: a webhook failure must not mask or replace the original error.
 */
class LekkerFlowHandler extends AbstractProcessingHandler
{
    public function __construct(
        private string $url,
        private string $token,
        private string $environment,
        private ?string $release = null,
        private int $timeout = 5,
        int|string|Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if ($this->url === '' || $this->token === '') {
            return;
        }

        try {
            Http::withHeaders(['X-Webhook-Token' => $this->token])
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->url, $this->payload($record));
        } catch (Throwable) {
            // Reporting an error must never become a new error. Swallow any
            // transport failure (timeout, DNS, 5xx) so the original record
            // still bubbles to the remaining log channels.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LogRecord $record): array
    {
        $context = $record->context;
        $exception = $context['exception'] ?? null;
        $url = $context['url'] ?? null;
        $explicitTrace = $context['stack_trace'] ?? $context['trace'] ?? null;
        unset($context['exception'], $context['url'], $context['stack_trace'], $context['trace']);

        $payload = array_filter([
            'message' => $record->message,
            'level' => $this->mapLevel($record->level),
            'environment' => $this->environment,
            'release' => $this->release,
            'url' => $url,
        ], fn ($value) => $value !== null && $value !== '');

        if ($exception instanceof Throwable) {
            $payload += array_filter([
                'exception_class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'stack_trace' => $exception->getTraceAsString(),
            ], fn ($value) => $value !== null && $value !== '');
        } elseif (is_string($explicitTrace) && $explicitTrace !== '') {
            $payload['stack_trace'] = $explicitTrace;
        } else {
            $payload['stack_trace'] = $this->callerStackTrace();
        }

        if ($context !== []) {
            $payload['context'] = $context;
        }

        return $payload;
    }

    /**
     * Build a stack from the call site that invoked the logger, skipping
     * Monolog / Laravel / package frames so LekkerFlow shows application code.
     */
    private function callerStackTrace(): string
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $lines = [];
        $index = 0;

        foreach ($frames as $frame) {
            $class = $frame['class'] ?? '';
            $file = $frame['file'] ?? '';

            if ($this->isLoggingFrame($class, $file)) {
                continue;
            }

            $location = $file !== ''
                ? $file.'('.($frame['line'] ?? 0).')'
                : '[internal]';
            $call = $class.($frame['type'] ?? '').($frame['function'] ?? '{main}');
            $lines[] = '#'.$index.' '.$location.': '.$call.'()';
            $index++;
        }

        return implode("\n", $lines);
    }

    private function isLoggingFrame(string $class, string $file): bool
    {
        if ($class !== '' && (
            str_starts_with($class, 'Monolog\\')
            || str_starts_with($class, 'PixelPenguin\\LekkerFlow\\')
            || str_starts_with($class, 'Illuminate\\Log\\')
            || str_starts_with($class, 'Illuminate\\Support\\Facades\\Log')
            || str_starts_with($class, 'Psr\\Log\\')
        )) {
            return true;
        }

        return str_contains($file, DIRECTORY_SEPARATOR.'monolog'.DIRECTORY_SEPARATOR.'monolog'.DIRECTORY_SEPARATOR)
            || str_contains($file, DIRECTORY_SEPARATOR.'Illuminate'.DIRECTORY_SEPARATOR.'Log'.DIRECTORY_SEPARATOR)
            || str_contains($file, DIRECTORY_SEPARATOR.'laravel-lekkerflow'.DIRECTORY_SEPARATOR);
    }

    /**
     * Map a Monolog level onto a LekkerFlow severity.
     */
    private function mapLevel(Level $level): string
    {
        return match ($level) {
            Level::Debug => 'debug',
            Level::Info, Level::Notice => 'info',
            Level::Warning => 'warning',
            Level::Error => 'error',
            Level::Critical, Level::Alert, Level::Emergency => 'critical',
        };
    }
}
