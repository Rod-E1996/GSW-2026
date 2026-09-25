<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\TipoHabitacion;
use App\Models\TipoHabitacionImagen;

/**
 * Carga fotos de ejemplo para cada tipo de habitación que aún no tenga imágenes.
 *
 *   php artisan db:seed --class=TiposHabitacionImagenesSeeder
 *
 * Busca fotos reales en database/seeders/imagenes/tipos_habitacion/<slug del tipo>/
 * (por ejemplo "sencilla", "doble", "familiar", "suite"). La primera en orden
 * alfabético queda como principal. Si un tipo no tiene carpeta, genera una
 * imagen de relleno con GD para que la demo no se vea vacía.
 *
 * Créditos de las fotos: database/seeders/imagenes/CREDITOS.md
 */
class TiposHabitacionImagenesSeeder extends Seeder
{
    //Colores de fondo para las imagenes de relleno (RGB); cualquier otro tipo usa el ultimo
    private $colores = [
        'Sencilla' => [96, 150, 180],
        'Doble'    => [72, 140, 110],
        'Familiar' => [196, 140, 60],
        'Suite'    => [110, 80, 150],
    ];

    public function run()
    {
        $tipos = TipoHabitacion::where('estado', 1)->doesntHave('imagenes')->get();

        foreach ($tipos as $tipo) {
            $fotos = $this->fotosReales($tipo);

            if (!empty($fotos)) {
                $this->cargarFotosReales($tipo, $fotos);
            } else {
                $this->cargarRelleno($tipo);
            }
        }
    }

    //Rutas absolutas de las fotos reales disponibles para el tipo, en orden alfabetico
    private function fotosReales(TipoHabitacion $tipo)
    {
        $carpeta = database_path('seeders/imagenes/tipos_habitacion/' . Str::slug($tipo->nombre));
        if (!is_dir($carpeta)) {
            return [];
        }

        $fotos = glob($carpeta . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        sort($fotos);

        return $fotos;
    }

    //Copia las fotos reales al disco publico y crea sus registros
    private function cargarFotosReales(TipoHabitacion $tipo, array $fotos)
    {
        $orden = 0;

        foreach ($fotos as $foto) {
            $orden++;
            $ruta = TipoHabitacionImagen::CARPETA . '/' . $tipo->id . '/' . basename($foto);

            Storage::disk(TipoHabitacionImagen::DISCO)->put($ruta, file_get_contents($foto));

            TipoHabitacionImagen::create([
                'tipo_habitacion_id' => $tipo->id,
                'ruta' => $ruta,
                'nombre_original' => basename($foto),
                'orden' => $orden,
                'principal' => $orden === 1,
            ]);
        }

        $this->command->info("Tipo {$tipo->nombre}: {$orden} foto(s) cargadas.");
    }

    //Genera una imagen de relleno con GD cuando el tipo no tiene fotos reales
    private function cargarRelleno(TipoHabitacion $tipo)
    {
        if (!extension_loaded('gd')) {
            $this->command->warn("Tipo {$tipo->nombre}: sin fotos y sin extensión GD, se omite.");
            return;
        }

        $ruta = TipoHabitacionImagen::CARPETA . '/' . $tipo->id . '/ejemplo-' . Str::slug($tipo->nombre) . '.png';

        Storage::disk(TipoHabitacionImagen::DISCO)->put($ruta, $this->generarImagen($tipo));

        TipoHabitacionImagen::create([
            'tipo_habitacion_id' => $tipo->id,
            'ruta' => $ruta,
            'nombre_original' => basename($ruta),
            'orden' => 1,
            'principal' => true,
        ]);

        $this->command->info("Tipo {$tipo->nombre}: sin fotos reales, se generó una imagen de relleno.");
    }

    //Imagen PNG 800x500 con un fondo de color y el nombre del tipo
    private function generarImagen(TipoHabitacion $tipo)
    {
        $ancho = 800;
        $alto = 500;
        [$r, $g, $b] = $this->colores[$tipo->nombre] ?? end($this->colores);

        $img = imagecreatetruecolor($ancho, $alto);

        //Degradado vertical simple
        for ($y = 0; $y < $alto; $y++) {
            $factor = 1 - ($y / $alto) * 0.35;
            $color = imagecolorallocate($img, (int)($r * $factor), (int)($g * $factor), (int)($b * $factor));
            imageline($img, 0, $y, $ancho, $y, $color);
        }

        //Texto centrado con la fuente interna de GD (escalada 3x para que sea legible)
        $texto = strtoupper($tipo->nombre);
        $fuente = 5;
        $escala = 3;
        $anchoTexto = imagefontwidth($fuente) * strlen($texto);
        $altoTexto = imagefontheight($fuente);

        $capa = imagecreatetruecolor($anchoTexto, $altoTexto);
        $fondo = imagecolorallocate($capa, 1, 2, 3);
        imagefill($capa, 0, 0, $fondo);
        imagecolortransparent($capa, $fondo);
        imagestring($capa, $fuente, 0, 0, $texto, imagecolorallocate($capa, 255, 255, 255));

        $destX = (int)(($ancho - $anchoTexto * $escala) / 2);
        $destY = (int)(($alto - $altoTexto * $escala) / 2);
        imagecopyresized($img, $capa, $destX, $destY, 0, 0, $anchoTexto * $escala, $altoTexto * $escala, $anchoTexto, $altoTexto);

        //Subtitulo: capacidad y precio
        $sub = $tipo->capacidad . ' huesped(es) - $' . number_format($tipo->precio_base, 2) . ' / noche';
        $anchoSub = imagefontwidth(3) * strlen($sub);
        imagestring($img, 3, (int)(($ancho - $anchoSub) / 2), $destY + $altoTexto * $escala + 20, $sub, imagecolorallocate($img, 235, 235, 235));

        ob_start();
        imagepng($img);
        $contenido = ob_get_clean();

        imagedestroy($img);
        imagedestroy($capa);

        return $contenido;
    }
}
