<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\UrlGenerator;
use App\Services\SesionTokenService;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //Pasarela del anticipo segun PASARELA_DRIVER (por ahora solo la de pruebas)
        $this->app->singleton(\App\Pagos\Pasarela::class, function () {
            return match (config('services.pasarela.driver')) {
                'sandbox' => new \App\Pagos\PasarelaSandbox(),
                default => throw new \InvalidArgumentException('Pasarela de pago no soportada: ' . config('services.pasarela.driver')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(UrlGenerator $url)
    {
        if (env('APP_ENV') === 'production') {
            $url->forceScheme('https');
        }else{
            $url->forceScheme('http');
        }

        setlocale(LC_TIME, "spanish");  // to set carbon in spanish

        Paginator::useBootstrap();
        Schema::defaultStringLength(191);

        //Cada peticion con token revisa que el usuario siga activo y que no haya vencido por inactividad
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $esValido) {
            return $esValido && app(SesionTokenService::class)->esVigente($token);
        });
    }
}
