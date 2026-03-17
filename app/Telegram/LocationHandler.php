<?php
namespace Modules\Prayer\Telegram;

use Illuminate\Support\Facades\Log;
use Modules\Telegram\Services\Handlers\BaseLocationHandler;
use Modules\Telegram\Services\Support\CacheReplyStateManager;
use Modules\Telegram\Services\Support\TelegramApi;

class LocationHandler extends BaseLocationHandler
{
  public function __construct(TelegramApi $telegram) {
    parent::__construct($telegram);
  }

  public function getName(): string
  {
    return "prayer_location";
  }

  protected function handleLocation(
    int $chatId,
    float $latitude,
    float $longitude,
    ?string $username = null,
    array $context = []
  ): array {
    try {
      return [];
    } catch(\Exception $e) {
      Log::error("Failed to process location message.", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString(),
      ]);

      return [
        "status" => "locationprayer_fail",
        "answer" => "Failed reply location for prayer."
      ];
    }
  }
}