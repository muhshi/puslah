<?php

namespace App\Console\Commands;

use App\Telegram\SafePolling;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\SingleUpdate;

class RunTelegramSafePollingCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Meng-override signature 'nutgram:run' agar container Docker puslah-telegram-bot
     * otomatis menggunakan engine SafePolling yang tahan crash/timeout.
     *
     * @var string
     */
    protected $signature = 'nutgram:run {--once} {--pollingTimeout=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Telegram bot in resilient SafePolling mode (crash-proof & persistent offset)';

    /**
     * Execute the console command.
     */
    public function handle(Nutgram $bot): int
    {
        if ($this->option('once')) {
            $bot->setRunningMode(SingleUpdate::class);
        } else {
            $bot->setRunningMode(new SafePolling());
        }

        $bot->run();

        return 0;
    }
}
