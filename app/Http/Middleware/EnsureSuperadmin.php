<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || $user->role !== 'superadmin'
            || $user->status !== 'active'
            || $user->school_id !== null
        ) {
            abort(403, 'Acceso exclusivo para el administrador global de SchoolPass.');
        }

        return $next($request);
    }
}
