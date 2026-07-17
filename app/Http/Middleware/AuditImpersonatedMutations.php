<?php

namespace App\Http\Middleware;

use App\Services\Auditing\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditImpersonatedMutations
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $context = $request->session()->get(
            'support_impersonation'
        );

        $routeName = $request->route()?->getName();

        $response = $next($request);

        if (
            ! is_array($context)
            || in_array(
                $request->method(),
                ['GET', 'HEAD', 'OPTIONS'],
                true
            )
            || $routeName === 'support.impersonation.stop'
        ) {
            return $response;
        }

        $this->auditLogger->record(
            action: 'support_impersonated_mutation',
            schoolId: isset($context['school_id'])
                ? (int) $context['school_id']
                : null,
            actorId: isset($context['sysadmin_user_id'])
                ? (int) $context['sysadmin_user_id']
                : null,
            actorType: 'support_impersonation',
            entityType: 'route',
            entityId: null,
            oldValues: [],
            newValues: [
                'impersonation_id' => $context['id'] ?? null,
                'target_user_id' => $context['target_user_id'] ?? null,
                'route' => $routeName,
                'method' => $request->method(),
                'path' => $request->path(),
                'response_status' => $response->getStatusCode(),
            ],
            request: $request,
        );

        return $response;
    }
}
