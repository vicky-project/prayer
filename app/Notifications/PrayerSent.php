<?php

namespace Modules\Prayer\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PrayerSent extends Notification implements ShouldQueue
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
    protected string $city,
    protected string $name,
    protected string $time
  ) {}

  public function via($notifiable) {
    $stack = config("prayer.notifications.stack");

    return !is_string($stack) ? ["telegram"] : explode(",", trim($stack));
  }

  public function toTelegram($notifiable) {
    $names = [
      'imsak' => 'Imsak',
      'subuh' => 'Subuh',
      'terbit' => 'Terbit',
      'dhuha' => 'Dhuha',
      'dzuhur' => 'Dzuhur',
      'ashar' => 'Ashar',
      'maghrib' => 'Maghrib',
      'isya' => 'Isya',
    ];
    $displayName = $names[$this->name] ?? $this->name;

    $message = "🕌 *Waktu Shalat {$displayName}*\n" .
    "📍 {$this->city}\n" .
    "⏰ {$this->time} \n\n" .
    "Semoga ibadah kita diterima Allah SWT.";

    return [
      "text" => $message,
      "parse_mode" => "MarkdownV2"
    ];
  }
}