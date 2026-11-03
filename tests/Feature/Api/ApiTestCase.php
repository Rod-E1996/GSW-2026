<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\SesionTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Base de las pruebas de la API: autentica con tokens reales de Sanctum,
 * igual que el portal React.
 */
abstract class ApiTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function admin(): User
    {
        return User::where('email', 'admin@gmail.com')->firstOrFail();
    }

    protected function recepcion(): User
    {
        return User::where('email', 'recepcion@hotellink.com')->firstOrFail();
    }

    protected function huesped(): User
    {
        return User::where('email', 'huesped@hotellink.com')->firstOrFail();
    }

    protected function tokenDe(User $user, bool $recordar = false): string
    {
        return app(SesionTokenService::class)->crearToken($user, $recordar)->plainTextToken;
    }

    //Envia las siguientes peticiones con ese token, sin reutilizar el usuario de la peticion anterior.
    protected function conToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    protected function como(User $user): static
    {
        return $this->conToken($this->tokenDe($user));
    }
}
