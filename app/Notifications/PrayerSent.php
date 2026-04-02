<?php

namespace Modules\Prayer\Notifications;

use Illuminate\Notifications\Notification;

class PrayerSent extends Notification
{
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

    $displayName = $displayName === "imsak" ? "Imsak" : "Shalat ". ucfirst($displayName);

    $message = "🕌 *Waktu {$displayName} telah tiba*\n" .
    "📍 {$this->city}\n" .
    "⏰ {$this->time} \n\n" .
    "Semoga ibadah kita diterima Allah SWT.";

    return [
      "text" => $message,
      "parse_mode" => "MarkdownV2"
    ];
  }
}