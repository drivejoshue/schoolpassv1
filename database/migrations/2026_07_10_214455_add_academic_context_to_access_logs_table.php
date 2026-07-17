<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'access_logs',
            function (Blueprint $table): void {
                $table->foreignId(
                    'academic_cycle_id'
                )
                    ->nullable()
                    ->after('student_id')
                    ->constrained(
                        'academic_cycles'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'student_enrollment_id'
                )
                    ->nullable()
                    ->after('academic_cycle_id')
                    ->constrained(
                        'student_enrollments'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'school_group_id'
                )
                    ->nullable()
                    ->after('student_enrollment_id')
                    ->constrained(
                        'school_groups'
                    )
                    ->nullOnDelete();

                $table->index([
                    'school_id',
                    'academic_cycle_id',
                    'school_group_id',
                    'scanned_at',
                ], 'access_logs_academic_context_index');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'access_logs',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'access_logs_academic_context_index'
                );

                $table->dropConstrainedForeignId(
                    'school_group_id'
                );

                $table->dropConstrainedForeignId(
                    'student_enrollment_id'
                );

                $table->dropConstrainedForeignId(
                    'academic_cycle_id'
                );
            }
        );
    }
};