<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('areas') && ! Schema::hasColumn('areas', 'code')) {
            Schema::table('areas', function (Blueprint $table) {
                $table->string('code', 80)->nullable()->after('name');
            });

            $areas = DB::table('areas')->select('id', 'name')->get();

            foreach ($areas as $area) {
                DB::table('areas')
                    ->where('id', $area->id)
                    ->update([
                        'code' => Str::slug($area->name ?: ('area-' . $area->id)),
                    ]);
            }

            Schema::table('areas', function (Blueprint $table) {
                $table->index('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('areas') && Schema::hasColumn('areas', 'code')) {
            Schema::table('areas', function (Blueprint $table) {
                $table->dropIndex(['code']);
                $table->dropColumn('code');
            });
        }
    }
};