<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('address', 255)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->unique()->after('email');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 40)->default('director')->after('password');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 30)->default('active')->after('role');
            }
        });

        Schema::create('academic_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('academic_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('school_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('academic_level_id')->constrained('academic_levels')->cascadeOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('grade_label', 100)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['school_id', 'academic_level_id']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('current_group_id')->constrained('school_groups')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_code', 50);
            $table->string('first_name', 100);
            $table->string('last_name', 150);
            $table->string('photo_url', 255)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'student_code']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->string('relationship', 50);
            $table->boolean('can_view_attendance')->default(true);
            $table->boolean('can_receive_notifications')->default(true);
            $table->boolean('can_authorize_exit')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
        });

        Schema::create('student_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('type', 30)->default('qr');
            $table->char('token_hash', 64)->unique();
            $table->string('public_code', 80)->nullable();
            $table->string('status', 30)->default('active');
            $table->dateTime('issued_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoked_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 40)->default('general');
            $table->boolean('affects_attendance')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['school_id', 'campus_id']);
        });

        Schema::create('access_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('name', 150);
            $table->string('device_uuid', 120)->unique();
            $table->string('platform', 30)->default('web');
            $table->string('device_type', 40)->default('prefect_app');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 40)->default('attendance');
            $table->string('default_event_type', 30)->default('entry');
            $table->boolean('can_unlock')->default(false);
            $table->boolean('allow_manual_search')->default(false);
            $table->boolean('show_student_photo')->default(true);
            $table->integer('auto_reset_seconds')->default(3);
            $table->time('active_from')->nullable();
            $table->time('active_until')->nullable();
            $table->string('status', 30)->default('active');
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('access_device_id')->nullable()->constrained('access_devices')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('credential_id')->nullable()->constrained('student_credentials')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 30);
            $table->string('event_status', 40);
            $table->string('decision', 40)->default('allowed');
            $table->string('action', 40)->default('none');
            $table->dateTime('scanned_at');
            $table->string('source', 40)->default('qr');
            $table->string('reader_type', 40)->default('camera_qr');
            $table->integer('minutes_late')->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'scanned_at']);
            $table->index(['school_id', 'scanned_at']);
        });

        Schema::create('daily_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('school_groups')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('entry_log_id')->nullable()->constrained('access_logs')->nullOnDelete();
            $table->foreignId('exit_log_id')->nullable()->constrained('access_logs')->nullOnDelete();
            $table->string('attendance_status', 40)->default('pending');
            $table->dateTime('entry_at')->nullable();
            $table->dateTime('exit_at')->nullable();
            $table->integer('minutes_late')->default(0);
            $table->timestamps();

            $table->unique(['student_id', 'date']);
            $table->index(['school_id', 'date']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('title', 150);
            $table->text('body');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('daily_attendance');
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('access_devices');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('student_credentials');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_groups');
        Schema::dropIfExists('academic_cycles');
        Schema::dropIfExists('academic_levels');
        Schema::dropIfExists('campuses');
        Schema::dropIfExists('schools');
    }
};