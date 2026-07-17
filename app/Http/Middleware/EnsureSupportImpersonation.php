<?php

namespace App\Http\Middleware;

use App\Models\SupportImpersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupportImpersonation
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $context = $request->session()->get(
            'support_impersonation'
        );

        if (
            ! is_array($context)
            || empty($context['id'])
            || empty($context['target_user_id'])
            || (int) $context['target_user_id']
                !== (int) $request->user()?->id
        ) {
            abort(
                403,
                'No existe una sesión de soporte activa.'
            );
        }

        $active = SupportImpersonation::query()
            ->whereKey($context['id'])
            ->where(
                'target_user_id',
                $request->user()->id
            )
            ->whereNull('ended_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            })
            ->exists();

        if (! $active) {
            $request->session()->forget(
                'support_impersonation'
            );

            abort(
                403,
                'La sesión de soporte ya terminó o expiró.'
            );
        }

        return $next($request);
    }
}