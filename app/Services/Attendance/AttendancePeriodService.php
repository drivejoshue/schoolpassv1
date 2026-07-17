<?php

namespace App\Services\Attendance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendancePeriodService
{
    /**
     * Retorna el ciclo marcado administrativamente como activo,
     * aunque todavía no haya comenzado o ya esté fuera de la
     * fecha consultada.
     */
    public function activeCycle(
        int $schoolId
    ): ?object {
        return DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Retorna las fechas completas del ciclo activo.
     *
     * No devuelve null solamente porque el ciclo comience
     * en el futuro. La existencia del ciclo y su vigencia
     * temporal son conceptos distintos.
     */
    public function attendanceWindow(
        int $schoolId
    ): ?array {
        $cycle = $this->activeCycle(
            $schoolId
        );

        if (! $cycle) {
            return null;
        }

        return [
            'cycle' => $cycle,

            'start' => Carbon::parse(
                $cycle->starts_on
            )->startOfDay(),

            'end' => Carbon::parse(
                $cycle->ends_on
            )->endOfDay(),
        ];
    }

    /**
     * Interseca un periodo solicitado con:
     *
     * - fechas del ciclo activo;
     * - fecha actual;
     * - rango solicitado.
     *
     * Devuelve null cuando el periodo no tiene días
     * evaluables para asistencia.
     */
    public function clampRange(
        int $schoolId,
        Carbon|string $from,
        Carbon|string $to
    ): ?array {
        $window = $this->attendanceWindow(
            $schoolId
        );

        if (! $window) {
            return null;
        }

        $requestedFrom = $from instanceof Carbon
            ? $from->copy()->startOfDay()
            : Carbon::parse($from)->startOfDay();

        $requestedTo = $to instanceof Carbon
            ? $to->copy()->endOfDay()
            : Carbon::parse($to)->endOfDay();

        $effectiveFrom = $requestedFrom->greaterThan(
            $window['start']
        )
            ? $requestedFrom
            : $window['start']->copy();

        $today = now(
            config('app.timezone')
        )->endOfDay();

        $effectiveTo = collect([
            $requestedTo,
            $window['end'],
            $today,
        ])
            ->sortBy(
                fn (Carbon $date): int =>
                    $date->getTimestamp()
            )
            ->first()
            ->copy();

        if (
            $effectiveFrom->greaterThan(
                $effectiveTo
            )
        ) {
            return null;
        }

        return [
            'cycle' => $window['cycle'],
            'from' => $effectiveFrom,
            'to' => $effectiveTo,
        ];
    }

    /**
     * Indica si una fecha específica pertenece al ciclo
     * administrativamente activo.
     */
    public function dateIsInsideActiveCycle(
        int $schoolId,
        Carbon|string $date
    ): bool {
        $window = $this->attendanceWindow(
            $schoolId
        );

        if (! $window) {
            return false;
        }

        $value = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date)->startOfDay();

        return $value->betweenIncluded(
            $window['start'],
            $window['end']
        );
    }
}