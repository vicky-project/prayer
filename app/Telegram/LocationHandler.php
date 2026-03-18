<?php
namespace Modules\Prayer\Telegram;

use Illuminate\Support\Facades\Log;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Services\Handlers\BaseLocationHandler;
use Modules\Telegram\Services\Support\Cache\CacheReplyStateManager;
use Modules\Telegram\Services\Support\TelegramApi;

class LocationHandler extends BaseLocationHandler
{
  protected PrayerTimeService $prayerService;

  public function __construct(
    TelegramApi $telegram,
    PrayerTimeService $prayerService
  ) {
    parent::__construct($telegram);
    $this->prayerService = $prayerService;
  }

  public function getName(): string
  {
    return "prayer_location";
  }

  protected function processLocation(
    int $chatId,
    float $latitude,
    float $longitude,
    ?string $username = null,
    array $context = []
  ): array {
    try {
      $prayer = $this->prayerService->getPrayerTimes($latitude, $longitude);

      // Informasi kota dan tanggal
      $message = "*{$prayer['city']}*\n";
      $message .= "📆 {$prayer['date']}\n";
      $message .= "📍 {$prayer['latitude']},{$prayer['longitude']}\n\n";

      // Data waktu shalat
      $rows = [
        'Imsak' => $prayer["jadwal"]["imsak"],
        'Subuh' => $prayer["jadwal"]["subuh"],
        'Terbit' => $prayer["jadwal"]["terbit"],
        'Dhuha' => $prayer["jadwal"]["dhuha"],
        'Dzuhur' => $prayer["jadwal"]["dzuhur"],
        'Ashar' => $prayer["jadwal"]["ashar"],
        'Maghrib' => $prayer["jadwal"]["maghrib"],
        'Isya' => $prayer["jadwal"]["isya"],
      ];

      // Bangun tabel dengan box‑drawing characters (dalam code block)
      $table = "```\n"; // mulai code block
      $table .= "┌──────────┬───────┐\n";
      $table .= "│ Waktu    │ Jam   │\n";
      $table .= "├──────────┼───────┤\n";

      foreach ($rows as $waktu => $jam) {
        // lebar kolom pertama 8 karakter, kedua 5 karakter
        $table .= sprintf("│ %-8s │ %-5s │\n", $waktu, $jam);
      }

      $table .= "└──────────┴───────┘\n";
      $table .= "```"; // tutup code block

      $message .= $table;
      $this->sendMessage($chatId, $message, null, "MarkdownV2");

      return [
        "status" => "prayer_sent",
        "message" => "Prayer times sent",
        "answer" => "Prayer times sent"
      ];
    } catch(\Exception $e) {
      Log::error("Failed to process location message.", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString(),
      ]);
      $this->sendMessage($chatId, $e->getMessage(), null, "MarkdownV2")

      return [
        "status" => "locationprayer_fail",
        "answer" => "Failed reply location for prayer."
      ];
    }
  }
}