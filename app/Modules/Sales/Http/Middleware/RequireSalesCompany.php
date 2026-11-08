<?php

namespace App\Modules\Sales\Http\Middleware;

use App\Modules\Sales\Support\SalesCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A platform administrator with no company chosen is sent to pick one instead of hitting an error. */
class RequireSalesCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if (SalesCompany::id() === null) {
            $message = 'Sales Intelligence works inside one company. Open Platform → Companies and choose "View as company" first.';
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                abort(409, $message);
            }

            return redirect('/platform/companies')->with('error', $message);
        }

        return $next($request);
    }
}
