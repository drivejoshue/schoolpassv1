<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_notices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 160);
            $table->string('subtitle', 220)->nullable();
            $table->string('header', 180)->nullable();
            $table->text('body');
            $table->string('footer', 220)->nullable();

            $table->string('banner_path', 255)->nullable();
            $table->string('banner_alt', 160)->nullable();

            $table->string('priority', 30)->default('normal');
            // normal | important | urgent

            $table->boolean('show_as_modal')->default(false);
            $table->boolean('requires_ack')->default(false);

            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 255)->nullable();

            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('status', 30)->default('draft');
            // draft | published | archived

            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'status', 'publish_at']);
            $table->index(['school_id', 'expires_at']);
            $table->index(['school_id', 'priority']);
        });

        Schema::create('school_notice_targets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_notice_id')->constrained('school_notices')->cascadeOnDelete();

            $table->string('target_type', 40);
            // all_school | group | student | guardian | user

            $table->unsignedBigInteger('target_id')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'target_type', 'target_id']);
            $table->index(['school_notice_id', 'target_type']);
        });

        Schema::create('school_notice_reads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('school_notice_id')->constrained('school_notices')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();

            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();

            $table->timestamps();

            $table->unique(['school_notice_id', 'user_id']);
            $table->index(['school_id', 'guardian_id']);
            $table->index(['school_notice_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_notice_reads');
        Schema::dropIfExists('school_notice_targets');
        Schema::dropIfExists('school_notices');
    }
};