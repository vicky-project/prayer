<?php

return [
  'id' => 'prayer',
  'name' => 'Jadwal Shalat',
  'description' => 'Aplikasi pengingat jadwal shalat dengan countdown waktu shalat berikutnya.',
  'icon_emoji' => '🕌',
  'render_type' => 'iframe',
  'render_config' => [
    'url' => env('APP_URL') . '/apps/prayer'
  ]
];