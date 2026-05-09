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

  public function __construct(PrayerTimeService $prayerService) {
    parent::__construct();
    $this->prayerService = $prayerService;
  }

  public function handle() {
    $this->info('Memulai pengiriman notifikasi shalat...');

    $users = TelegramUser::whereRaw('JSON_EXTRACT(data, "$.prayer.notifications_enabled") = true')->get();

    if ($users->isEmpty()) {
      $this->info('Tidak ada user dengan notifikasi aktif.');
      return 0;
    }

    $sentCount = 0;
    foreach ($users as $user) {
      try {
        $data = $user->data ?? [];
        $prayer = $data['prayer'] ?? [];

        $defaultLocation = $prayer['default_location'] ?? [];

        if (empty($defaultLocation)) {
          $this->warn("User {$user->telegram_id} tidak menyimpan lokasi default.");
          continue;
        }

        $prayerData = $this->prayerService->getTodayPrayerByLocation($defaultLocation);
        if (!$prayerData) {
          $this->warn("User {$user->telegram_id}: jadwal tidak ditemukan untuk lokasi default.");
          continue;
        }

        if (!isset($prayer['notifications_sent'])) {
          $prayer['notifications_sent'] = [];
        }

        $timezone = $prayerData['timezone'] ?? 'Asia/Jakarta';
        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $isRamadhan = $now->toHijri()->month === 9;

        $sentToday = $prayer['notifications_sent'][$today] ?? [];
        $reminderMinutes = $prayer['reminder_minutes'] ?? 0;
        $updated = false;

        foreach ($prayerData['jadwal'] as $name => $timeStr) {
          if ($name === 'imsak' && !$isRamadhan) {
            continue;
          }

          $prayerTime = Carbon::today($timezone)->setTimeFromTimeString($timeStr);
          $notifyTime = $prayerTime->copy()->subMinutes($reminderMinutes);
          $diffMinutes = $now->diffInMinutes($notifyTime);
          $diffSeconds = $now->diffInSeconds($notifyTime);

          if ($now->gte($notifyTime) && $diffMinutes == 0 && $diffSeconds <= 60 && !in_array($name, $sentToday)) {
            // Deteksi apakah ini shalat dzuhur dan sekarang hari Jumat
            $isFriday = ($name === 'dzuhur' && $now->dayOfWeek === Carbon::FRIDAY);

            $user->notify(new PrayerSent(
              city: $prayerData['city'],
              name: $name,
              time: $timeStr,
              isFriday: $isFriday
            ));

            $sentToday[] = $name;
            $updated = true;
            $this->info("Notifikasi {$name} (reminder {$reminderMinutes} menit) terkirim ke {$user->telegram_id}");
            $sentCount++;
          }
        }

        if ($updated) {
          $prayer['notifications_sent'][$today] = $sentToday;
          $cutoff = Carbon::now()->subDays(7);
          $prayer['notifications_sent'] = collect($prayer['notifications_sent'])
          ->filter(fn($sent, $date) => Carbon::parse($date)->gte($cutoff))
          ->toArray();
          $data['prayer'] = $prayer;
          $user->data = $data;
          $user->save();
        }
      } catch (\Exception $e) {
        \Log::error("Gagal kirim notifikasi shalat", [
          'user' => $user->telegram_id,
          'message' => $e->getMessage()
        ]);
      }
    }

    $this->info("Selesai. {$sentCount} notifikasi terkirim.");
    return 0;
  }
}