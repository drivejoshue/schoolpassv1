<?php

namespace App\Console\Commands;

use App\Models\SupportImpersonation;
use App\Models\User;
use App\Services\Auditing\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExpireSupportImpersonations extends Command
{
    protected $signature = '
        schoolpass:support-expire
        {--dry-run : Solo mostrar sesiones}
    ';

    protected $description = (
        'Cierra sesiones de soporte que superaron su vigencia.'
    );

    public function handle(
        AuditLogger $auditLogger,
    ): int {
        $dryRun = (bool) $this->option(
            'dry-run'
        );

        $reviewed = 0;
        $expired = 0;
        $errors = 0;

        SupportImpersonation::query()
            ->whereNull('ended_at')
            ->whereNotNull('expires_at')
            ->where(
                'expires_at',
                '<=',
                now()
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function ($rows) use (
                    $auditLogger,
                    $dryRun,
                    &$reviewed,
                    &$expired,
                    &$errors,
                ): void {
                    foreach ($rows as $row) {
                        $reviewed++;

                        $this->line(
                            "Soporte #{$row->id} expirado."
                        );

                        if ($dryRun) {
                            $expired++;

                            continue;
                        }

                        try {
                            DB::transaction(
                                function () use (
                                    $row,
                                    $auditLogger,
                                ): void {
                                    $locked =
                                        SupportImpersonation::query()
                                            ->whereKey($row->id)
                                            ->lockForUpdate()
                                            ->first();

                                    if (
                                        $locked === null
                                        || $locked->ended_at !== null
                                    ) {
                                        return;
                                    }

                                    $locked->update([
                                        'ended_at' => now(),
                                        'ended_reason' =>
                                            'expired_command',
                                    ]);

                                    $auditLogger->record(
                                        action:
                                            'support_impersonation_expired',

                                        schoolId:
                                            $locked->school_id,

                                        actorId:
                                            $locked->sysadmin_user_id,

                                        actorType:
                                            'system',

                                        entityType:
                                            User::class,

                                        entityId:
                                            $locked->target_user_id,

                                        oldValues: [
                                            'started_at' =>
                                                $locked
                                                    ->started_at
                                                    ?->toIso8601String(),

                                            'expires_at' =>
                                                $locked
                                                    ->expires_at
                                                    ?->toIso8601String(),
                                        ],

                                        newValues: [
                                            'impersonation_id' =>
                                                $locked->id,

                                            'ended_at' =>
                                                now()
                                                    ->toIso8601String(),

                                            'ended_reason' =>
                                                'expired_command',
                                        ],
                                    );
                                }
                            );

                            $expired++;
                        } catch (Throwable $exception) {
                            $errors++;

                            report($exception);

                            $this->error(
                                "Error en soporte #{$row->id}: "
                                .$exception->getMessage()
                            );
                        }
                    }
                }
            );

        $this->info("Revisadas: {$reviewed}");
        $this->info("Cerradas: {$expired}");

        if ($dryRun) {
            $this->warn(
                'Modo dry-run: no se modificaron registros.'
            );
        }

        if ($errors > 0) {
            $this->error("Errores: {$errors}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}