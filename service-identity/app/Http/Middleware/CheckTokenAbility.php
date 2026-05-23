<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAbility
{
    /**
     * Verify the token has the required ability.
     * Used to restrict temporary tokens (e.g. password-change only).
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (!$request->user() || !$request->user()->tokenCan($ability)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé pour cette action.',
            ], 403);
        }

        return $next($request);
    }
}
