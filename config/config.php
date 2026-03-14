<?php

return [
  'name' => 'Prayer',
  "base_api_url" => env("PRAYER_BASEAPI_URL", "http://loscos4w40ko04sss0cg0wo4.70.153.72.107.sslip.io"),
  "hook" => [
    "enabled" => env("PRAYER_HOOK_ENABLED", true),
    "service" => \Modules\CoreUI\Services\UIService::class,
    "name" => "main-apps",
  ]
];