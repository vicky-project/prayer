<?php
namespace Modules\Prayer\Telegram;

use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Services\Support\InlineKeyboardBuilder;
use Modules\Telegram\Services\Support\TelegramApi;
use Modules\Telegram\Services\Handlers\Callbacks\BaseCallbackHandler;
use Modules\Telegram\Services\Support\CacheReplyStateManager;

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
      $id = $data["id"] ?? null;
      $messageId = $context["callback_query"]["message"]["message_id"];
      $chatId = $context["callback_query"]["message"]["chat"]["id"];

      switch ($action) {
        case "provinces":
          return $this->getAllProvince();

        case "cities":
          if (!$id) {
            throw new \Exception("ID not provided in callback query");
          }

          return $this->getCitiesByProvinceId($id);

        case "prayer":
          if (!$id) {
            throw new \Exception("ID not provided in callback query");
          }
          return $this->getPrayerByCityId($id);

        case "location":
          CacheReplyStateManager::expectReply($chatId, $messageId, "global:prayer:location", $context);
          return [
            "status" => "location requested",
            "send_message" => [
              "text" => "Share your location",
              "reply_markup" => $this->inlineKeyboard->replyKeyboardGrid([
                ["text" => "Location", "request_location" => true]
              ])
            ]
          ];
        default:
          Log::warn("No action found ij prayer callback");
          return [
            "status" => "No action found."
          ];
      }
    } catch(\Exception $e) {
      throw $e;
    }
  }

  private function getAllProvince(): array
  {
    $provinces = $this->prayerService->getProvinces();
    $buttons = $provinces->map(function($province) {
      return [
        "text" => $province->province_name,
        "callback_data" => [
          "action" => "cities",
          "value" => $province->id
        ]
      ];
    })->toArray();

    $keyboard = $this->inlineKeyboard
    ->setModule("prayer")
    ->setEntity("prayer")
    ->grid($buttons, 2);

    return [
      "status" => "provinces_sent",
      "edit_message" => [
        "text" => "List of Provinces",
        "parse_mode" => "MarkdownV2",
        "reply_markup" => ["inline_keyboard" => $keyboard]]
    ];
  }

  private function getCitiesByProvinceId(int $id): array
  {
    $cities = $this->prayerService->getCitiesByProvinceId($id);
    $buttons = $cities->map(function($city) {
      return [
        "text" => $city->name,
        "callback_data" => [
          "action" => "prayer",
          "value" => $city->id
        ]
      ];
    })->toArray();

    $keyboard = $this->inlineKeyboard
    ->setModule("prayer")
    ->setEntity("prayer")
    ->grid($buttons, 2);

    return [
      "status" => "cities_sent",
      "edit_message" => [
        "text" => "List of Cities",
        "parse_mode" => "MarkdownV2",
        "reply_markup" => ["inline_keyboard" => $keyboard]]
    ];
  }

  private function getPrayerByCityId(int $id): array
  {
    $city = $this->prayerService->getCityById($id);
    $prayer = $this->prayerService->getPrayerTimes($city->latitude, $city->longitude, $city->name);

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

    return [
      "status" => "prayer_sent",
      "edit_message" => [
        "text" => $message,
        "parse_mode" => "MarkdownV2"
      ]
    ];
  }
}