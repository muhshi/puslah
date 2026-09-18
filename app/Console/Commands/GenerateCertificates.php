<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateCertificates extends Command
{
    /**
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-certificates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate certificates for completed Surat Tugas automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $this->info("Starting certificate generation check at {$now}...");

        // 1. Find eligible SurveyUsers (Participants)
        // - Survey is inactive (ended)
        // - Status is not approved
        // - User name is NOT 'terlampir' (case-insensitive)
        $candidates = \App\Models\SurveyUser::with(['user', 'survey'])
            ->where('status', '!=', 'approved')
            ->whereHas('survey', function ($query) {
                $query->where('is_active', false);
            })
            ->whereHas('user', function ($query) {
                $query->whereRaw('LOWER(name) NOT LIKE ?', ['%terlampir%']);
            })
            ->get();

        $this->info("Found {$candidates->count()} candidate participants from inactive surveys.");
        $generatedCount = 0;

        foreach ($candidates as $participant) {
            try {
                // Ensure User and Survey exist
                if (!$participant->user || !$participant->survey) {
                    continue;
                }

                // Check if certificate already exists
                $exists = \App\Models\Certificate::where('survey_id', $participant->survey_id)
                    ->where('user_id', $participant->user_id)
                    ->exists();

                if ($exists) {
                    $participant->update(['status' => 'approved']);
                    continue;
                }

                // Check active template
                $template = \App\Models\CertificateTemplate::where('active', 1)->first();
                if (!$template) {
                    $this->error("No active certificate template found!");
                    return;
                }

                $this->info("Generating certificate for: {$participant->user->name} (Survey: {$participant->survey->name})");
                app(\App\Services\CertificateIssuanceService::class)->issue($participant);
                $generatedCount++;

                // Also update SurveyUser status to approved
                $participant->update(['status' => 'approved']);

            } catch (\Exception $e) {
                $this->error("Failed to generate for Participant ID {$participant->id}: " . $e->getMessage());
            }
        }

        $this->info("Done! Generated {$generatedCount} new certificates.");
    }
}
