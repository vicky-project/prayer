<?php
namespace Modules\Prayer\Telegram;

use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Services\Support\InlineKeyboardBuilder;
use Modules\Telegram\Services\Support\TelegramApi;
use Modules\Telegram\Services\Handlers\Callbacks\BaseCallbackHandler;

class PrayerCallback extends BaseCallbackHandler
{
  protected PrayerTimeService $prayerService;
  protected InlineKeyboardBuilder $inlineKeyboard;

  public function __construct(
    TelegramApi $telegramApi,
    PrayerTimeService $prayerService,
    InlineKeyboardBuilder $inlineKeyboard,
  ) {
    parent::__construct($telegramApi);
    $this->prayerService = $prayerService;
    $this->inlineKeyboard = $inlineKeyboard;
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
          return $this->getAllProvince();

        case "cities":
          Log::debug("Cities", ["context" => $context, "data" => $data]);
          return [];

        case "location":
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

  private function getAllProvince(): array
  {
    $provinces = $this->prayerService->getProvinces();

    $keyboard = $this->inlineKeyboard
    ->setModule("prayer")
    ->setEntity("prayer")
    ->grid($provinces->map(function($province) {
      return [
        "text" => $province->province_name,
        "callback_data" => [
          "action" => "cities",
          "value" => $province->city_id
        ]
      ];
  })->toArray(), 2);

  return [
    "status" => "provinces_sent",
    "edit_message" => [
      "text" => "All provinces",
      "parse_mode" => "MarkdownV2"
    ],
    "send_message" => [
      "text" => "List of Provinces",
      "parse_mode" => "MarkdownV2",
      "reply_markup" => ["inline_keyboard" => $keyboard]]
  ];
}
}