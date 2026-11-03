<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Si esta prueba falla, las demas podrian estar escribiendo en una base real.
 */
class BasePruebasTest extends TestCase
{
    public function test_las_pruebas_solo_usan_la_base_aislada()
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(
            'storage/framework/testing/pruebas.sqlite',
            config('database.connections.sqlite.database')
        );
    }

    public function test_el_correo_y_la_cola_no_salen_del_proceso_de_pruebas()
    {
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('sync', config('queue.default'));
    }
}
