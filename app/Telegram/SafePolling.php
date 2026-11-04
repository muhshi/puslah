<?php

namespace App\Telegram;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\RunningMode;
use SergiX44\Nutgram\Telegram\Types\Common\Update;
use Throwable;

class SafePolling implements RunningMode
{
    public static bool $FOREVER = true;
    public static mixed $STDERR = null;

    protected const OFFSET_CACHE_KEY = 'nutgram_safe_polling_offset';

    public function __construct()
    {
        if (!(\PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg')) {
            throw new RuntimeException('This mode can be only invoked via cli.');
        }
    }

    public function processUpdates(Nutgram $bot): void
    {
        $this->listenForSignals();

        $config = $bot->getConfig();

        // Ambil offset terakhir yang tersimpan di cache Redis/File agar update tidak hilang saat container restart
        $cachedOffset = Cache::get(self::OFFSET_CACHE_KEY);
        $offset = is_numeric($cachedOffset) && (int) $cachedOffset > 0 ? (int) $cachedOffset : 1;

        print("🚀 Puslah SafePolling Bot Active! Listening on Telegram...\n");
        Log::info("Telegram SafePolling started with offset: {$offset}");

        $isInitialRun = ($offset === 1);

        while (self::$FOREVER) {
            $updates = null;

            try {
                // Polling timeout 10 detik agar tidak menabrak batas Guzzle client timeout (30-45s)
                $updates = $bot->getUpdates(
                    offset: $offset,
                    limit: $config->pollingLimit ?: 100,
                    timeout: min($config->pollingTimeout ?: 10, 10),
                    allowed_updates: $config->pollingAllowedUpdates
                );
            } catch (ConnectException $e) {
                // cURL error 28 / timeout normal saat long-polling tidak ada pesan masuk
                // Lanjutkan loop tanpa mencatat error berat dan tanpa mematikan bot
                continue;
            } catch (RequestException $e) {
                // Error HTTP dari Telegram (misal 502 Bad Gateway atau 429 Too Many Requests)
                $statusCode = $e->getResponse()?->getStatusCode() ?: 0;
                print("⚠️ Telegram HTTP {$statusCode}: " . $e->getMessage() . "\n");
                Log::warning("Telegram SafePolling HTTP Error [{$statusCode}]: " . $e->getMessage());
                sleep(2);
                continue;
            } catch (GuzzleException $e) {
                print("⚠️ Guzzle network glitch: " . $e->getMessage() . "\n");
                Log::warning("Telegram SafePolling network glitch: " . $e->getMessage());
                sleep(2);
                continue;
            } catch (Throwable $e) {
                print("❌ Unexpected polling error: " . $e->getMessage() . "\n");
                Log::error("Telegram SafePolling unexpected error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                sleep(3);
                continue;
            }

            if (empty($updates)) {
                continue;
            }

            // Jika bot baru pertama kali jalan dan offset masih 1, kita proses seluruh batch tanpa membuangnya
            // Update offset ke ID update terakhir + 1
            /** @var Update $last */
            $last = end($updates);
            if ($last) {
                $nextOffset = $last->update_id + 1;
                $offset = $nextOffset;
                Cache::put(self::OFFSET_CACHE_KEY, $nextOffset, now()->addDays(7));
            }

            // Eksekusi setiap update yang diterima
            $this->fire($bot, $updates);
        }

        print("👋 Telegram SafePolling Stopped Gracefully.\n");
    }

    protected function listenForSignals(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            $killMe = static function () {
                exit("\nGoodbye!\n");
            };

            $exitGracefully = static function () {
                self::$FOREVER = false;
            };

            pcntl_signal(SIGINT, $killMe);
            pcntl_signal(SIGQUIT, $killMe);
            pcntl_signal(SIGTERM, $exitGracefully);
        }
    }

    /**
     * @param Nutgram $bot
     * @param Update[] $updates
     * @return void
     */
    protected function fire(Nutgram $bot, array $updates = []): void
    {
        foreach ($updates as $update) {
            try {
                $bot->processUpdate($update);
            } catch (Throwable $e) {
                $err = "Telegram update processing error: " . $e->getMessage();
                fwrite(self::$STDERR ?? STDERR, "{$err}\n");
                Log::error($err, [
                    'update_id' => $update->update_id ?? null,
                    'trace' => $e->getTraceAsString(),
                ]);
            } finally {
                $bot->clear();
            }
        }
    }
}
