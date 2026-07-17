<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'school_groups',
            function (Blueprint $table): void {
                $table
                    ->unsignedSmallInteger(
                        'auto_transition_minutes'
                    )
                    ->default(30)
                    ->after('requires_guardian_scan');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'school_groups',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'auto_transition_minutes'
                );
            }
        );
    }
};