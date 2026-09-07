<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SyncMitraToSipetra extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sipetra:sync-mitra
                            {--dry-run : Preview missing mitras without inserting into Sipetra}
                            {--password=3321 : Default password for new mitra accounts in Sipetra}
                            {--survey= : Filter only mitras in surveys matching this name (e.g. SE2026)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync mitra accounts from Puslah into Sipetra database so they can log in via SIPETRA SSO';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $defaultPassword = (string) $this->option('password');
        $surveyFilter = $this->option('survey');

        $this->info('=====================================================');
        $this->info('  Puslah -> Sipetra Mitra Synchronization');
        $this->info('=====================================================');

        // Test connection to sipetra
        try {
            DB::connection('sipetra')->getPdo();
            $this->info('Successfully connected to Sipetra database.');
        } catch (\Exception $e) {
            $this->error('Failed to connect to Sipetra database: ' . $e->getMessage());
            return self::FAILURE;
        }

        // 1. Get Mitra role ID in Sipetra
        $mitraRole = DB::connection('sipetra')->table('roles')->where('name', 'mitra')->first();
        $mitraRoleId = $mitraRole ? $mitraRole->id : 7;
        $this->line("Sipetra 'mitra' Role ID: <comment>{$mitraRoleId}</comment>");

        // 2. Fetch existing emails from Sipetra
        $sipetraEmails = DB::connection('sipetra')->table('users')
            ->pluck('email')
            ->map(fn($e) => strtolower(trim((string) $e)))
            ->flip()
            ->toArray();

        $this->line('Existing users in Sipetra: <comment>' . count($sipetraEmails) . '</comment>');

        // 3. Find candidate users in Puslah
        $query = DB::table('users');

        if (!empty($surveyFilter)) {
            $surveyIds = DB::table('surveys')
                ->where('name', 'LIKE', "%{$surveyFilter}%")
                ->pluck('id');

            if ($surveyIds->isEmpty()) {
                $this->warn("No surveys found matching filter: '{$surveyFilter}'");
                return self::FAILURE;
            }

            $surveyUserIds = DB::table('survey_users')->whereIn('survey_id', $surveyIds)->pluck('user_id');
            $stUserIds = DB::table('surat_tugas')->whereIn('survey_id', $surveyIds)->pluck('user_id');
            $candidateUserIds = $surveyUserIds->merge($stUserIds)->unique()->filter();

            $query->whereIn('id', $candidateUserIds);
            $this->line("Filtering by survey '{$surveyFilter}': found <comment>" . $candidateUserIds->count() . "</comment> candidate users.");
        }

        $puslahUsers = $query->select('id', 'name', 'email')->get();

        $excludedEmails = ['terlampir@gmail.com', 'admin@admin.com', 'admin@gmail.com'];
        $excludedNames = ['terlampir', 'super admin'];

        $toInsert = [];
        $skippedCount = 0;
        $seen = [];

        foreach ($puslahUsers as $user) {
            $email = strtolower(trim((string) ($user->email ?? '')));
            $name = trim((string) ($user->name ?? ''));

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedCount++;
                continue;
            }

            if (in_array($email, $excludedEmails) || in_array(strtolower($name), $excludedNames)) {
                $skippedCount++;
                continue;
            }

            // Exclude organic pegawai
            if (str_ends_with($email, '@bps.go.id')) {
                $skippedCount++;
                continue;
            }

            // Skip if already in Sipetra
            if (isset($sipetraEmails[$email])) {
                $skippedCount++;
                continue;
            }

            // Deduplicate across puslah
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            // Fetch profile data
            $profile = DB::table('user_profiles')
                ->where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->first();

            $phone = null;
            if (!empty($profile?->phone)) {
                $rawPhone = preg_replace('/[^0-9+]/', '', trim((string) $profile->phone));
                $phone = substr($rawPhone, 0, 20);
            }

            $toInsert[] = [
                'puslah_id'     => $user->id,
                'name'          => $name,
                'email'         => $email,
                'identity_type' => 'mitra',
                'kd_satker'     => '3321',
                'jenis_kelamin' => !empty($profile?->gender) ? strtoupper(substr(trim($profile->gender), 0, 2)) : null,
                'tempat_lahir'  => !empty($profile?->birth_place) ? trim((string) $profile->birth_place) : null,
                'tanggal_lahir' => !empty($profile?->birth_date) ? $profile->birth_date : null,
                'phone'         => $phone,
                'is_active'     => 1,
            ];
        }

        $totalToInsert = count($toInsert);
        $this->info("Found <comment>{$totalToInsert}</comment> Mitra account(s) missing in Sipetra (skipped: {$skippedCount}).");

        if ($totalToInsert === 0) {
            $this->info('All mitra accounts are already synchronized with Sipetra.');
            return self::SUCCESS;
        }

        // Preview top 5
        $this->line("\nPreview of mitras to insert (first 5):");
        $tableHeaders = ['Puslah ID', 'Name', 'Email', 'Gender', 'Phone'];
        $tableRows = array_map(function ($m) {
            return [
                $m['puslah_id'],
                $m['name'],
                $m['email'],
                $m['jenis_kelamin'] ?? '-',
                $m['phone'] ?? '-',
            ];
        }, array_slice($toInsert, 0, 5));
        $this->table($tableHeaders, $tableRows);

        if ($isDryRun) {
            $this->warn("\n[DRY RUN] No records were inserted into Sipetra.");
            return self::SUCCESS;
        }

        $this->info("\nStarting insertion of {$totalToInsert} records into Sipetra...");
        $bar = $this->output->createProgressBar($totalToInsert);
        $bar->start();

        $hashedPassword = Hash::make($defaultPassword);
        $now = now();
        $insertedCount = 0;

        DB::connection('sipetra')->beginTransaction();
        try {
            foreach ($toInsert as $item) {
                $newUserId = DB::connection('sipetra')->table('users')->insertGetId([
                    'name'          => $item['name'],
                    'email'         => $item['email'],
                    'identity_type' => $item['identity_type'],
                    'kd_satker'     => $item['kd_satker'],
                    'sobat_id'      => null,
                    'jenis_kelamin' => $item['jenis_kelamin'],
                    'tempat_lahir'  => $item['tempat_lahir'],
                    'tanggal_lahir' => $item['tanggal_lahir'],
                    'phone'         => $item['phone'],
                    'is_active'     => $item['is_active'],
                    'password'      => $hashedPassword,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                DB::connection('sipetra')->table('model_has_roles')->insert([
                    'role_id'    => $mitraRoleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id'   => $newUserId,
                ]);

                $insertedCount++;
                $bar->advance();
            }

            DB::connection('sipetra')->commit();
            $bar->finish();
            $this->newLine(2);

            $this->info("Successfully inserted <comment>{$insertedCount}</comment> mitra accounts into Sipetra!");
            $this->line("Default credentials: Email: <comment>[email mitra]</comment> | Password: <comment>{$defaultPassword}</comment>");
            $this->line("Users can now log in via SIPETRA SSO at: <info>https://bpsdemak.com/login</info> or directly from Puslah.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::connection('sipetra')->rollBack();
            $this->newLine();
            $this->error('Failed during insertion: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
