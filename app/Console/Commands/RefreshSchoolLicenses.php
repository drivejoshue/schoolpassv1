<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshSchoolLicenses extends Command
{
    protected $signature = 'schoolpass:licenses-refresh
                            {--dry-run : Solo mostrar cambios}';

    protected $description = (
        'Actualiza pruebas, periodos de gracia y licencias vencidas.'
    );

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $graceDays = max(
            0,
            (int) config(
                'schoolpass.license.grace_days',
                7
            )
        );

        $dryRun = (bool) $this->option('dry-run');

        $reviewed = 0;
        $changed = 0;
        $errors = 0;

        DB::table('school_licenses')
            ->where('is_current', true)
            ->whereIn(
                'status',
                [
                    'trial',
                    'active',
                    'grace',
                ]
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function ($licenses) use (
                    $today,
                    $graceDays,
                    $dryRun,
                    &$reviewed,
                    &$changed,
                    &$errors,
                ): void {
                    foreach ($licenses as $license) {
                        $reviewed++;

                        try {
                            $result = $this->resolveTransition(
                                license: $license,
                                today: $today,
                                graceDays: $graceDays
                            );

                            if ($result === null) {
                                continue;
                            }

                            $changed++;

                            $this->line(
                                sprintf(
                                    'Licencia #%d: %s → %s',
                                    $license->id,
                                    $license->status,
                                    $result['status']
                                )
                            );

                            if ($dryRun) {
                                continue;
                            }

                            DB::transaction(
                                function () use (
                                    $license,
                                    $result
                                ): void {
                                    $locked = DB::table(
                                        'school_licenses'
                                    )
                                        ->where(
                                            'id',
                                            $license->id
                                        )
                                        ->lockForUpdate()
                                        ->first();

                                    if (
                                        $locked === null
                                        || ! $locked->is_current
                                    ) {
                                        return;
                                    }

                                    DB::table(
                                        'school_licenses'
                                    )
                                        ->where(
                                            'id',
                                            $locked->id
                                        )
                                        ->update([
                                            'status' =>
                                                $result['status'],

                                            'grace_ends_at' =>
                                                $result[
                                                    'grace_ends_at'
                                                ],

                                            'updated_at' => now(),
                                        ]);

                                    DB::table(
                                        'school_license_events'
                                    )->insert([
                                        'school_id' =>
                                            $locked->school_id,

                                        'school_license_id' =>
                                            $locked->id,

                                        'event_type' =>
                                            $result['event_type'],

                                        'previous_status' =>
                                            $locked->status,

                                        'new_status' =>
                                            $result['status'],

                                        'metadata_json' =>
                                            json_encode([
                                                'source' =>
                                                    'scheduled_command',

                                                'grace_ends_at' =>
                                                    $result[
                                                        'grace_ends_at'
                                                    ],
                                            ], JSON_UNESCAPED_UNICODE),

                                        'performed_by' => null,
                                        'created_at' => now(),
                                    ]);
                                }
                            );
                        } catch (Throwable $exception) {
                            $errors++;

                            report($exception);

                            $this->error(
                                "Error en licencia #{$license->id}: "
                                .$exception->getMessage()
                            );
                        }
                    }
                }
            );

        $this->newLine();

        $this->info("Revisadas: {$reviewed}");
        $this->info("Con cambio: {$changed}");

        if ($dryRun) {
            $this->warn(
                'Modo dry-run: no se modificó la base de datos.'
            );
        }

        if ($errors > 0) {
            $this->error("Errores: {$errors}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveTransition(
        object $license,
        CarbonImmutable $today,
        int $graceDays,
    ): ?array {
        $status = (string) $license->status;

        $expiresAt = $license->expires_at
            ? CarbonImmutable::parse(
                $license->expires_at
            )->startOfDay()
            : null;

        $trialEndsAt = $license->trial_ends_at
            ? CarbonImmutable::parse(
                $license->trial_ends_at
            )->startOfDay()
            : null;

        $graceEndsAt = $license->grace_ends_at
            ? CarbonImmutable::parse(
                $license->grace_ends_at
            )->startOfDay()
            : null;

        if ($status === 'trial') {
            $trialExpiration = $trialEndsAt
                ?? $expiresAt;

            if (
                $trialExpiration !== null
                && $trialExpiration->lt($today)
            ) {
                return [
                    'status' => 'expired',
                    'grace_ends_at' =>
                        $license->grace_ends_at,
                    'event_type' => 'trial_expired',
                ];
            }

            return null;
        }

        if ($status === 'active') {
            if (
                $expiresAt === null
                || $expiresAt->gte($today)
            ) {
                return null;
            }

            if ($graceDays <= 0) {
                return [
                    'status' => 'expired',
                    'grace_ends_at' => null,
                    'event_type' => 'expired',
                ];
            }

            $newGraceEnd = $graceEndsAt
                ?? $expiresAt->addDays($graceDays);

            if ($newGraceEnd->gte($today)) {
                return [
                    'status' => 'grace',
                    'grace_ends_at' =>
                        $newGraceEnd->toDateString(),
                    'event_type' => 'grace_started',
                ];
            }

            return [
                'status' => 'expired',
                'grace_ends_at' =>
                    $newGraceEnd->toDateString(),
                'event_type' => 'expired',
            ];
        }

        if ($status === 'grace') {
            if (
                $graceEndsAt === null
                || $graceEndsAt->lt($today)
            ) {
                return [
                    'status' => 'expired',
                    'grace_ends_at' =>
                        $license->grace_ends_at,
                    'event_type' => 'grace_expired',
                ];
            }
        }

        return null;
    }
}