<?php
namespace Modules\Prayer\Console;

use Illuminate\Console\Command;
use Modules\Telegram\Models\TelegramUser;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Services\Support\TelegramApi;
use Modules\Telegram\Services\Support\TelegramMarkdownHelper;
use Carbon\Carbon;

class SendPrayerNotifications extends Command
{
  protected $signature = 'app:prayer-sent';
  protected $description = 'Kirim notifikasi waktu shalat ke pengguna Telegram yang mengaktifkan';

  protected PrayerTimeService $prayerService;
  protected TelegramApi $telegramApi;

  public function __construct(
    PrayerTimeService $prayerService,
    TelegramApi $telegramApi
  ) {
    parent::__construct();
    $this->prayerService = $prayerService;
    $this->telegramApi = $telegramApi;
  }

  public function handle() {
    $this->info('Memulai pengiriman notifikasi shalat...');

    $users = TelegramUser::all()->filter(function ($user) {
      $data = $user->data ?? [];
      return ($data['notifications_prayer_enabled'] ?? false) === true;
    });

    if ($users->isEmpty()) {
      $this->info('Tidak ada user dengan notifikasi aktif.');
      return 0;
    }

    $nowGlobal = Carbon::now();
    $sentCount = 0;

    foreach ($users as $user) {
      $data = $user->data ?? [];
      $defaultLocation = $data['default_location'] ?? [];

      if (empty($defaultLocation)) {
        continue;
      }

      $prayerData = $this->prayerService->getTodayPrayerByLocation($defaultLocation);
      if (!$prayerData) {
        $this->warn("User {$user->telegram_id}: jadwal tidak ditemukan.");
        \Log::warning("User {$user->telegram_id}: Jadwal tidak ditemukan.", ['location' => $defaultLocation]);
        continue;
      }

      // Inisialisasi array notifikasi yang sudah dikirim per tanggal
      if (!isset($data['notifications_prayer_sent'])) {
        $data['notifications_prayer_sent'] = [];
      }

      // Gunakan timezone kota untuk menentukan waktu sekarang
      $timezone = $prayerData['timezone'] ?? 'Asia/Jakarta';
      $now = Carbon::now($timezone);
      $today = $now->toDateString();
      $currentTime = $now->format('H:i');

      // Inisialisasi notifikasi yang sudah dikirim hari ini
      $sentToday = $data['notifications_prayer_sent'][$today] ?? [];

      foreach ($prayerData['jadwal'] as $name => $time) {
        // Lewati jika sudah dikirim hari ini
        if (in_array($name, $sentToday)) {
          continue;
        }

        // Cek kecocokan waktu (dengan toleransi 1 menit)
        if ($time >= $currentTime && $time <= $currentTime + now()->addMinutes(2)->format('H:i')) {
          $clearName = $this->translatePrayerName($name);
          $message = $this->formatNotificationMessage($prayerData["city_name"], $name, $time);

          $sent = $this->telegramApi->sendMessage($user->telegram_id, TelegramMarkdownHelper::safeText($message, "HTML"), "HTML");
          if ($sent) {
            // Catat pengiriman
            $sentToday[] = $name;
            $data['notifications_prayer_sent'][$today] = $sentToday;
            $user->data = $data;
            $user->save();

            $this->info("Notifikasi {$name} terkirim ke {$user->telegram_id}");
            \Log::info("Notification sent: ", ["name" => $name, "telegram_id" => $user->telegram_id]);
            $sentCount++;
          }
        }
      }
    }

    $this->info("Selesai. {$sentCount} notifikasi terkirim.");
    return 0;
  }

  protected function formatNotificationMessage($city, $prayerName, $time) {
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
    $displayName = $names[$prayerName] ?? $prayerName;

    return "🕌 *Waktu Shalat {$displayName}*\n" .
    "📍 {$city}\n" .
    "⏰ {$time} WIB\n\n" .
    "Semoga ibadah kita diterima Allah SWT.";
  }
}