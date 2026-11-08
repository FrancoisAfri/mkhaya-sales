<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Never send somebody "back" to a file after they sign in or unlock.
 *
 * When a session has expired, any GET that reaches the auth check is remembered as the place to
 * return to, including requests the browser makes on its own: a service worker checking
 * `/sw.js` for an update, a source map, a favicon. Signing in or unlocking then redirected the
 * person to that file, which ended on a 404 ("localhost:8000/sw.js") instead of their screen. A
 * remembered address that is a file or an API call is dropped, so `intended()` falls back to the
 * person's landing page.
 */
class ForgetNonPageIntendedUrl
{
    private const FILE = '/\.(js|mjs|css|map|json|webmanifest|ico|png|jpe?g|gif|svg|webp|woff2?|ttf|txt|xml)$/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->hasSession() && ($intended = $request->session()->get('url.intended'))) {
            $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
            if (preg_match(self::FILE, $path) || str_starts_with($path, '/api/') || str_starts_with($path, '/build/')) {
                $request->session()->forget('url.intended');
            }
        }

        return $response;
    }
}
