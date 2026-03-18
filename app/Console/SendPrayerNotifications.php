<?php
namespace Modules\Prayer\Console;

use Illuminate\Console\Command;
use Modules\Telegram\Models\TelegramUser;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Services\Support\TelegramApi;
use Carbon\Carbon;

class SendPrayerNotifications extends Command
{
  protected $signature = 'app:prayer-sent';
  protected $description = 'Kirim notifikasi waktu shalat ke pengguna Telegram yang mengaktifkan';

  protected $prayerService;
  protected $telegramApi;

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
      return ($data['notifications_enabled'] ?? false) === true;
    });

    if ($users->isEmpty()) {
      $this->info('Tidak ada user dengan notifikasi aktif.');
      return 0;
    }

    $now = Carbon::now();
    $now->tz = config("prayer.timezone");
    $today = $now->toDateString(); // format Y-m-d
    $currentTime = $now->format('H:i');

    foreach ($users as $user) {
      $data = $user->data ?? [];
      $defaultLocation = $data['default_location'] ?? [];

      if (empty($defaultLocation)) {
        continue;
      }

      $prayerData = $this->prayerService->getTodayPrayerByLocation($defaultLocation);
      if (!$prayerData) {
        $this->warn("User {$user->telegram_id}: jadwal tidak ditemukan.");
        continue;
      }

      // Inisialisasi array notifikasi yang sudah dikirim per tanggal
      if (!isset($data['notifications_sent'])) {
        $data['notifications_sent'] = [];
      }
      $sentToday = $data['notifications_sent'][$today] ?? [];

      foreach ($prayerData['jadwal'] as $name => $time) {
        // Lewati jika sudah dikirim hari ini
        if (in_array($name, $sentToday)) {
          continue;
        }

        // Cek kecocokan waktu (dengan toleransi 1 menit)
        if ($time === $currentTime) {
          $clearName = $this->translatePrayerName($name);
          $message = "🕌 <b>Waktu Shalat {$clearName}</b>\n";
          $message .= "📍 {$prayerData['city_name']}\n";
          $message .= "⏰ {$time} \n\n";
          $message .= "Semoga ibadah kita diterima Allah SWT.";

          $sent = $this->telegramApi->sendMessage($user->telegram_id, $message, null, "HTML");
          if ($sent) {
            // Catat pengiriman
            $sentToday[] = $name;
            $data['notifications_sent'][$today] = $sentToday;
            $user->data = $data;
            $user->save();

            $this->info("Notifikasi {$name} terkirim ke {$user->telegram_id}");
            \Log::info("Notification sent: ", ["name" => $name, "telegram_id" => $user->telegram_id]);
          }
        }
      }
    }

    $this->info('Selesai.');
    return 0;
  }

  private function translatePrayerName($name) {
    $map = [
      'imsak' => 'Imsak',
      'subuh' => 'Subuh',
      'terbit' => 'Terbit',
      'dhuha' => 'Dhuha',
      'dzuhur' => 'Dzuhur',
      'ashar' => 'Ashar',
      'maghrib' => 'Maghrib',
      'isya' => 'Isya',
    ];
    return $map[$name] ?? $name;
  }
}