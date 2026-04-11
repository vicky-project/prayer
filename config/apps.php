<?php

return [
  'id' => 'prayer-times',
  'name' => 'Jadwal Shalat',
  'description' => 'Waktu shalat berdasarkan lokasi',
  'icon_class' => 'bi bi-compass',
  'render_type' => 'iframe',
  'render_config' => [
    'url' => env('APP_URL') . '/apps/prayer'
  ]
];