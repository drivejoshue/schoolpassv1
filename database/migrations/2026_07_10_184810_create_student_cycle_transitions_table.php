<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'student_cycle_transitions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('school_id')
                    ->constrained('schools')
                    ->cascadeOnDelete();

                $table->foreignId('student_id')
                    ->constrained('students')
                    ->cascadeOnDelete();

                $table->foreignId('source_cycle_id')
                    ->constrained(
                        'academic_cycles'
                    )
                    ->cascadeOnDelete();

                $table->foreignId('destination_cycle_id')
                    ->constrained(
                        'academic_cycles'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'source_enrollment_id'
                )
                    ->nullable()
                    ->constrained(
                        'student_enrollments'
                    )
                    ->nullOnDelete();

                $table->foreignId('target_group_id')
                    ->nullable()
                    ->constrained('school_groups')
                    ->nullOnDelete();

                /*
                 * promotion:
                 * Pasa al siguiente grado/grupo.
                 *
                 * reenrollment:
                 * Continúa en el mismo grado o nivel.
                 *
                 * repeat:
                 * Repite grado.
                 *
                 * change_group:
                 * Continúa pero cambia de grupo.
                 *
                 * not_reenrolled:
                 * No continúa en el siguiente ciclo.
                 *
                 * graduated:
                 * Egresó.
                 *
                 * withdrawn:
                 * Baja definitiva.
                 */
                $table->string(
                    'decision',
                    30
                );

                /*
                 * draft:
                 * Decisión preparada.
                 *
                 * applied:
                 * Inscripción nueva y cambios aplicados.
                 */
                $table->string(
                    'status',
                    30
                )->default('draft');

                $table->text('notes')
                    ->nullable();

                $table->foreignId(
                    'created_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId(
                    'applied_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('applied_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'student_id',
                        'source_cycle_id',
                        'destination_cycle_id',
                    ],
                    'student_cycle_transition_unique'
                );

                $table->index(
                    [
                        'school_id',
                        'source_cycle_id',
                        'destination_cycle_id',
                        'status',
                    ],
                    'student_cycle_transition_status_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'student_cycle_transitions'
        );
    }
};