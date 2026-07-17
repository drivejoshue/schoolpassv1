<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_release_policies', function (Blueprint $table) {
            $table->id();

            $table->string('app_key', 30)->unique();
            $table->string('package_name', 180);

            $table->unsignedBigInteger('latest_version_code')
                ->default(1);

            $table->string('latest_version_name', 40)
                ->default('1.0.0');

            $table->unsignedBigInteger('minimum_supported_version_code')
                ->default(1);

            $table->boolean('force_update')
                ->default(false);

            $table->string('title', 120)
                ->nullable();

            $table->text('message')
                ->nullable();

            $table->string('store_url', 500)
                ->nullable();

            $table->timestamp('published_at')
                ->nullable();

            $table->timestamps();
        });

        DB::table('mobile_app_release_policies')->insert([
            [
                'app_key' => 'family',
                'package_name' => 'com.schoolpass.family',
                'latest_version_code' => 1,
                'latest_version_name' => '1.0.0',
                'minimum_supported_version_code' => 1,
                'force_update' => false,
                'title' => 'Nueva versión disponible',
                'message' => 'Actualiza SchoolPass Familia para recibir las últimas mejoras.',
                'store_url' => null,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'app_key' => 'staff',
                'package_name' => 'com.schoolpass.staff',
                'latest_version_code' => 1,
                'latest_version_name' => '1.0.0',
                'minimum_supported_version_code' => 1,
                'force_update' => false,
                'title' => 'Nueva versión disponible',
                'message' => 'Actualiza SchoolPass Staff para continuar con la versión más reciente.',
                'store_url' => null,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'mobile_app_release_policies'
        );
    }
};