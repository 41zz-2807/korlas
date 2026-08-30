<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('admin_authenticated')) {
            abort(403, 'Akses hanya untuk admin.');
        }

        return $next($request);
    }
}
