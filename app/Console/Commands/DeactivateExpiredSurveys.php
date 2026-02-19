<?php

namespace App\Console\Commands;

use App\Models\Survey;
use Illuminate\Console\Command;

class DeactivateExpiredSurveys extends Command
{
    protected $signature = 'surveys:deactivate-expired';

    protected $description = 'Deactivate surveys whose end_date has passed';

    public function handle(): int
    {
        $count = Survey::where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->startOfDay())
            ->update(['is_active' => false]);

        $this->info("Deactivated {$count} expired survey(s).");

        return self::SUCCESS;
    }
}
