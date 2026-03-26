<?php

namespace Modules\Prayer\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PrayerSent extends Notification implements ShouldQueue
{
  use Queueable;

  public function __construct(
    protected string $city,
    protected string $name,
    protected string $time
  ) {
    $this->authenticationLog = $authenticationLog;
  }

  public function via($notifiable) {
    return $notifiable->notifyAuthenticationLogVia();
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
}.