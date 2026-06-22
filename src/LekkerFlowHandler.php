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
        unset($context['exception'], $context['url']);

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
        }

        if ($context !== []) {
            $payload['context'] = $context;
        }

        return $payload;
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
