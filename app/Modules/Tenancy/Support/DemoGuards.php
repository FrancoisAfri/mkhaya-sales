<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Services\DemoMode;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

/**
 * Where DemoMode meets the framework. Mail and notifications stop here whichever module sent them;
 * in-app notifications still go through. The HTTP client is the catch-all for everything else this
 * system talks to — social networks, ad platforms, WhatsApp, payment and payout providers, webhooks —
 * so a module added later is covered without knowing demos exist.
 */
final class DemoGuards
{
    public static function register(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $to = array_merge($event->message->getTo(), $event->message->getCc(), $event->message->getBcc());

            return app(DemoMode::class)->blocksEmail(array_map(fn ($a) => $a->getAddress(), $to)) ? false : null;
        });

        Event::listen(NotificationSending::class, function (NotificationSending $event) {
            return ! in_array($event->channel, ['database', 'broadcast'], true) && app(DemoMode::class)->active() ? false : null;
        });

        Http::globalMiddleware(fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler) {
            if (app(DemoMode::class)->blocksRequest($request->getMethod(), $request->getUri()->getHost())) {
                return Create::promiseFor(new Response(503, ['Content-Type' => 'application/json'], '{"error":"This is the demo system: nothing is sent to outside services."}'));
            }

            return $handler($request, $options);
        });
    }
}
