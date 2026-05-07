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

    // Efisiensi query: hanya user dengan notifikasi aktif
    $users = TelegramUser::whereRaw('JSON_EXTRACT(data, "$.notifications_prayer_enabled") = true')->get();

    if ($users->isEmpty()) {
      $this->info('Tidak ada user dengan notifikasi aktif.');
      return 0;
    }

    $sentCount = 0;
    foreach ($users as $user) {
      try {
        $data = $user->data ?? [];
        $defaultLocation = $data['default_location'] ?? [];

        if (empty($defaultLocation)) {
          $this->warn("User {$user->telegram_id} tidak menyimpan lokasi default.");
          continue;
        }

        $prayerData = $this->prayerService->getTodayPrayerByLocation($defaultLocation);
        if (!$prayerData) {
          $this->warn("User {$user->telegram_id}: jadwal tidak ditemukan.");
          continue;
        }

        if (!isset($data['notifications_prayer_sent'])) {
          $data['notifications_prayer_sent'] = [];
        }

        $timezone = $prayerData['timezone'] ?? 'Asia/Jakarta';
        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $isRamadhan = $now->toHijri()->month === 9;

        $sentToday = $data['notifications_prayer_sent'][$today] ?? [];
        $updated = false;

        foreach ($prayerData['jadwal'] as $name => $timeStr) {
          if ($name === 'imsak' && !$isRamadhan) continue;

          $prayerTime = Carbon::today($timezone)->setTimeFromTimeString($timeStr);

          // Kirim tepat waktu (setelah atau saat adzan, max 1 menit setelah)
          if ($now->gte($prayerTime) && $now->diffInMinutes($prayerTime) <= 1 && !in_array($name, $sentToday)) {
            $user->notify(new PrayerSent(
              city: $prayerData['city'],
              name: $name,
              time: $timeStr
            ));

            $sentToday[] = $name;
            $updated = true;
            $this->info("Notifikasi {$name} terkirim ke {$user->telegram_id}");
            $sentCount++;
          }
        }

        if ($updated) {
          $data['notifications_prayer_sent'][$today] = $sentToday;
          // Hapus data sent lebih dari 7 hari
          $cutoff = Carbon::now()->subDays(7);
          $data['notifications_prayer_sent'] = collect($data['notifications_prayer_sent'])
          ->filter(fn($sent, $date) => Carbon::parse($date)->gte($cutoff))
          ->toArray();
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