<?php
namespace Modules\Prayer\Telegram;

use Illuminate\Support\Facades\Log;
use Modules\Telegram\Services\Handlers\Replies\BaseReplyHandler;
use Modules\Telegram\Services\Support\CacheReplyStateManager;
use Modules\Telegram\Services\Support\TelegramApi;

class ReplyLocation extends BaseReplyHandler
{
  public function __construct(TelegramApi $telegram) {
    parent::__construct($telegram);
  }

  public function getModuleName(): string
  {
    return "prayer";
  }

  public function getEntity(): string
  {
    return "prayer";
  }

  public function getAction(): string
  {
    return "location";
  }

  public function handle(
    array $context,
    string $replyText,
    int $chatId,
    ?int $replyToMessageId
  ): array {
    try {
      if (
        !$this->ensureReplyToMessageIdExists(
          $chatId,
          $replyToMessageId,
          $context
        )
      ) {
        return ["status" => "missing_message_id"];
      }

      $state = CacheReplyStateManager::getReplyState(
        $chatId,
        $replyToMessageId
      );

      if (!$state) {
        $this->noticeUser($chatId, $context);
        return ["status" => "state_not_found"];
      }

      Log::debug("Reoly context location", $context);
    } catch(\Exception $e) {
      Log::error("Failed to process reply message.", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString(),
      ]);

      return [
        "status" => "replyprayer_fail",
        "answer" => "Failed reply location for prayer."
      ];
    }
  }

  private function ensureReplyToMessageIdExists(
    int $chatId,
    ?int $replyToMessageId,
    ?array $context = []
  ): bool {
    if (!$replyToMessageId) {
      Log::error("Missing message ID.", [
        "chat_id" => $chatId,
        "message_id" => $replyToMessageId,
      ]);

      $this->noticeUser($chatId, $context);

      return false;
    }

    return true;
  }

  private function noticeUser(int $chatId, array $context = []): void
  {
    $message = "Missing message ID or the message was expired.";
    $callbackQueryId = $context["callback_id"] ?? null;
    if ($callbackQueryId) {
      $this->answerCallbackQuery($callbackQueryId, $message, false);
    } else {
      $this->sendMessage($chatId, $message);
    }
  }
}