<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PixelPenguin\LekkerFlow\LekkerFlowHandler;
use Psr\Log\LoggerInterface;

function lekkerflowChannel(array $overrides = []): LoggerInterface
{
    config()->set('logging.channels.lekkerflow.handler_with', array_merge([
        'url' => 'https://lekkerflow.test/capture',
        'token' => 'test-token',
        'environment' => 'staging',
        'release' => 'abc123',
        'timeout' => 5,
    ], $overrides));

    return Log::build(config('logging.channels.lekkerflow'));
}

it('auto-registers the lekkerflow log channel from package config', function (): void {
    expect(config('logging.channels.lekkerflow.driver'))->toBe('monolog')
        ->and(config('logging.channels.lekkerflow.handler'))->toBe(LekkerFlowHandler::class);
});

it('ships sensible config defaults', function (): void {
    expect(config('lekkerflow.url'))->toBe('https://lekkerflow.com/api/error-webhook/capture')
        ->and(config('lekkerflow.level'))->toBe('error')
        ->and(config('lekkerflow.timeout'))->toBe(5);
});

it('posts an error record to the webhook with the configured headers and payload', function (): void {
    Http::fake(['lekkerflow.test/*' => Http::response('', 202)]);

    lekkerflowChannel()->error('User 42 failed to verify', [
        'url' => 'https://app.test/onboard',
        'user_id' => 42,
    ]);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://lekkerflow.test/capture'
            && $request->method() === 'POST'
            && $request->header('X-Webhook-Token')[0] === 'test-token'
            && $request['message'] === 'User 42 failed to verify'
            && $request['level'] === 'error'
            && $request['environment'] === 'staging'
            && $request['release'] === 'abc123'
            && $request['url'] === 'https://app.test/onboard'
            && $request['context'] === ['user_id' => 42];
    });
});

it('includes exception details when an exception is logged', function (): void {
    Http::fake(['lekkerflow.test/*' => Http::response('', 202)]);

    lekkerflowChannel()->critical('Boom', ['exception' => new RuntimeException('Boom')]);

    Http::assertSent(function (Request $request): bool {
        return $request['level'] === 'critical'
            && $request['exception_class'] === RuntimeException::class
            && $request['line'] > 0
            && is_string($request['stack_trace'])
            && str_contains($request['stack_trace'], 'LekkerFlowChannelTest.php')
            && ! array_key_exists('context', $request->data());
    });
});

it('promotes a context trace string into stack_trace', function (): void {
    Http::fake(['lekkerflow.test/*' => Http::response('', 202)]);

    lekkerflowChannel()->error('Gateway down', [
        'error' => 'timeout',
        'trace' => "#0 app/Foo.php(10): boom()\n#1 app/Bar.php(20): foo()",
    ]);

    Http::assertSent(function (Request $request): bool {
        return $request['stack_trace'] === "#0 app/Foo.php(10): boom()\n#1 app/Bar.php(20): foo()"
            && $request['context'] === ['error' => 'timeout'];
    });
});

it('includes a call-site stack trace when no exception or trace is provided', function (): void {
    Http::fake(['lekkerflow.test/*' => Http::response('', 202)]);

    lekkerflowChannel()->error('Integration boom', ['order_id' => 7]);

    Http::assertSent(function (Request $request): bool {
        return is_string($request['stack_trace'])
            && $request['stack_trace'] !== ''
            && str_contains($request['stack_trace'], 'LekkerFlowChannelTest')
            && ! str_contains($request['stack_trace'], 'Monolog\\')
            && $request['context'] === ['order_id' => 7];
    });
});

it('does not send anything when no token is configured', function (): void {
    Http::fake();

    lekkerflowChannel(['token' => ''])->error('Should not be sent');

    Http::assertNothingSent();
});

it('swallows webhook transport failures so the original error is not masked', function (): void {
    Http::fake(fn () => throw new ConnectionException('down'));

    lekkerflowChannel()->error('Still logs locally');
})->throwsNoExceptions();
