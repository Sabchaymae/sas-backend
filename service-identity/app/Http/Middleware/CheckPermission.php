<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $module
     * @param  string  $action
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $module, string $action): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié.'
            ], 401);
        }

        // Active check
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte n\'est pas actif.'
            ], 403);
        }

        // Admin bypass or check specific permission
        if ($user->isAdmin() || $user->hasModulePermission($module, $action)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => "Accès interdit. Autorisation insuffisante pour le module {$module} ({$action})."
        ], 403);
    }
}
