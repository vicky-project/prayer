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
    \Log::info("Command ResetPrayerNotifications started.");

    $users = TelegramUser::all();
    $today = Carbon::today()->toDateString();
    $deletedCount = 0;

    foreach ($users as $user) {
      try {
        $data = $user->data ?? [];
        // Ambil array prayer
        if (!isset($data['prayer'])) {
          continue;
        }
        $prayer = $data['prayer'];
        if (!isset($prayer['notifications_sent'])) {
          continue;
        }

        $notifications = $prayer['notifications_sent'];
        $originalCount = count($notifications);

        // Hapus entri dengan tanggal sebelum hari ini
        $notifications = array_filter($notifications, function ($date) use ($today) {
          return $date >= $today; // pertahankan hari ini dan masa depan (kalau ada)
        }, ARRAY_FILTER_USE_KEY);

        if (count($notifications) !== $originalCount) {
          $prayer['notifications_sent'] = $notifications;
          $data['prayer'] = $prayer;
          $user->data = $data;
          $user->save();
          $deletedCount++;
        }
      } catch (\Exception $e) {
        \Log::error("Failed to reset prayer notification records.", [
          "user" => $user->telegram_id,
          "message" => $e->getMessage(),
          "trace" => $e->getTraceAsString()
        ]);
        $this->error("Failed to reset prayer notification: " . $e->getMessage());
      }
    }

    $this->info("Reset selesai. {$deletedCount} user dibersihkan.");
    \Log::info("Command ResetPrayerNotifications finished.", [
      "deleted_count" => $deletedCount
    ]);
    return 0;
  }
}