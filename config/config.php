<?php

return [
  'name' => 'Prayer',
  "base_api_url" => env("PRAYER_BASEAPI_URL", "https://shalat-api.vercel.app"),
  "hook" => [
    "enabled" => env("PRAYER_HOOK_ENABLED", true),
    "service" => \Modules\CoreUI\Services\UIService::class,
    "name" => "main-apps",
  ]
];