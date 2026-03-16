<?php
namespace Modules\Prayer\Telegram;

use Illuminate\Support\Facades\Log;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Services\Handlers\Commands\BaseCommandHandler;
use Modules\Telegram\Services\Support\InlineKeyboardBuilder;
use Modules\Telegram\Services\Support\TelegramApi;

class PrayerCommand extends BaseCommandHandler
{
  protected PrayerTimeService $prayerService;
  protected InlineKeyboardBuilder $inlineKeyboard;

  public function __construct(
    TelegramApi $telegram,
    PrayerTimeService $prayerService,
    InlineKeyboardBuilder $inlineKeyboard,
  ) {
    parent::__construct($telegram);
    $this->prayerService = $prayerService;
    $this->inlineKeyboard = $inlineKeyboard;
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
      $keyboard = $this->prepareKeyboard();
      return [
        "status" => "prayertimes_sent",
        "send_message" => [
          "text" => "Pilih lokasi atau bagikan lokasi anda",
          "parse_mode" => "MarkdownV2",
          "reply_markup" => $keyboard
        ]
      ];
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

  private function prepareKeyboard(): array
  {
    $this->inlineKeyboard->setModule("prayer")->setEntity("prayer");

    return $this->inlineKeyboard->replyKeyboardGrid(
      [
        [
          "text" => "All provinces",
          "callback_data" =>
          [
            "value" => "provinces",
            "action" => "provinces"
          ]
        ],
        [
          "text" => "ShareLocation",
          "callback_data" =>
          [
            "value" => "location",
            "action" => "location"
          ]
        ]
      ], 2);
  }
}