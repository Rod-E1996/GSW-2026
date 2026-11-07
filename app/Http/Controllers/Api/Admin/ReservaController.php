<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\HabitacionNoDisponible;
use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Habitacion;
use App\Models\Huesped;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Services\CancelacionService;
use App\Services\ComprobanteService;
use App\Services\DisponibilidadService;
use App\Services\NotificacionService;
use App\Services\PagoService;
use App\Services\RecepcionService;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reservas en el panel de recepcion: busqueda, reserva telefonica o presencial,
 * confirmar, modificar, cancelar, entrada, consumos y salida.
 */
class ReservaController extends Controller
{
    const CANALES = [
        Reserva::CANAL_WEB => 'Web',
        Reserva::CANAL_TELEFONO => 'Teléfono',
        Reserva::CANAL_PRESENCIAL => 'Presencial',
    ];

    public function __construct(
        private ReservaService $reservas,
        private RecepcionService $recepcion,
        private CancelacionService $cancelaciones,
        private DisponibilidadService $disponibilidad,
        private ComprobanteService $comprobantes,
        private NotificacionService $notificaciones
    ) {
    }

    /**
     * Busca por codigo, documento, correo, nombre o telefono del titular. Acepta tambien
     * lo que entrega el lector del QR (la URL o el token del comprobante).
     */
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'estado' => ['nullable', 'string', 'in:1,2,3,4,5,vencida'],
            'canal' => ['nullable', Rule::in(array_keys(self::CANALES))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = Reserva::with(['huesped', 'tipoHabitacion', 'habitacion'])->where('estado', 1);

        if ($q = trim((string) $request->get('q'))) {
            if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $q, $m)) {
                $query->whereIn('id', Comprobante::where('qr_token', strtolower($m[0]))->pluck('reserva_id'));
            } else {
                $query->where(function ($w) use ($q) {
                    $w->where('codigo', 'LIKE', '%' . strtoupper($q) . '%')
                        ->orWhereHas('huesped', function ($h) use ($q) {
                            $h->where('documento', 'LIKE', "%$q%")
                                ->orWhere('email', 'LIKE', "%$q%")
                                ->orWhere('telefono', 'LIKE', "%$q%")
                                ->orWhereRaw(self::nombreCompletoSql() . ' LIKE ?', ["%$q%"]);
                        });
                });
            }
        }

        $estado = $request->get('estado');
        $limite = now()->subHours((int) \App\Models\Configuracion::valor('reserva_pendiente_horas', 24));
        if ($estado === 'vencida') {
            $query->where('estado_reserva', Reserva::PENDIENTE)->where('created_at', '<=', $limite);
        } elseif ($estado === (string) Reserva::PENDIENTE) {
            $query->where('estado_reserva', Reserva::PENDIENTE)->where('created_at', '>', $limite);
        } elseif ($estado) {
            $query->where('estado_reserva', (int) $estado);
        }

        $query->when($request->get('canal'), fn ($q, $canal) => $q->where('canal', $canal))
            ->when($request->get('desde'), fn ($q, $desde) => $q->whereDate('fecha_llegada', '>=', $desde))
            ->when($request->get('hasta'), fn ($q, $hasta) => $q->whereDate('fecha_llegada', '<=', $hasta));

        $pagina = $query->orderByDesc('fecha_llegada')->orderByDesc('id')->paginate(15);
        $vigentes = Reserva::vigentes()->whereIn('id', $pagina->pluck('id'))->pluck('id');

        return response()->json([
            'data' => $pagina->getCollection()->map(fn (Reserva $r) => $this->resumen($r, $vigentes->contains($r->id))),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function catalogos()
    {
        return response()->json([
            'estados' => collect(Reserva::ESTADOS)->map(fn ($e, $valor) => ['valor' => (string) $valor, 'nombre' => $e['nombre']])
                ->values()->push(['valor' => 'vencida', 'nombre' => 'Pendiente vencida']),
            'canales' => collect(self::CANALES)->map(fn ($nombre, $valor) => ['valor' => $valor, 'nombre' => $nombre])->values(),
            'metodos_pago' => collect(RecepcionService::METODOS)->map(fn ($nombre, $valor) => ['valor' => $valor, 'nombre' => $nombre])->values(),
        ]);
    }

    public function show(string $codigo)
    {
        return response()->json(['data' => $this->detalle($this->buscar($codigo))]);
    }

    //Reserva telefonica o presencial. El titular es una ficha existente o una nueva.
    public function store(Request $request)
    {
        $datos = $request->validate($this->reglasEstadia() + [
            'canal' => ['required', Rule::in([Reserva::CANAL_TELEFONO, Reserva::CANAL_PRESENCIAL])],
            'huesped_id' => ['required', 'integer', Rule::exists('huespedes', 'id')->where('estado', 1)],
        ], $this->mensajes());

        $habitacion = Habitacion::with('tipoHabitacion')->findOrFail($datos['habitacion_id']);
        $this->validarCapacidad($datos, $habitacion);

        try {
            $reserva = $this->reservas->crear(
                array_intersect_key($datos, array_flip(['habitacion_id', 'fecha_llegada', 'fecha_salida', 'adultos', 'ninos', 'hora_estimada_llegada', 'notas', 'canal', 'huesped_id'])) + [
                    'user_id' => $request->user()->id,
                ],
                $datos['servicios'] ?? []
            );
        } catch (HabitacionNoDisponible $e) {
            return $this->conflicto($e, $datos, $habitacion);
        }

        $this->notificaciones->registro($reserva);

        return response()->json([
            'message' => 'Reserva ' . $reserva->codigo . ' registrada. Queda pendiente hasta recibir el anticipo.',
            'data' => $this->detalle($reserva->fresh()),
        ], 201);
    }

    //Modificar: fechas, habitacion, personas, servicios contratados, hora y notas.
    public function update(Request $request, string $codigo)
    {
        $reserva = $this->buscar($codigo);
        $datos = $request->validate($this->reglasEstadia(), $this->mensajes());
        $habitacion = Habitacion::with('tipoHabitacion')->findOrFail($datos['habitacion_id']);
        $this->validarCapacidad($datos, $habitacion);

        try {
            $resultado = $this->reservas->modificar(
                $reserva,
                array_intersect_key($datos, array_flip(['habitacion_id', 'fecha_llegada', 'fecha_salida', 'adultos', 'ninos'])) + [
                    'hora_estimada_llegada' => $datos['hora_estimada_llegada'] ?? null,
                    'notas' => $datos['notas'] ?? null,
                ],
                $request->has('servicios') ? ($datos['servicios'] ?? []) : null
            );
        } catch (HabitacionNoDisponible $e) {
            return $this->conflicto($e, $datos, $habitacion, $reserva->id);
        }

        if (!$resultado['cambios']) {
            return response()->json(['message' => 'No había cambios que guardar.', 'data' => $this->detalle($resultado['reserva'])]);
        }

        $reserva = $resultado['reserva'];
        //El comprobante impreso debe mostrar lo nuevo: se emite otra version y la anterior deja de valer
        if ((int) $reserva->estado_reserva === Reserva::CONFIRMADA) {
            try {
                $this->comprobantes->reemitir($reserva);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $this->notificaciones->modificacion($reserva, $resultado['cambios']);

        return response()->json([
            'message' => 'Reserva modificada. Avisamos al huésped por correo.',
            'data' => $this->detalle($reserva->fresh()),
        ]);
    }

    //Habitaciones que la reserva puede ocupar en otras fechas (la propia no cuenta como ocupacion)
    public function habitaciones(Request $request, string $codigo)
    {
        $reserva = $this->buscar($codigo);
        $datos = $request->validate([
            'llegada' => ['required', 'date_format:Y-m-d'],
            'salida' => ['required', 'date_format:Y-m-d', 'after:llegada'],
            'huespedes' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $libres = $this->disponibilidad->habitacionesLibres($datos['llegada'], $datos['salida'], null, $reserva->id)
            ->filter(fn (Habitacion $h) => $h->tipoHabitacion->capacidad >= (int) $datos['huespedes'])
            ->sortBy([['tipoHabitacion.nombre', 'asc'], ['numero', 'asc']])
            ->values();

        return response()->json([
            'data' => $libres->map(fn (Habitacion $h) => [
                'id' => $h->id,
                'numero' => $h->numero,
                'piso' => $h->piso,
                'tipo' => $h->tipoHabitacion->nombre,
                'capacidad' => $h->tipoHabitacion->capacidad,
                'estado_habitacion' => $h->estado_habitacion,
                'estado_nombre' => $h->estado_nombre,
                'actual' => (int) $h->id === (int) $reserva->habitacion_id,
            ]),
        ]);
    }

    public function confirmar(Request $request, string $codigo)
    {
        $reserva = $this->buscar($codigo);
        $pago = $request->validate($this->reglasPago(true), $this->mensajesPago());

        $reserva = $this->recepcion->confirmar($reserva, $pago, $request->user());

        return response()->json([
            'message' => 'Reserva confirmada. Enviamos el comprobante al huésped.',
            'data' => $this->detalle($reserva),
        ]);
    }

    public function cancelar(Request $request, string $codigo)
    {
        $reserva = $this->buscar($codigo);
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:255']], [
            'motivo.required' => 'Anota el motivo de la cancelación.',
        ]);

        $resultado = $this->cancelaciones->cancelar($reserva, $datos['motivo'], true);
        $reembolso = $resultado['politica']['reembolso'];

        return response()->json([
            'message' => 'Reserva cancelada.' . ($reembolso > 0 ? ' Corresponde reembolsar $' . number_format($reembolso, 2) . '.' : ''),
            'data' => $this->detalle($resultado['reserva']->fresh()),
        ]);
    }

    public function checkin(Request $request, string $codigo)
    {
        $reserva = $this->buscar($codigo);
        $datos = $request->validate([
            'habitacion_id' => ['nullable', 'integer'],
            'identidad_verificada' => ['accepted'],
        ], [
            'identidad_verificada.accepted' => 'Confirma que verificaste el documento del huésped.',
        ]);

        $reserva = $this->recepcion->checkin($reserva, $datos['habitacion_id'] ?? null, $request->user());

        return response()->json([
            'message' => 'Entrada registrada. La reserva está en estadía.',
            'data' => $this->detalle($reserva),
        ]);
    }

    public function agregarConsumo(Request $request, string $codigo)
    {
        $reserva = $this->buscar($codigo);
        $datos = $request->validate([
            'servicio_id' => ['required', 'integer'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'notas' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'message' => 'Consumo cargado.',
            'data' => $this->detalle($this->recepcion->agregarConsumo($reserva, $datos)),
        ]);
    }

    public function quitarConsumo(string $codigo, int $linea)
    {
        $reserva = $this->buscar($codigo);

        return response()->json([
            'message' => 'Consumo quitado.',
            'data' => $this->detalle($this->recepcion->quitarConsumo($reserva, $linea)),
        ]);
    }

    public function checkout(Request $request, string $codigo)
    {
        $reserva = $this->buscar($codigo);
        $pago = $request->validate($this->reglasPago(false), $this->mensajesPago());

        $reserva = $this->recepcion->checkout($reserva, $pago, $request->user());

        return response()->json([
            'message' => 'Salida registrada. La habitación ' . $reserva->habitacion?->numero . ' quedó en limpieza.',
            'data' => $this->detalle($reserva),
        ]);
    }

    //Nombre y apellidos juntos en SQL: MariaDB usa CONCAT (alli || es un O logico), SQLite usa ||
    public static function nombreCompletoSql(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
            ? "(nombres || ' ' || apellidos)"
            : "CONCAT(nombres, ' ', apellidos)";
    }

    private function buscar(string $codigo): Reserva
    {
        return Reserva::where('estado', 1)->where('codigo', strtoupper($codigo))->firstOr(
            fn () => abort(response()->json(['message' => 'No existe una reserva con ese código.'], 404))
        );
    }

    private function resumen(Reserva $r, bool $vigente): array
    {
        $vencida = (int) $r->estado_reserva === Reserva::PENDIENTE && !$vigente;

        return [
            'codigo' => $r->codigo,
            'estado' => $r->estado_reserva,
            'estado_nombre' => $vencida ? 'Pendiente vencida' : $r->estado_nombre,
            'vencida' => $vencida,
            'canal' => $r->canal,
            'canal_nombre' => self::CANALES[$r->canal] ?? '—',
            'titular' => $r->huesped?->nombre_completo,
            'documento' => trim(($r->huesped?->tipo_documento ?? '') . ' ' . ($r->huesped?->documento ?? '')),
            'tipo' => $r->tipoHabitacion?->nombre,
            'habitacion' => $r->habitacion?->numero,
            'fecha_llegada' => $r->fecha_llegada->toDateString(),
            'fecha_salida' => $r->fecha_salida->toDateString(),
            'noches' => $r->noches_total,
            'personas' => (int) $r->adultos + (int) $r->ninos,
            'total' => (float) $r->total,
            'creada_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function detalle(Reserva $r): array
    {
        $r->load(['huesped.user', 'tipoHabitacion', 'habitacion', 'noches.temporada', 'servicios', 'historial.user', 'pagos.user', 'user', 'notificaciones']);
        $vigente = Reserva::vigentes()->whereKey($r->id)->exists();
        $pagado = $r->montoPagado();
        $saldo = $this->recepcion->saldo($r);
        $comprobante = $r->comprobantes()->reorder('version', 'desc')->first();
        $estado = (int) $r->estado_reserva;
        $hoy = now()->startOfDay();

        return $this->resumen($r, $vigente) + [
            'adultos' => $r->adultos,
            'ninos' => $r->ninos,
            'hora_estimada_llegada' => $r->hora_estimada_llegada ? substr($r->hora_estimada_llegada, 0, 5) : null,
            'notas' => $r->notas,
            'habitacion_id' => $r->habitacion_id,
            'creada_por' => $r->user?->name,
            'check_in_at' => $r->check_in_at?->toIso8601String(),
            'check_out_at' => $r->check_out_at?->toIso8601String(),
            'cancelada_at' => $r->cancelada_at?->toIso8601String(),
            'motivo_cancelacion' => $r->motivo_cancelacion,
            'huesped' => [
                'id' => $r->huesped->id,
                'nombres' => $r->huesped->nombres,
                'apellidos' => $r->huesped->apellidos,
                'tipo_documento' => $r->huesped->tipo_documento,
                'documento' => $r->huesped->documento,
                'email' => $r->huesped->email,
                'telefono' => $r->huesped->telefono,
                'tiene_cuenta' => $r->huesped->user_id !== null,
            ],
            'subtotal_alojamiento' => (float) $r->subtotal_alojamiento,
            'subtotal_servicios' => (float) $r->subtotal_servicios,
            'descuento' => (float) $r->descuento,
            'impuesto' => (float) $r->impuesto,
            'anticipo_requerido' => (float) $r->anticipo_requerido,
            'pagado' => $pagado,
            'saldo' => $saldo,
            'detalle_noches' => $r->noches->map(fn ($n) => [
                'fecha' => $n->fecha->toDateString(),
                'temporada' => $n->temporada?->nombre,
                'precio_noche' => (float) $n->precio_noche,
            ]),
            'servicios' => $r->servicios->map(fn ($s) => [
                'linea' => (int) $s->pivot->id,
                'servicio_id' => $s->id,
                'nombre' => $s->nombre,
                'cantidad' => (int) $s->pivot->cantidad,
                'precio_unitario' => (float) $s->pivot->precio_unitario,
                'subtotal' => (float) $s->pivot->subtotal,
                'fecha_servicio' => $s->pivot->fecha_servicio ? substr($s->pivot->fecha_servicio, 0, 10) : null,
                'hora_servicio' => $s->pivot->hora_servicio ? substr($s->pivot->hora_servicio, 0, 5) : null,
                'consumo' => (int) $s->pivot->estado_servicio === Reserva::SERVICIO_CONSUMIDO,
                'notas' => $s->pivot->notas,
            ])->values(),
            'pagos' => $r->pagos->map(fn (Pago $p) => [
                'id' => $p->id,
                'tipo' => $p->tipo_pago,
                'monto' => (float) $p->monto,
                'estado' => $p->estado_pago,
                'estado_nombre' => $p->estado_nombre,
                'metodo' => RecepcionService::METODOS[$p->metodo_pago] ?? ucfirst((string) $p->metodo_pago),
                'medio' => isset($p->respuesta_pasarela['ultimos4'])
                    ? ($p->respuesta_pasarela['marca'] ?? 'Tarjeta') . ' terminada en ' . $p->respuesta_pasarela['ultimos4'] : null,
                'pasarela' => $p->pasarela,
                'referencia' => $p->pasarela ? null : $p->referencia_externa,
                'motivo' => $p->respuesta_pasarela['motivo'] ?? null,
                'registrado_por' => $p->pasarela ? null : $p->user?->name,
                'fecha' => ($p->pagado_at ?? $p->updated_at)?->toIso8601String(),
                'requiere_reembolso' => PagoService::requiereReembolso($p),
            ])->values(),
            'comprobante' => $comprobante ? [
                'numero' => $comprobante->numero,
                'version' => $comprobante->version,
                'estado' => $comprobante->estado_nombre,
                'vigente' => (int) $comprobante->estado === Comprobante::VIGENTE,
                'qr_token' => $comprobante->qr_token,
                'verificado_at' => $comprobante->verificado_at?->toIso8601String(),
            ] : null,
            'notificaciones' => $r->notificaciones->map(fn (Notificacion $n) => [
                'tipo' => $n->tipo,
                'destinatario' => $n->destinatario,
                'estado' => match ((int) $n->estado_envio) {
                    Notificacion::ENVIADO => 'Enviado',
                    Notificacion::FALLIDO => 'Fallido',
                    default => 'En cola',
                },
                'intentos' => $n->intentos,
                'error' => $n->error,
                'fecha' => ($n->enviado_at ?? $n->created_at)?->toIso8601String(),
            ])->values(),
            'historial' => $r->historial->map(fn ($h) => [
                'estado' => Reserva::ESTADOS[$h->estado_nuevo]['nombre'] ?? 'Desconocido',
                'cambio_estado' => (int) $h->estado_anterior !== (int) $h->estado_nuevo,
                'comentario' => $h->comentario,
                'usuario' => $h->user?->name,
                'fecha' => $h->created_at?->toIso8601String(),
            ]),
            'cancelacion' => $this->cancelaciones->evaluar($r),
            //Lo que el estado permite; el panel ademas revisa los permisos del usuario
            'acciones' => [
                'confirmar' => $estado === Reserva::PENDIENTE,
                'modificar' => in_array($estado, [Reserva::PENDIENTE, Reserva::CONFIRMADA]),
                'cancelar' => in_array($estado, [Reserva::PENDIENTE, Reserva::CONFIRMADA]),
                'checkin' => $estado === Reserva::CONFIRMADA && $hoy->gte($r->fecha_llegada) && $hoy->lt($r->fecha_salida),
                'consumos' => $estado === Reserva::EN_ESTADIA,
                'checkout' => $estado === Reserva::EN_ESTADIA,
            ],
        ];
    }

    private function reglasEstadia(): array
    {
        return [
            'habitacion_id' => ['required', 'integer', Rule::exists('habitaciones', 'id')->where('estado', 1)],
            'fecha_llegada' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'fecha_salida' => ['required', 'date_format:Y-m-d', 'after:fecha_llegada'],
            'adultos' => Reserva::$rules['adultos'],
            'ninos' => Reserva::$rules['ninos'],
            'hora_estimada_llegada' => Reserva::$rules['hora_estimada_llegada'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'servicios' => ['nullable', 'array', 'max:20'],
            'servicios.*.servicio_id' => ['required', 'integer', 'distinct'],
            'servicios.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:20'],
            'servicios.*.fecha_servicio' => ['nullable', 'date_format:Y-m-d'],
            'servicios.*.hora_servicio' => ['nullable', 'date_format:H:i'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'fecha_llegada.after_or_equal' => 'La llegada no puede ser una fecha pasada.',
            'fecha_salida.after' => 'La salida debe ser posterior a la llegada.',
            'servicios.*.servicio_id.distinct' => 'Ese servicio está repetido.',
            'huesped_id.required' => 'Elige o registra al huésped titular.',
        ];
    }

    private function reglasPago(bool $obligatorio): array
    {
        $requerido = $obligatorio ? 'required' : 'nullable';

        return [
            'monto' => [$requerido, 'numeric', 'min:0.01', 'max:100000'],
            'metodo_pago' => [$requerido, Rule::in(array_keys(RecepcionService::METODOS))],
            'referencia' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function mensajesPago(): array
    {
        return [
            'monto.required' => 'Indica el monto recibido.',
            'metodo_pago.required' => 'Indica cómo pagó el huésped.',
        ];
    }

    private function validarCapacidad(array $datos, Habitacion $habitacion): void
    {
        $noches = (int) Carbon::parse($datos['fecha_llegada'])->diffInDays(Carbon::parse($datos['fecha_salida']));
        if ($noches > Reserva::MAX_NOCHES) {
            throw ValidationException::withMessages(['fecha_salida' => 'La estadía no puede superar las ' . Reserva::MAX_NOCHES . ' noches.']);
        }

        $capacidad = $habitacion->tipoHabitacion->capacidad;
        if ((int) $datos['adultos'] + (int) ($datos['ninos'] ?? 0) > $capacidad) {
            throw ValidationException::withMessages(['adultos' => "La habitación {$habitacion->numero} admite hasta $capacidad persona(s)."]);
        }
    }

    private function conflicto(HabitacionNoDisponible $e, array $datos, Habitacion $habitacion, ?int $excluir = null)
    {
        return response()->json([
            'message' => $e->getMessage(),
            'alternativas' => $this->disponibilidad->alternativas(
                $datos['fecha_llegada'],
                $datos['fecha_salida'],
                (int) $datos['adultos'] + (int) ($datos['ninos'] ?? 0),
                $habitacion->tipo_habitacion_id
            ),
        ], 409);
    }
}
