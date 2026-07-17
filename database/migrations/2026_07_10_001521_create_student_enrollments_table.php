<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (
            Blueprint $table
        ): void {
            $table->id();

            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('academic_cycle_id')
                ->constrained('academic_cycles')
                ->cascadeOnDelete();

            $table->foreignId('school_group_id')
                ->nullable()
                ->constrained('school_groups')
                ->nullOnDelete();

            $table->foreignId('campus_id')
                ->nullable()
                ->constrained('campuses')
                ->nullOnDelete();

            $table->foreignId('previous_enrollment_id')
                ->nullable()
                ->constrained('student_enrollments')
                ->nullOnDelete();

            /*
             * active:
             * Inscripción vigente en el ciclo.
             *
             * completed:
             * Concluyó normalmente.
             *
             * withdrawn:
             * Baja durante el ciclo.
             *
             * transferred:
             * Transferido a otra escuela.
             *
             * not_reenrolled:
             * No continuó al siguiente ciclo.
             *
             * graduated:
             * Egresó.
             */
            $table->string('status', 30)
                ->default('active');

            /*
             * new:
             * Nuevo ingreso.
             *
             * reenrollment:
             * Reinscripción.
             *
             * promotion:
             * Promovido al siguiente grado.
             *
             * repeat:
             * Repite grado.
             *
             * transfer:
             * Ingreso por transferencia.
             */
            $table->string('enrollment_type', 30)
                ->default('new');

            $table->date('enrolled_on')
                ->nullable();

            $table->date('completed_on')
                ->nullable();

            $table->date('withdrawn_on')
                ->nullable();

            $table->string('withdrawal_reason', 255)
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            /*
             * Un alumno solo puede tener una inscripción
             * principal por ciclo escolar.
             */
            $table->unique(
                [
                    'student_id',
                    'academic_cycle_id',
                ],
                'student_enrollments_student_cycle_unique'
            );

            $table->index(
                [
                    'school_id',
                    'academic_cycle_id',
                    'status',
                ],
                'student_enrollments_school_cycle_status_index'
            );

            $table->index(
                [
                    'school_id',
                    'school_group_id',
                    'status',
                ],
                'student_enrollments_school_group_status_index'
            );

            $table->index(
                [
                    'student_id',
                    'status',
                ],
                'student_enrollments_student_status_index'
            );
        });

        /*
         * Migración inicial:
         *
         * Cada alumno existente se inscribe en el ciclo
         * al que pertenece su grupo actual.
         */
        $students = DB::table('students as s')
            ->join('school_groups as sg', function (
                $join
            ): void {
                $join->on(
                    'sg.id',
                    '=',
                    's.current_group_id'
                )
                    ->on(
                        'sg.school_id',
                        '=',
                        's.school_id'
                    );
            })
            ->join('academic_cycles as ac', function (
                $join
            ): void {
                $join->on(
                    'ac.id',
                    '=',
                    'sg.academic_cycle_id'
                )
                    ->on(
                        'ac.school_id',
                        '=',
                        's.school_id'
                    );
            })
            ->select([
                's.id as student_id',
                's.school_id',
                's.campus_id',
                's.current_group_id',
                's.status as student_status',
                'sg.academic_cycle_id',
                'ac.starts_on',
                'ac.ends_on',
                'ac.status as cycle_status',
            ])
            ->orderBy('s.id')
            ->get();

        $now = now();

        foreach ($students as $student) {
            $status = match (true) {
                $student->student_status !== 'active' =>
                    'withdrawn',

                $student->cycle_status === 'closed' =>
                    'completed',

                default =>
                    'active',
            };

            DB::table('student_enrollments')
                ->insert([
                    'school_id' =>
                        (int) $student->school_id,

                    'student_id' =>
                        (int) $student->student_id,

                    'academic_cycle_id' =>
                        (int) $student->academic_cycle_id,

                    'school_group_id' =>
                        (int) $student->current_group_id,

                    'campus_id' =>
                        (int) $student->campus_id,

                    'previous_enrollment_id' =>
                        null,

                    'status' =>
                        $status,

                    'enrollment_type' =>
                        'new',

                    'enrolled_on' =>
                        $student->starts_on,

                    'completed_on' =>
                        $status === 'completed'
                            ? $student->ends_on
                            : null,

                    'withdrawn_on' =>
                        null,

                    'withdrawal_reason' =>
                        null,

                    'notes' =>
                        'Inscripción inicial creada automáticamente desde el grupo actual del alumno.',

                    'created_by_user_id' =>
                        null,

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};