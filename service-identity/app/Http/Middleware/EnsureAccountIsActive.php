<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Reject requests from users whose accounts are not active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte n\'est pas actif.',
            ], 403);
        }

        return $next($request);
    }
}
