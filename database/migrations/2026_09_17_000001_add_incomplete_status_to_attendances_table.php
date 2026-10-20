<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the "forgotten Time Out" handling (see AttendanceService::timeIn).
 * A previous day's attendance that never received a Time Out is marked
 * 'incomplete' (instead of silently attaching the next day's Time Out to
 * it) and flagged with missing_time_out so it surfaces for regularization
 * rather than corrupting the new day's record.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL/MariaDB requires the enum to be redefined; SQLite/Postgres
        // (via Laravel's schema builder) store status as a string check
        // constraint, so we branch on driver to stay compatible with both
        // local (sqlite) and production (mysql) environments.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','late','half_day','on_leave','holiday','weekend','incomplete') NOT NULL DEFAULT 'present'");
        }

        Schema::table('attendances', function ($table) {
            if (! Schema::hasColumn('attendances', 'missing_time_out')) {
                $table->boolean('missing_time_out')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function ($table) {
            if (Schema::hasColumn('attendances', 'missing_time_out')) {
                $table->dropColumn('missing_time_out');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE attendances SET status = 'absent' WHERE status = 'incomplete'");
            DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','late','half_day','on_leave','holiday','weekend') NOT NULL DEFAULT 'present'");
        }
    }
};
