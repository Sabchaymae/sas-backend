<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
<<<<<<< HEAD
        if ($request->user() && $request->user()->isAdmin()) {
=======
        if ($request->user() && $request->user()->role === 'admin') {
>>>>>>> import/master
            return $next($request);
        }

        return response()->json(['message' => 'Accès interdit. Administrateur requis.'], 403);
    }
}
