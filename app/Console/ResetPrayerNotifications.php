<?php
namespace Modules\Prayer\Console;

use Illuminate\Console\Command;
use Modules\Telegram\Models\TelegramUser;
use Carbon\Carbon;

class ResetPrayerNotifications extends Command
{
  protected $signature = 'app:prayer-reset';
  protected $description = 'Hapus catatan notifikasi yang sudah lewat';

  public function handle() {
    $this->info('Memulai reset notifikasi...');

    $users = TelegramUser::all();
    $today = Carbon::today();
    $today->tz = config("prayer.timezone");
    $today = $today->toDateString();
    $deletedCount = 0;

    foreach ($users as $user) {
      $data = $user->data ?? [];
      if (!isset($data['notifications_sent'])) {
        continue;
      }

      $notifications = $data['notifications_sent'];
      $originalCount = count($notifications);

      // Hapus entri dengan tanggal sebelum hari ini
      $notifications = array_filter($notifications, function ($date) use ($today) {
        return $date >= $today; // pertahankan hari ini dan masa depan (kalau ada)
      }, ARRAY_FILTER_USE_KEY);

      if (count($notifications) !== $originalCount) {
        $data['notifications_sent'] = $notifications;
        $user->data = $data;
        $user->save();
        $deletedCount++;
      }
    }

    $this->info("Reset selesai. {$deletedCount} user dibersihkan.");
    \Log::info("Reset user notifications with total {$deletedCount}");
    return 0;
  }
}