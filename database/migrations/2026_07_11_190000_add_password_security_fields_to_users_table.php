<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn(
                'users',
                'must_change_password'
            )
        ) {
            Schema::table(
                'users',
                function (Blueprint $table): void {
                    $table
                        ->boolean('must_change_password')
                        ->default(false)
                        ->after('password');
                }
            );
        }

        if (
            ! Schema::hasColumn(
                'users',
                'password_changed_at'
            )
        ) {
            Schema::table(
                'users',
                function (Blueprint $table): void {
                    $table
                        ->timestamp('password_changed_at')
                        ->nullable()
                        ->after('must_change_password');
                }
            );
        }

        if (
            ! Schema::hasColumn(
                'users',
                'last_login_at'
            )
        ) {
            Schema::table(
                'users',
                function (Blueprint $table): void {
                    $table
                        ->timestamp('last_login_at')
                        ->nullable()
                        ->after('password_changed_at');
                }
            );
        }
    }

    public function down(): void
    {
        Schema::table(
            'users',
            function (Blueprint $table): void {
                $columns = [];

                if (
                    Schema::hasColumn(
                        'users',
                        'must_change_password'
                    )
                ) {
                    $columns[] = 'must_change_password';
                }

                if (
                    Schema::hasColumn(
                        'users',
                        'password_changed_at'
                    )
                ) {
                    $columns[] = 'password_changed_at';
                }

                if (
                    Schema::hasColumn(
                        'users',
                        'last_login_at'
                    )
                ) {
                    $columns[] = 'last_login_at';
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            }
        );
    }
};