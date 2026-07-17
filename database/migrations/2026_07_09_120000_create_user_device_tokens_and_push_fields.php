<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('installation_uuid', 120);
            $table->longText('fcm_token');
            $table->char('token_hash', 64)->unique();

            $table->string('platform', 20)->default('android');
            $table->string('app_key', 60)->default('schoolpass_family');
            $table->string('app_flavor', 80)->nullable();
            $table->string('app_version_name', 40)->nullable();
            $table->unsignedBigInteger('app_version_code')->nullable();
            $table->string('device_name', 150)->nullable();
            $table->string('os_version', 80)->nullable();
            $table->string('locale', 20)->nullable();
            $table->string('timezone', 80)->nullable();

            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamp('last_registered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'installation_uuid', 'app_key'],
                'user_device_tokens_user_installation_app_unique'
            );

            $table->index(
                ['school_id', 'app_key', 'is_active'],
                'user_device_tokens_school_app_active_index'
            );
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('push_status', 30)->default('pending')->after('status');
            $table->timestamp('push_attempted_at')->nullable()->after('push_status');
            $table->timestamp('push_sent_at')->nullable()->after('push_attempted_at');
            $table->string('push_error_code', 120)->nullable()->after('push_sent_at');
            $table->text('push_error_message')->nullable()->after('push_error_code');
            $table->string('reference_type', 60)->nullable()->after('push_error_message');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');

            $table->index(
                ['school_id', 'push_status'],
                'notifications_school_push_status_index'
            );
            $table->index(
                ['school_id', 'reference_type', 'reference_id'],
                'notifications_reference_index'
            );
        });

        Schema::table('school_notices', function (Blueprint $table) {
            $table->timestamp('push_dispatched_at')->nullable()->after('archived_at');
            $table->unsignedInteger('push_recipient_count')->default(0)->after('push_dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('school_notices', function (Blueprint $table) {
            $table->dropColumn(['push_dispatched_at', 'push_recipient_count']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_school_push_status_index');
            $table->dropIndex('notifications_reference_index');
            $table->dropColumn([
                'push_status',
                'push_attempted_at',
                'push_sent_at',
                'push_error_code',
                'push_error_message',
                'reference_type',
                'reference_id',
            ]);
        });

        Schema::dropIfExists('user_device_tokens');
    }
};
