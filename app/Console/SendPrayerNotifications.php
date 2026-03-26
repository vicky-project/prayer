<?php
namespace Modules\Prayer\Console;

use Illuminate\Console\Command;
use Modules\Prayer\Notifications\PrayerSent;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Models\TelegramUser;
use Carbon\Carbon;

class SendPrayerNotifications extends Command
{
  protected $signature = 'app:prayer-sent';
  protected $description = 'Kirim notifikasi waktu shalat ke pengguna Telegram yang mengaktifkan';

  protected PrayerTimeService $prayerService;

  public function __construct(
    PrayerTimeService $prayerService
  ) {
    parent::__construct();
    $this->prayerService = $prayerService;
  }

  public function handle() {
    $this->info('Memulai pengiriman notifikasi shalat...');

    $users = TelegramUser::all()->filter(function (TelegramUser $user) {
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
      try {
        $data = $user->data ?? [];
        $defaultLocation = $data['default_location'] ?? [];

        if (empty($defaultLocation)) {
          $this->warn("User tidak menyimpan lokasi default.");
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
          $this->info("Membuat inisialisasi array notifikasi.");
        }

        // Gunakan timezone kota untuk menentukan waktu sekarang
        $timezone = $prayerData['timezone'] ?? 'Asia/Jakarta';
        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $currentTime = $now->format('H:i');

        // Inisialisasi notifikasi yang sudah dikirim hari ini
        $sentToday = $data['notifications_prayer_sent'][$today] ?? [];

        foreach ($prayerData['jadwal'] as $name => $timeStr) {
          // Buat waktu shalat hari ini
          $prayerTime = Carbon::today($timezone)->setTimeFromTimeString($timeStr);
          $diffMinutes = abs($now->diffInMinutes($prayerTime));

          if ($diffMinutes <= 1 && !in_array($name, $sentToday)) {
            \Log::debug("Found matches prayer: ". $name, [
              "user" => $user->telegram_id,
              "diff" => $diffMinutes
            ]);

            $user->notify(new PrayerSent(city: $prayerData["city"], name: $name, time: $timeStr));


            $sentToday[] = $name;
            $data['notifications_prayer_sent'][$today] = $sentToday;
            $user->data = $data;
            $user->save();

            $this->info("Notifikasi {$name} terkirim ke {$user->telegram_id}");
            \Log::info("Notification sent: ", ["name" => $name, "telegram_id" => $user->telegram_id]);
            $sentCount++;
          }
        }
      } catch(\Exception $e) {
        \Log::warning("Failed to sent prayer notifications", [
          "user" => $user->telegram_id,
          "message" => $e->getMessage(),
          "trace" => $e->getTraceAsString()
        ]);
      }
    }

    $this->info("Selesai. {$sentCount} notifikasi terkirim.");
    return 0;
  }
}