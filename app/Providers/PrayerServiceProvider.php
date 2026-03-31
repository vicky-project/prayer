<?php

namespace Modules\Prayer\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Modules\Prayer\Notifications\PrayerSentFailed;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Prayer\Telegram\LocationHandler;
use Modules\Prayer\Telegram\PrayerCommand;
use Modules\Prayer\Telegram\PrayerCallback;
use Modules\Telegram\Services\Handlers\CallbackHandler as TelegramCallbackHandler;
use Modules\Telegram\Services\Handlers\CommandDispatcher;
use Modules\Telegram\Services\Handlers\LocationDispatcher;
use Modules\Telegram\Services\Handlers\ReplyDispatcher;
use Modules\Telegram\Services\Support\InlineKeyboardBuilder;
use Modules\Telegram\Services\Support\TelegramApi;

class PrayerServiceProvider extends ServiceProvider
{
  use PathNamespace;

  protected string $name = 'Prayer';

  protected string $nameLower = 'prayer';

  /**
  * Boot the application events.
  */
  public function boot(): void
  {
    $this->registerCommands();
    $this->registerCommandSchedules();
    $this->registerTranslations();
    $this->registerConfig();
    $this->registerViews();
    $this->loadMigrationsFrom(module_path($this->name, "database/migrations"));

    if ($this->app->bound(CommandDispatcher::class)) {
      $dispatcher = $this->app->make(CommandDispatcher::class);

      $this->registerTelegramCommands($dispatcher);
    } else {
      \Log::warning(
        "Telegram CommandDispatcher not bound. Skipping command registration.",
      );
    }

    if ($this->app->bound(TelegramCallbackHandler::class)) {
      $callback = $this->app->make(TelegramCallbackHandler::class);
      $this->registerCallbackHandlers($callback);
    }

    if ($this->app->bound(ReplyDispatcher::class)) {
      $replyDispatcher = $this->app->make(ReplyDispatcher::class);
      $this->registerReplyHandlers($replyDispatcher);
    }

    if ($this->app->bound(LocationDispatcher::class)) {
      $dispatcher = $this->app->make(LocationDispatcher::class);
      $dispatcher->registerHandler($this->app->make(LocationHandler::class));
    }

    if (
      config($this->nameLower . ".hook.enabled", false) &&
      class_exists($class = config($this->nameLower . ".hook.service"))
    ) {
      $this->registerHooks($class);
    }
  }

  /**
  * Register the service provider.
  */
  public function register(): void
  {
    $this->app->register(EventServiceProvider::class);
    $this->app->register(RouteServiceProvider::class);

    $this->app
    ->make("config")
    ->set("app.timezone",
      config("prayer.timezone", 'Asia/Jakarta'));
  }

  protected function registerHooks($hookService): void
  {
    $hookService::registerHook(
      config($this->nameLower . ".hook.name"),
      $this->nameLower."::hooks.app"
    );
  }

  protected function registerTelegramCommands(
    CommandDispatcher $dispatcher,
  ): void {
    $dispatcher->registerCommand(
      new PrayerCommand(
        $this->app->make(TelegramApi::class),
        $this->app->make(InlineKeyboardBuilder::class)
      ),
    );
  }

  protected function registerCallbackHandlers(
    TelegramCallbackHandler $callback,
  ): void {
    $callback->registerHandler(
      new PrayerCallback(
        $this->app->make(TelegramApi::class),
        $this->app->make(PrayerTimeService::class),
        $this->app->make(InlineKeyboardBuilder::class)
      )
    );
  }

  protected function registerReplyHandlers(
    ReplyDispatcher $replyDispatcher,
  ): void {
    // $replyDispatcher->registerHandler();
  }

  /**
  * Register commands in the format of Command::class
  */
  protected function registerCommands(): void
  {
    $this->commands([
      \Modules\Prayer\Console\FetchPrayerData::class,
      \Modules\Prayer\Console\ResetPrayerNotifications::class,
      \Modules\Prayer\Console\SendPrayerNotifications::class
    ]);
  }

  /**
  * Register command Schedules.
  */
  protected function registerCommandSchedules(): void
  {
    $this->app->booted(function () {
      //     $schedule = $this->app->make(Schedule::class);
      Schedule::command('app:prayer')
      ->monthly()
      ->runInBackground()
      ->withoutOverlapping()
      ->timezone(config("prayer.timezone"));
      Schedule::command('app:prayer-sent')
      ->everyMinute()
      ->onOneServer()
      ->withoutOverlapping()
      ->timezone(config("prayer.timezone"))
      ->onFailure(function() {
        \Log::error("Schedule failed for  app:prayer-sent");
        Notification::route("telegram", env("TELEGRAM_CHAT_ID"))->notifyNow(new PrayerSentFailed('app:prayer-sent'));
      })->pingBefore("https://hc-ping.com/86031fbf-7c4e-40e1-a302-521a1fa6470e")
      ->pingOnSuccess('https://hc-ping.com/86031fbf-7c4e-40e1-a302-521a1fa6470e');
      Schedule::command('app:prayer-reset')
      ->dailyAt('00:01')
      ->withoutOverlapping()
      ->onOneServer()
      ->timezone(config("prayer.timezone"));
    });
  }

  /**
  * Register translations.
  */
  public function registerTranslations(): void
  {
    $langPath = resource_path('lang/modules/'.$this->nameLower);

    if (is_dir($langPath)) {
      $this->loadTranslationsFrom($langPath, $this->nameLower);
      $this->loadJsonTranslationsFrom($langPath);
    } else {
      $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
      $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
    }
  }

  /**
  * Register config.
  */
  protected function registerConfig(): void
  {
    $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

    if (is_dir($configPath)) {
      $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

      foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
          $config = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
          $config_key = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
          $segments = explode('.', $this->nameLower.'.'.$config_key);

          // Remove duplicated adjacent segments
          $normalized = [];
          foreach ($segments as $segment) {
            if (end($normalized) !== $segment) {
              $normalized[] = $segment;
            }
          }

          $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

          $this->publishes([$file->getPathname() => config_path($config)], 'config');
          $this->merge_config_from($file->getPathname(), $key);
        }
      }
    }
  }

  /**
  * Merge config from the given path recursively.
  */
  protected function merge_config_from(string $path, string $key): void
  {
    $existing = config($key, []);
    $module_config = require $path;

    config([$key => array_replace_recursive($existing, $module_config)]);
  }

  /**
  * Register views.
  */
  public function registerViews(): void
  {
    $viewPath = resource_path('views/modules/'.$this->nameLower);
    $sourcePath = module_path($this->name, 'resources/views');

    $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

    $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

    Blade::componentNamespace(config('modules.namespace').'\\' . $this->name . '\\View\\Components', $this->nameLower);
  }

  /**
  * Get the services provided by the provider.
  */
  public function provides(): array
  {
    return [];
  }

  private function getPublishableViewPaths(): array
  {
    $paths = [];
    foreach (config('view.paths') as $path) {
      if (is_dir($path.'/modules/'.$this->nameLower)) {
        $paths[] = $path.'/modules/'.$this->nameLower;
      }
    }

    return $paths;
  }
}