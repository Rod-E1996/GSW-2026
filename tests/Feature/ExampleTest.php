<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_example()
    {
        //Laravel ya no sirve pantallas: la raiz manda al portal React
        $this->get('/')->assertRedirect(config('app.frontend_url'));
    }
}
