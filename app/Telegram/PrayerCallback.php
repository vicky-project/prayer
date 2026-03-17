<?php
namespace Modules\Prayer\Telegram;

use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Services\Support\TelegramApi;
use Modules\Telegram\Services\Handlers\Callbacks\BaseCallbackHandler;

class PrayerCallback extends BaseCallbackHandler
{
  protected PrayerTimeService $prayerService;

  public function __construct(
    TelegramApi $telegramApi,
    PrayerTimeService $prayerService,
  ) {
    parent::__construct($telegramApi);
    $this->prayerService = $prayerService;
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
          $this->getAllProvince();
          return [];

        case "location":
          Log::debug("location", ["data" => $data, "context" => $context]);
          return [
            "send_message" => [
              "text" => "Share your location",
              "reply_markup" => Keyboard::make()->setOneTimeKeyboard(true)->row([Keyboard::button()->setRequestLocation(true)])
            ]
          ];
      }
    } catch(\Exception $e) {
      throw $e;
    }
  }

  private function getAllProvince() {
    $provinces = $this->prayerService->getProvinces();
    Log::debug("All provinces", $provinces);
  }
}