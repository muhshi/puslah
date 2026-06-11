<?php

namespace App\Imports;

use App\Models\SuratTugas;
use App\Models\User;
use App\Settings\SystemSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SuratTugasImport implements ToCollection, WithHeadingRow
{
    public $success = 0;
    public $skipped = 0; // e.g. user not found or already has surat tugas
    public $failed = 0;

    protected $surveyId;
    protected $tanggal;
    protected $waktuMulai;
    protected $waktuSelesai;
    protected $keperluan;
    protected $kodeKlasifikasi;
    
    protected $settings;
    protected $year;

    public function __construct($surveyId, $tanggal, $waktuMulai, $waktuSelesai, $keperluan, $kodeKlasifikasi = 'KP.650')
    {
        $this->surveyId = $surveyId;
        $this->tanggal = $tanggal;
        $this->waktuMulai = $waktuMulai;
        $this->waktuSelesai = $waktuSelesai;
        $this->keperluan = $keperluan;
        $this->kodeKlasifikasi = $kodeKlasifikasi;
        
        $this->settings = app(SystemSettings::class);
        $this->year = \Carbon\Carbon::parse($tanggal)->year;
    }

    public function collection(Collection $rows)
    {
        // Pre-fetch used and blocked numbers for the year to avoid collision
        $usedNumbers = SuratTugas::getOccupiedNumbers($this->year);
        // We will maintain current max urut locally during loop
        $currentUrut = SuratTugas::getNextNomorUrut($this->year) - 1;

        $prefix = $this->settings->surat_prefix ?? 'B';
        $office = $this->settings->office_code ?? '33210';
        $creatorId = auth()->id();

        DB::transaction(function () use ($rows, &$currentUrut, $usedNumbers, $prefix, $office, $creatorId) {
            foreach ($rows as $row) {
                try {
                    $email = strtolower(trim($row['email'] ?? $row['email_petugas'] ?? ''));
                    $jabatan = trim($row['jabatan'] ?? $row['jabatan_tugas'] ?? '');
                    $tempatTugas = trim($row['tempat_tugas'] ?? $row['kecamatan_tugas'] ?? '');

                    if (empty($email) || empty($jabatan) || empty($tempatTugas)) {
                        $this->skipped++;
                        continue;
                    }

                    // Cek apakah ada kata "mundur" di kolom mana saja (misal di Kolom H / Keterangan)
                    $isMundur = false;
                    foreach ($row as $val) {
                        if (is_string($val) && stripos($val, 'mundur') !== false) {
                            $isMundur = true;
                            break;
                        }
                    }

                    if ($isMundur) {
                        $this->skipped++;
                        continue;
                    }

                    $user = User::where('email', $email)->first();

                    if (!$user) {
                        $nama = trim($row['nama_petugas'] ?? '');
                        if (empty($nama)) {
                            // Cannot auto-create without a name
                            $this->failed++;
                            continue;
                        }

                        $user = User::create([
                            'name' => ucwords(strtolower($nama)),
                            'email' => $email,
                            'password' => \Illuminate\Support\Facades\Hash::make('Mitra3321'),
                        ]);
                        
                        $user->assignRole('Mitra');
                    }

                    // Ensure the user is a participant of the survey
                    $isParticipant = \App\Models\SurveyUser::where('user_id', $user->id)
                        ->where('survey_id', $this->surveyId)
                        ->exists();

                    if (!$isParticipant) {
                        \App\Models\SurveyUser::create([
                            'user_id' => $user->id,
                            'survey_id' => $this->surveyId,
                        ]);
                    }

                    // Check if already has surat tugas for this survey
                    $exists = SuratTugas::where('user_id', $user->id)
                        ->where('survey_id', $this->surveyId)
                        ->exists();

                    if ($exists) {
                        $this->skipped++;
                        continue;
                    }

                    // Advance the nomor_urut safely
                    $currentUrut++;
                    while (isset($usedNumbers[$currentUrut])) {
                        $currentUrut++;
                    }
                    // Mark as used for the next iteration
                    $usedNumbers[$currentUrut] = true;

                    $urut = str_pad($currentUrut, 4, '0', STR_PAD_LEFT);
                    $nomorSurat = "{$prefix}-{$urut}/{$office}/{$this->kodeKlasifikasi}/{$this->year}";

                    SuratTugas::create([
                        'user_id' => $user->id,
                        'survey_id' => $this->surveyId,
                        'nomor_surat' => $nomorSurat,
                        'nomor_urut' => $currentUrut,
                        'kode_klasifikasi' => $this->kodeKlasifikasi,
                        'jabatan' => $jabatan,
                        'keperluan' => $this->keperluan,
                        'tempat_tugas' => $tempatTugas,
                        'tanggal' => $this->tanggal,
                        'waktu_mulai' => $this->waktuMulai,
                        'waktu_selesai' => $this->waktuSelesai,
                        'signer_city' => $this->settings->cert_city,
                        'signer_name' => $this->settings->cert_signer_name,
                        'signer_nip' => $this->settings->cert_signer_nip,
                        'signer_title' => $this->settings->cert_signer_title,
                        'signer_signature_path' => $this->settings->cert_signer_signature_path,
                        'created_by' => $creatorId,
                    ]);

                    $this->success++;
                } catch (\Throwable $e) {
                    $this->failed++;
                }
            }
        });
    }
}
