<?php

namespace Modules\Prayer\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PrayerSentFailed extends Notification implements ShouldQueue
{
  use Queueable;

  /**
  * The number of times the notification may be attempted.
  *
  * @var int
  */
  public $tries = 5;

  /**
  * The number of seconds the notification can run before timing out.
  *
  * @var int
  */
  public $timeout = 120;

  /**
  * The maximum number of unhandled exceptions to allow before failing.
  *
  * @var int
  */
  public $maxExceptions = 3;

  public function __construct(
    protected string $signature,
  ) {}

  public function via($notifiable) {
    $stack = config("prayer.notifications.stack");

    return !is_string($stack) ? ["telegram"] : explode(",", trim($stack));
  }

  public function toTelegram($notifiable) {
    $message = "⚠️ Failed to process command: ". $this->signature;

    return [
      "text" => $message,
      "parse_mode" => "MarkdownV2"
    ];
  }
}