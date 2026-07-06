<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

// RS-06: respuestas de error genéricas en producción (nunca stack traces / SQL).
class ForceJsonErrors
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
