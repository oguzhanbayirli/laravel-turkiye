<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\Validator;
use OguzhanBayirli\Turkiye\Identity\Iban;
use OguzhanBayirli\Turkiye\Identity\Tckn;
use OguzhanBayirli\Turkiye\Identity\Vkn;
use OguzhanBayirli\Turkiye\Tcmb\ExchangeRates;
use OguzhanBayirli\Turkiye\Words\AmountInWords;

class TurkiyeServiceProvider extends ServiceProvider
{
    /**
     * String rule name => checker.
     *
     * @var array<string, callable(mixed): bool>
     */
    private const RULES = [
        'tckn' => [Tckn::class, 'isValid'],
        'vkn' => [Vkn::class, 'isValid'],
        'tr_iban' => [Iban::class, 'isValid'],
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/turkiye.php', 'turkiye');

        $this->app->singleton(AmountInWords::class);

        $this->app->singleton(ExchangeRates::class, function (Application $app): ExchangeRates {
            /** @var array{base_url?: string, timeout?: int, connect_timeout?: int, retries?: int, max_lookback_days?: int, cache?: array{store?: string|null, prefix?: string, today_ttl?: int, archive_ttl?: int|null}} $config */
            $config = $app->make(Config::class)->get('turkiye.tcmb', []);

            return new ExchangeRates(
                $app->make(HttpFactory::class),
                $app->make(CacheFactory::class)->store($config['cache']['store'] ?? null),
                $config,
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'turkiye');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/turkiye.php' => $this->app->configPath('turkiye.php'),
            ], 'turkiye-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/turkiye'),
            ], 'turkiye-lang');
        }

        $this->callAfterResolving('validator', function (ValidatorFactory $validator): void {
            $rules = self::RULES + [
                'tckn_or_vkn' => static fn (mixed $value): bool => Tckn::isValid($value) || Vkn::isValid($value),
            ];

            foreach ($rules as $name => $check) {
                $validator->extend($name, static fn (string $attribute, mixed $value): bool => $check($value));

                // Use the application's own "validation.{rule}" line when it
                // has one, otherwise fall back to the package translation.
                $validator->replacer($name, static function (string $message, string $attribute, string $rule, array $parameters, Validator $validator) use ($name): string {
                    if ($message !== "validation.{$name}") {
                        return $message;
                    }

                    return (string) trans("turkiye::validation.{$name}", [
                        'attribute' => $validator->getDisplayableAttribute($attribute),
                    ]);
                });
            }
        });
    }
}
