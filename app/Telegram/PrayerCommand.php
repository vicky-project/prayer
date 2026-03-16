<?php
namespace Modules\Prayer\Telegram;

use Illuminate\Support\Facades\Log;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Services\Support\TelegramApi;
use Modules\Telegram\Services\Handlers\Commands\BaseCommandHandler;

class PrayerCommand extends BaseCommandHandler
{
  protected PrayerTimeService $prayerService;

  public function __construct(TelegramApi $telegram, PrayerTimeService $prayerService) {
    parent::__construct($telegram);
    $this->prayerService = $prayerService;
  }

  public function getName(): string
  {
    return "prayer";
  }

  public function getDescription(): string
  {
    return "Show prayer schedule time";
  }

  /*
	 * Handle command
	 */
  protected function processCommand(
    int $chatId,
    string $text,
    ?string $username = null,
    array $params = [],
  ): array {
    try {
      $provinces = $this->prayerService->getProvinces();
      Log::debug("All provinces", $provinces);
    } catch(\Exception $e) {
      Log::error("Failed to get prayer times", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString(),
      ]);

      return [
        "status" => "prayertimes_failed",
        "message" => $e->getMessage(),
        "send_message" => ["text" => $e->getMessage()],
      ];
    }
  }
}