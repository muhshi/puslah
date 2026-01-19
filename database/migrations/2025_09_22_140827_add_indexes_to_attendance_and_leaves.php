<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ATTENDANCES
        Schema::table('attendances', function (Blueprint $table) {
            if (!$this->indexExists('attendances', 'idx_attendances_user_start')) {
                $table->index(['user_id', 'start_time'], 'idx_attendances_user_start');
            }
            if (!$this->indexExists('attendances', 'idx_attendances_user_end')) {
                $table->index(['user_id', 'end_time'], 'idx_attendances_user_end');
            }
        });

        // LEAVES
        Schema::table('leaves', function (Blueprint $table) {
            if (!$this->indexExists('leaves', 'idx_leaves_user_status_dates')) {
                $table->index(['user_id', 'status', 'start_date', 'end_date'], 'idx_leaves_user_status_dates');
            }
        });

        // SURVEY_USERS
        Schema::table('survey_users', function (Blueprint $table) {
            if (!$this->indexExists('survey_users', 'idx_survey_users_survey_user')) {
                $table->index(['survey_id', 'user_id'], 'idx_survey_users_survey_user');
            }
            if (!$this->indexExists('survey_users', 'idx_survey_users_user_survey')) {
                $table->index(['user_id', 'survey_id'], 'idx_survey_users_user_survey');
            }
        });

        // ANALYZE TABLES
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ANALYZE TABLE attendances');
            DB::statement('ANALYZE TABLE leaves');
            DB::statement('ANALYZE TABLE survey_users');
        }
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $this->dropIndexIfExists('attendances', 'idx_attendances_user_start');
            $this->dropIndexIfExists('attendances', 'idx_attendances_user_end');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $this->dropIndexIfExists('leaves', 'idx_leaves_user_status_dates');
        });

        Schema::table('survey_users', function (Blueprint $table) {
            $this->dropIndexIfExists('survey_users', 'idx_survey_users_survey_user');
            $this->dropIndexIfExists('survey_users', 'idx_survey_users_user_survey');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('$table')");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        $db = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $db)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }
};
