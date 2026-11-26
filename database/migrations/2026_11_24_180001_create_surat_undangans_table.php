<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('surat_undangans')) {
            Schema::create('surat_undangans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
                $table->foreignId('kop_surat_id')->nullable()->constrained('kop_surats')->nullOnDelete();
                $table->string('nomor_surat'); // Manual input
                $table->string('sifat')->default('Biasa');
                $table->string('lampiran_teks')->default('1 (satu) set');
                $table->string('hal');
                $table->date('tanggal_surat');
                $table->text('yth_teks')->nullable();
                $table->text('paragraf_pembuka')->nullable();
                $table->string('tipe_isi')->default('opsi2'); // opsi1, opsi2, custom
                $table->longText('isi_surat_html')->nullable();

                // Pengaturan Lampiran 1 (Peserta)
                $table->boolean('sertakan_lampiran_peserta')->default(true);
                $table->json('kolom_lampiran_peserta')->nullable(); // ['nip', 'asal_kecamatan', 'sobat_id', 'email', 'phone']
                $table->json('peserta_ids')->nullable(); // specific user ids if filtered

                // Pengaturan Lampiran 2 (Jadwal)
                $table->boolean('sertakan_lampiran_jadwal')->default(true);
                $table->string('mode_jadwal')->default('builder'); // builder, upload
                $table->string('jadwal_image_path')->nullable();
                $table->boolean('tampilkan_durasi_jadwal')->default(false);

                // Penandatangan
                $table->string('signer_name')->nullable();
                $table->string('signer_nip')->nullable();
                $table->string('signer_title')->nullable();
                $table->string('signer_city')->default('Demak');
                $table->string('signer_signature_path')->nullable();
                $table->string('status')->default('approved'); // draft, approved
                $table->string('hash', 64)->nullable()->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_undangans');
    }
};
