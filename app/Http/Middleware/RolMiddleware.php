<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RolMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $usuario = $request->user();

        if (!$usuario || !$usuario->tieneRol(...$roles)) {
            abort(403, 'No tienes permiso para esta acción.');
        }

        return $next($request);
    }
}