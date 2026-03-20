<?php

return [
  'name' => 'Prayer',
  "base_api_url" => env("PRAYER_BASEAPI_URL", "https://vickyserver.my.id/data/local_data.json"),
  "timezone" => env("PRAYER_TIMEZONE", "Asia/Makassar"),
  "hook" => [
    "enabled" => env("PRAYER_HOOK_ENABLED", true),
    "service" => \Modules\CoreUI\Services\UIService::class,
    "name" => "main-apps",
  ],
  "ipgeolocation" => [
    "api_key" => env("IPGEOLOCATION_APIKEY")
  ]
];