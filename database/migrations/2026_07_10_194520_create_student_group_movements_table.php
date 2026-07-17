<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'student_group_movements',
            function (Blueprint $table): void {
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

                $table->foreignId('enrollment_id')
                    ->nullable()
                    ->constrained('student_enrollments')
                    ->nullOnDelete();

                $table->foreignId('from_group_id')
                    ->nullable()
                    ->constrained('school_groups')
                    ->nullOnDelete();

                $table->foreignId('to_group_id')
                    ->nullable()
                    ->constrained('school_groups')
                    ->nullOnDelete();

                $table->string('movement_type', 40);

                $table->date('effective_on');

                $table->string('reason', 255)
                    ->nullable();

                $table->text('notes')
                    ->nullable();

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'school_id',
                    'academic_cycle_id',
                    'effective_on',
                ], 'student_group_movements_cycle_date_index');

                $table->index([
                    'student_id',
                    'effective_on',
                ], 'student_group_movements_student_date_index');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'student_group_movements'
        );
    }
};