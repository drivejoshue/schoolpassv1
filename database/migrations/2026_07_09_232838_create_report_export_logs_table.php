<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_export_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('report_key', 100);
            $table->string('report_name', 160);
            $table->string('format', 20);

            $table->string('route_name', 180)->nullable();
            $table->string('request_path', 255)->nullable();

            $table->json('filters_json')->nullable();

            $table->string('status', 30)
                ->default('success');

            $table->unsignedInteger('http_status')
                ->nullable();

            $table->unsignedInteger('duration_ms')
                ->nullable();

            $table->string('download_filename', 255)
                ->nullable();

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->timestamp('exported_at')
                ->useCurrent();

            $table->timestamps();

            $table->index([
                'school_id',
                'exported_at',
            ]);

            $table->index([
                'school_id',
                'report_key',
            ]);

            $table->index([
                'school_id',
                'user_id',
            ]);

            $table->index([
                'school_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_export_logs');
    }
};