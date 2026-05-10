<?php

namespace Modules\Prayer\Console;

use Illuminate\Console\Command;
use Modules\Prayer\Notifications\PrayerSent;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Telegram\Models\TelegramUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendPrayerNotifications extends Command
{
  protected $signature = 'app:prayer-sent';
  protected $description = 'Kirim notifikasi waktu shalat secara akurat ke pengguna Telegram';

  protected PrayerTimeService $prayerService;

  public function __construct(PrayerTimeService $prayerService) {
    parent::__construct();
    $this->prayerService = $prayerService;
  }

  public function handle() {
    $this->info('[' . now()->toDateTimeString() . '] Memulai pengiriman notifikasi...');

    // 1. Gunakan chunk untuk menangani ribuan user tanpa memakan RAM besar
    // 2. Gunakan sintaks '->' untuk filter JSON yang lebih clean dan aman
    TelegramUser::where('data->prayer->notifications_enabled', true)
    ->chunk(100, function ($users) {
      foreach ($users as $user) {
        $this->processUserNotification($user);
      }
    });

    $this->info('Proses selesai.');
    return 0;
  }

  private function processUserNotification(TelegramUser $user) {
    try {
      $data = $user->data ?? [];
      $prayer = $data['prayer'] ?? [];
      $defaultLocation = $prayer['default_location'] ?? [];

      if (empty($defaultLocation)) return;

      // Ambil jadwal shalat
      $prayerData = $this->prayerService->getTodayPrayerByLocation($defaultLocation);
      if (!$prayerData || !isset($prayerData['jadwal'])) return;

      $timezone = $prayerData['timezone'] ?? 'Asia/Jakarta';
      $now = Carbon::now($timezone);
      $today = $now->toDateString();

      // Inisialisasi data pengiriman hari ini
      $sentToday = $prayer['notifications_sent'][$today] ?? [];
      $reminderMinutes = (int) ($prayer['reminder_minutes'] ?? 0);
      $updated = false;

      foreach ($prayerData['jadwal'] as $name => $timeStr) {
        // Skip Imsak jika bukan Ramadhan
        if ($name === 'imsak' && $now->toHijri()->month !== 9) continue;

        // Parsing waktu shalat
        $prayerTime = Carbon::today($timezone)->setTimeFromTimeString($timeStr);
        $notifyTime = $prayerTime->copy()->subMinutes($reminderMinutes);

        /**
        * LOGIKA AKURASI:
        * 1. Waktu sekarang sudah melewati (gte) waktu notifikasi.
        * 2. Belum dikirim hari ini (in_array).
        * 3. Masih dalam jendela waktu yang wajar (misal: belum lewat 15 menit dari jadwal).
        *    Ini mencegah notifikasi "nyepam" jika server sempat down lalu nyala kembali.
        */
        if ($now->gte($notifyTime) &&
          $now->diffInMinutes($notifyTime) <= 5 &&
          !in_array($name, $sentToday)) {

          $isFriday = ($name === 'dzuhur' && $now->isFriday());

          $user->notify(new PrayerSent(
            city: $prayerData['city'],
            name: $name,
            time: $timeStr,
            isFriday: $isFriday
          ));

          $sentToday[] = $name;
          $updated = true;
          $this->info("✓ Notifikasi {$name} terkirim ke: {$user->telegram_id}");
        }
      }

      if ($updated) {
        $this->saveUserProgress($user, $data, $sentToday, $today);
      }

    } catch (\Exception $e) {
      Log::error("Gagal memproses user {$user->telegram_id}: " . $e->getMessage());
    }
  }

  private function saveUserProgress($user, $data, $sentToday, $today) {
    $prayer = $data['prayer'];
    $prayer['notifications_sent'][$today] = $sentToday;

    // Cleanup: Hapus log notifikasi yang lebih lama dari 3 hari (agar JSON tidak bengkak)
    $cutoff = Carbon::now()->subDays(3);
    $prayer['notifications_sent'] = collect($prayer['notifications_sent'])
    ->filter(fn($sent, $date) => Carbon::parse($date)->gte($cutoff))
    ->toArray();

    $data['prayer'] = $prayer;
    $user->data = $data;

    // Gunakan update agar hanya kolom data yang diperbarui (lebih aman dari race condition)
    $user->update(['data' => $data]);
  }
}