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
    protected $nomorUrutMulai;
    
    protected $settings;
    protected $year;

    public function __construct($surveyId, $tanggal, $waktuMulai, $waktuSelesai, $keperluan, $kodeKlasifikasi = 'KP.650', $nomorUrutMulai = null)
    {
        $this->surveyId = $surveyId;
        $this->tanggal = $tanggal;
        $this->waktuMulai = $waktuMulai;
        $this->waktuSelesai = $waktuSelesai;
        $this->keperluan = $keperluan;
        $this->kodeKlasifikasi = $kodeKlasifikasi;
        $this->nomorUrutMulai = $nomorUrutMulai;
        
        $this->settings = app(SystemSettings::class);
        $this->year = \Carbon\Carbon::parse($tanggal)->year;
    }

    public function collection(Collection $rows)
    {
        // Pre-fetch used and blocked numbers for the year to avoid collision
        $usedNumbers = SuratTugas::getOccupiedNumbers($this->year);
        
        if ($this->nomorUrutMulai !== null) {
            $currentUrut = (int) $this->nomorUrutMulai - 1;
        } else {
            $currentUrut = SuratTugas::getNextNomorUrut($this->year) - 1;
        }

        $prefix = $this->settings->surat_prefix ?? 'B';
        $office = $this->settings->office_code ?? '33210';
        $creatorId = auth()->id();

        DB::transaction(function () use ($rows, &$currentUrut, $usedNumbers, $prefix, $office, $creatorId) {
            $validRows = [];
            $emailsToFetch = [];

            // 1. Filter and Collect Emails
            foreach ($rows as $row) {
                $email = strtolower(trim($row['email'] ?? $row['email_petugas'] ?? ''));
                $jabatan = trim($row['jabatan'] ?? $row['jabatan_tugas'] ?? '');
                $tempatTugas = trim($row['tempat_tugas'] ?? $row['kecamatan_tugas'] ?? '');
                $nama = trim($row['nama_petugas'] ?? '');

                if (empty($email) || empty($jabatan) || empty($tempatTugas)) {
                    $this->skipped++;
                    continue;
                }

                // Cek apakah ada kata "mundur" di kolom mana saja
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

                $validRows[] = [
                    'email' => $email,
                    'nama' => $nama,
                    'jabatan' => $jabatan,
                    'tempat_tugas' => $tempatTugas,
                ];
                $emailsToFetch[] = $email;
            }

            if (empty($validRows)) {
                return;
            }

            // 2. Bulk Fetch Existing Users
            $usersByEmail = User::whereIn('email', array_unique($emailsToFetch))
                ->get()
                ->keyBy('email');

            // 3. Identify and Create Missing Users
            $usersToProcess = []; // To store final User objects
            $defaultPassword = \Illuminate\Support\Facades\Hash::make('Mitra3321'); // Hashed once!

            foreach ($validRows as $key => $vRow) {
                $email = $vRow['email'];
                if (!$usersByEmail->has($email)) {
                    if (empty($vRow['nama'])) {
                        $this->failed++;
                        unset($validRows[$key]);
                        continue;
                    }

                    // Create new user
                    $newUser = User::create([
                        'name' => ucwords(strtolower($vRow['nama'])),
                        'email' => $email,
                        'password' => $defaultPassword,
                    ]);
                    $newUser->assignRole('Mitra');
                    $usersByEmail->put($email, $newUser);
                }
                
                $usersToProcess[$email] = $usersByEmail->get($email);
            }

            if (empty($validRows)) {
                return;
            }

            $userIds = collect($usersToProcess)->pluck('id')->toArray();

            // 4. Bulk Fetch Survey Participants & Existing Surat Tugas
            $existingParticipants = \App\Models\SurveyUser::where('survey_id', $this->surveyId)
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->flip(); // [user_id => index] for fast isset lookup

            $existingSuratTugas = SuratTugas::where('survey_id', $this->surveyId)
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->flip();

            // 5. Build Bulk Inserts
            $surveyUserInserts = [];
            $suratTugasInserts = [];
            $now = now()->toDateTimeString();

            foreach ($validRows as $vRow) {
                $email = $vRow['email'];
                $user = $usersToProcess[$email] ?? null;

                if (!$user) {
                    continue;
                }

                // Check if already has Surat Tugas
                if (isset($existingSuratTugas[$user->id])) {
                    $this->skipped++;
                    continue;
                }

                // Check if needs SurveyUser record
                if (!isset($existingParticipants[$user->id])) {
                    $surveyUserInserts[] = [
                        'user_id' => $user->id,
                        'survey_id' => $this->surveyId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    // Prevent duplicate participant creation if same email appears twice in Excel
                    $existingParticipants[$user->id] = true; 
                }

                // Advance the nomor_urut safely
                $currentUrut++;
                while (isset($usedNumbers[$currentUrut])) {
                    $currentUrut++;
                }
                $usedNumbers[$currentUrut] = true;

                $urut = str_pad($currentUrut, 4, '0', STR_PAD_LEFT);
                $nomorSurat = "{$prefix}-{$urut}/{$office}/{$this->kodeKlasifikasi}/{$this->year}";

                $suratTugasInserts[] = [
                    'user_id' => $user->id,
                    'survey_id' => $this->surveyId,
                    'nomor_surat' => $nomorSurat,
                    'nomor_urut' => $currentUrut,
                    'kode_klasifikasi' => $this->kodeKlasifikasi,
                    'jabatan' => $vRow['jabatan'],
                    'keperluan' => $this->keperluan,
                    'tempat_tugas' => $vRow['tempat_tugas'],
                    'tanggal' => $this->tanggal,
                    'waktu_mulai' => $this->waktuMulai,
                    'waktu_selesai' => $this->waktuSelesai,
                    'signer_city' => $this->settings->cert_city,
                    'signer_name' => $this->settings->cert_signer_name,
                    'signer_nip' => $this->settings->cert_signer_nip,
                    'signer_title' => $this->settings->cert_signer_title,
                    'signer_signature_path' => $this->settings->cert_signer_signature_path,
                    'created_by' => $creatorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Mark this user as having Surat Tugas to prevent duplicates from within the Excel file itself
                $existingSuratTugas[$user->id] = true;
                $this->success++;
            }

            // 6. Execute Bulk Inserts
            if (!empty($surveyUserInserts)) {
                // chunking inserts just in case of hundreds of rows
                foreach (array_chunk($surveyUserInserts, 500) as $chunk) {
                    \App\Models\SurveyUser::insert($chunk);
                }
            }

            if (!empty($suratTugasInserts)) {
                foreach (array_chunk($suratTugasInserts, 500) as $chunk) {
                    SuratTugas::insert($chunk);
                }
            }
        });
    }
}
