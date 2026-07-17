<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('academic_cycles')) {
            return;
        }

        Schema::table('academic_cycles', function (Blueprint $table) {
            if (! Schema::hasColumn('academic_cycles', 'starts_on')) {
                $table->date('starts_on')->nullable()->after('name');
            }

            if (! Schema::hasColumn('academic_cycles', 'ends_on')) {
                $table->date('ends_on')->nullable()->after('starts_on');
            }

            if (! Schema::hasColumn('academic_cycles', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('ends_on');
            }

            if (! Schema::hasColumn('academic_cycles', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('academic_cycles', 'status')) {
                $table->string('status', 30)->default('draft')->after('closed_at');
            }

            if (! Schema::hasColumn('academic_cycles', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        //
    }
};