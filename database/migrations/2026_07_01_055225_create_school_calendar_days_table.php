<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_calendar_days')) {
            Schema::create('school_calendar_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('academic_cycle_id')->nullable()->constrained('academic_cycles')->nullOnDelete();
                $table->date('date');
                $table->string('type', 40)->default('class_day');
                $table->string('title', 160);
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();

                $table->unique(['school_id', 'date']);
                $table->index(['school_id', 'type', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_calendar_days');
    }
};