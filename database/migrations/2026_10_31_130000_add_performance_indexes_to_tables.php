<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // SURAT TUGAS INDEXES
        Schema::table('surat_tugas', function (Blueprint $table) {
            if (!$this->hasIndexSafely('surat_tugas', 'idx_surat_tugas_status')) {
                $table->index('status', 'idx_surat_tugas_status');
            }
            if (!$this->hasIndexSafely('surat_tugas', 'idx_surat_tugas_nomor_urut')) {
                $table->index('nomor_urut', 'idx_surat_tugas_nomor_urut');
            }
            if (!$this->hasIndexSafely('surat_tugas', 'idx_surat_tugas_tanggal')) {
                $table->index('tanggal', 'idx_surat_tugas_tanggal');
            }
            if (!$this->hasIndexSafely('surat_tugas', 'idx_surat_tugas_tanggal_nomor_urut')) {
                $table->index(['tanggal', 'nomor_urut'], 'idx_surat_tugas_tanggal_nomor_urut');
            }
            if (!$this->hasIndexSafely('surat_tugas', 'idx_surat_tugas_user_status')) {
                $table->index(['user_id', 'status'], 'idx_surat_tugas_user_status');
            }
            if (!$this->hasIndexSafely('surat_tugas', 'idx_surat_tugas_created_by')) {
                $table->index('created_by', 'idx_surat_tugas_created_by');
            }
            if (!$this->hasIndexSafely('surat_tugas', 'idx_surat_tugas_waktu_mulai_selesai')) {
                $table->index(['waktu_mulai', 'waktu_selesai'], 'idx_surat_tugas_waktu_mulai_selesai');
            }
        });

        // LAPORAN PERJALANAN DINAS INDEXES
        Schema::table('laporan_perjalanan_dinas', function (Blueprint $table) {
            if (!$this->hasIndexSafely('laporan_perjalanan_dinas', 'idx_lpd_tanggal_kunjungan')) {
                $table->index('tanggal_kunjungan', 'idx_lpd_tanggal_kunjungan');
            }
            if (!$this->hasIndexSafely('laporan_perjalanan_dinas', 'idx_lpd_st_id_tanggal')) {
                $table->index(['surat_tugas_id', 'tanggal_kunjungan'], 'idx_lpd_st_id_tanggal');
            }
            if (!$this->hasIndexSafely('laporan_perjalanan_dinas', 'idx_lpd_nomor_surat_tugas')) {
                $table->index('nomor_surat_tugas', 'idx_lpd_nomor_surat_tugas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $this->dropIndexSafely('surat_tugas', 'idx_surat_tugas_status');
            $this->dropIndexSafely('surat_tugas', 'idx_surat_tugas_nomor_urut');
            $this->dropIndexSafely('surat_tugas', 'idx_surat_tugas_tanggal');
            $this->dropIndexSafely('surat_tugas', 'idx_surat_tugas_tanggal_nomor_urut');
            $this->dropIndexSafely('surat_tugas', 'idx_surat_tugas_user_status');
            $this->dropIndexSafely('surat_tugas', 'idx_surat_tugas_created_by');
            $this->dropIndexSafely('surat_tugas', 'idx_surat_tugas_waktu_mulai_selesai');
        });

        Schema::table('laporan_perjalanan_dinas', function (Blueprint $table) {
            $this->dropIndexSafely('laporan_perjalanan_dinas', 'idx_lpd_tanggal_kunjungan');
            $this->dropIndexSafely('laporan_perjalanan_dinas', 'idx_lpd_st_id_tanggal');
            $this->dropIndexSafely('laporan_perjalanan_dinas', 'idx_lpd_nomor_surat_tugas');
        });
    }

    private function hasIndexSafely(string $table, string $indexName): bool
    {
        if (method_exists(Schema::class, 'hasIndex')) {
            try {
                return Schema::hasIndex($table, $indexName);
            } catch (\Throwable $e) {
                // fallback below
            }
        }

        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'sqlite') {
                $indexes = Schema::getConnection()->select("PRAGMA index_list('{$table}')");
                foreach ($indexes as $index) {
                    if (($index->name ?? null) === $indexName) {
                        return true;
                    }
                }
                return false;
            }

            if ($driver === 'pgsql') {
                return !empty(Schema::getConnection()->select(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $indexName]
                ));
            }

            // mysql / mariadb
            $db = Schema::getConnection()->getDatabaseName();
            return !empty(Schema::getConnection()->select(
                "SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$db, $table, $indexName]
            ));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function dropIndexSafely(string $table, string $indexName): void
    {
        if ($this->hasIndexSafely($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }
};
