<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_access_schedules')) {
            Schema::create('group_access_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('group_id')->constrained('school_groups')->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday'); // 1 lunes, 7 domingo
                $table->time('entry_time');
                $table->time('grace_until');
                $table->time('late_until');
                $table->time('exit_time');
                $table->string('status', 30)->default('active');
                $table->timestamps();

                $table->unique(['group_id', 'weekday']);
                $table->index(['school_id', 'weekday']);
            });
        }

        if (! Schema::hasTable('area_access_rules')) {
            Schema::create('area_access_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();

                $table->string('applies_to_type', 40); // group, student, user_role, user
                $table->unsignedBigInteger('applies_to_id')->nullable();
                $table->string('role_name', 50)->nullable();

                $table->unsignedTinyInteger('weekday')->nullable();
                $table->time('starts_at')->nullable();
                $table->time('ends_at')->nullable();

                $table->string('status', 30)->default('active');
                $table->timestamps();

                $table->index('area_id');
                $table->index(['applies_to_type', 'applies_to_id']);
                $table->index(['school_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('area_access_rules');
        Schema::dropIfExists('group_access_schedules');
    }
};