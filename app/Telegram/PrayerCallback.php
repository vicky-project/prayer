<?php
namespace Modules\Prayer\Telegram;

use Illuminate\Support\Facades\Log;
use Modules\Telegram\Services\Support\TelegramApi;
use Modules\Telegram\Services\Handlers\Callbacks\BaseCallbackHandler;

class PrayerCallback extends BaseCallbackHandler
{
  public function __construct(
    TelegramApi $telegramApi,
  ) {
    parent::__construct($telegramApi);
  }

  public function getModuleName(): string
  {
    return "prayer";
  }

  public function getName(): string
  {
    return "prayer callback handler";
  }

  public function handle(array $data, array $context): array {
    try {
      return $this->handleCallbackWithAutoAnswer(
        $context,
        $data,
        fn($data, $context) => $this->processCallback($data, $context),
      );
    } catch (\Exception $e) {
      Log::error("Failed to handle callback of prayer", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString(),
      ]);

      return ["status" => "callback_failed",
        "answer" => $e->getMessage()];
    }
  }

  private function processCallback(array $data, array $context): array {
    try {
      $entity = $data["entity"];
      $action = $data["action"];

      switch ($action) {
        case "provinces":
          \Log::debug("province callback", ["data" => $data, "context" => $context]);
          break;
        case "location":
          \Log::debug("location", ["data" => $data, "context" => $context]);
          break;
      }
    } catch(\Exception $e) {
      throw $e;
    }
  }
}