<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'support_impersonations',
            function (Blueprint $table): void {
                $table
                    ->dateTime('expires_at')
                    ->nullable()
                    ->after('started_at');

                $table
                    ->string('ended_reason', 60)
                    ->nullable()
                    ->after('ended_at');

                $table->index(
                    [
                        'ended_at',
                        'expires_at',
                    ],
                    'support_impersonations_expiry_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'support_impersonations',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'support_impersonations_expiry_index'
                );

                $table->dropColumn([
                    'expires_at',
                    'ended_reason',
                ]);
            }
        );
    }
};