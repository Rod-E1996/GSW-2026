# Plan de trabajo de HotelLink

Actualizado el 7 de noviembre de 2026: commit `d87f37a` más la Fase 6, aún sin confirmar. Se compara con el informe «Entrega 5: Manual de usuario y Manual técnico».

La vista gráfica e interactiva está en [avance-hotellink.html](avance-hotellink.html).

## Dónde estamos

**Avance global estimado: 79 %.** De 64 requisitos, 47 están desarrollados, 5 están parciales y 12 no se han empezado.

Las fases 0 a 6 están terminadas: el huésped reserva y paga en línea, y recepción atiende el ciclo completo desde el panel (reserva telefónica o presencial, confirmar, modificar, cancelar, entrada, consumos y salida), con panel de control y calendario de ocupación. Faltan los reportes gerenciales, las valoraciones, los respaldos y el sitio bilingüe.

| Capa | Avance |
|---|---:|
| Datos | 89 % |
| Lógica | 80 % |
| Pantalla React | 81 % |
| Pruebas | 80 % |

De las 39 tablas del diseño existen 35: las 18 de plataforma y 17 del dominio, todas con las columnas del diccionario. Faltan 4.

### Cómo se calculó

- Cada requisito se puntúa en datos (20 %), lógica (35 %), pantalla React (30 %) y pruebas (15 %), con valores 0, 0.5 o 1. Las capas que no aplican se excluyen.
- Lo que solo funciona en Blade vale 0.5 en lógica, porque el manual fija React y la API como destino.
- El peso de cada requisito (1 a 5) es una estimación de esfuerzo y criticidad, así que el porcentaje es orientativo.
- «Desarrollado» significa que datos, lógica y pantalla están completos, aunque pueden faltar pruebas.
- Se comprobó: 309 pruebas en verde sobre SQLite; y el ciclo de recepción completo, de la reserva presencial a la salida, recorrido en Chrome. Las migraciones de las fases 4 y 5 y la búsqueda por nombre en MariaDB aún no se ensayaron.

## Después de la Fase 6

Ejecuta `docker compose exec back php artisan migrate --force`. Define `MAIL_FROM_ADDRESS` en el .env: hoy vale null y ningún correo sale. Los correos de la reserva viajan por la cola: el contenedor del worker debe estar encendido. El manual técnico debe agregar `SANCTUM_*`, `CORS_ALLOWED_ORIGINS`, `PASARELA_DRIVER` y `PASARELA_SECRETO`, la expiración de tokens, el límite de login, las rutas nuevas de la API y cómo correr `tests/Concurrencia/ejecutar.php`.

## Resumen de fases

| Fase | Nombre | Avance | Días-persona | Depende de |
|---|---|---:|---:|---|
| 0 | Estabilizar la base | 100 % | 4 | Ninguna |
| 1 | Núcleo de datos de reservas | 100 % | 3 | Fase 0 |
| 2 | Inventario, tarifas y servicios | 100 % | 9 | Fase 1 |
| 3 | Motor de disponibilidad | 100 % | 7 | Fase 2 |
| 4 | Reserva en línea y Mi cuenta | 100 % | 6 | Fase 3 |
| 5 | Pago, comprobante y correos | 100 % | 8 | Fase 4 |
| 6 | Operación de recepción | 100 % | 9 | Fase 3 (la Fase 5 solo para el QR) |
| 7 | Reportes, valoraciones y respaldos | 0 % | 8 | Fase 6 |
| 8 | Sitio público y bilingüe | 24 % | 6 | Puede avanzar en paralelo desde la Fase 2 |
| 9 | Cierre y puesta en producción | 22 % | 6 | Todas las anteriores |
| | **Total** | **79 %** | **66** | |

Los días son una primera estimación para una persona a tiempo completo, sin margen para imprevistos. Las fases 6, 7 y 8 pueden avanzar en paralelo una vez terminada la Fase 3.

## Detalle de cada fase

### Fase 0: Estabilizar la base

Cerrar los huecos de lo ya construido antes de levantar el resto encima.

**Tareas**

- [x] Rechazar en cada petición los tokens de usuarios inactivos y revocarlos al desactivar la cuenta.
- [x] Definir la expiración de los tokens de Sanctum y limitar los intentos de POST /api/login.
- [x] Restringir CORS al dominio del portal.
- [x] Crear una base exclusiva para pruebas, para que los tests no toquen la base de desarrollo.
- [x] Escribir pruebas de API para sesión, usuarios, roles, permisos, sesiones, auditoría, errores, recuperación de contraseña y correos.
- [x] Recuperar la configuración de Docker del manual: compose.yaml, web02, base sin puerto publicado y .env.example completo.
- [x] Corregir dos fallos de arranque: la espera de la primera instalación y los permisos de caché que causaban un error 500.

**Terminada cuando:** Pruebas de API en verde sobre una base separada; un usuario desactivado recibe 401; el arranque con Docker coincide con el manual.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 001 | Inicio y cierre de sesión | Desarrollado | 100 % | Nada: requisito cerrado. |
| 002 | Recuperar contraseña | Desarrollado | 100 % | Nada: requisito cerrado. |
| 003 | Roles y permisos | Desarrollado | 100 % | Nada: requisito cerrado. |
| 004 | Usuarios del personal | Desarrollado | 100 % | Nada: requisito cerrado. |
| 005 | Sesiones por dispositivo | Desarrollado | 100 % | Nada: requisito cerrado. |
| 006 | Bitácora de auditoría | Desarrollado | 100 % | Nada: requisito cerrado. |
| 007 | Registro de errores | Desarrollado | 100 % | Nada: requisito cerrado. |
| 008 | Endurecer la seguridad de la API | Desarrollado | 100 % | Nada: requisito cerrado. |
| 009 | Contenedores según el manual | Desarrollado | 100 % | Nada: requisito cerrado. |
| 010 | Base de pruebas y cobertura | Desarrollado | 100 % | Nada: requisito cerrado. |
| 011 | Correo SMTP y cola de trabajos | Desarrollado | 100 % | Nada: requisito cerrado. |

### Fase 1: Núcleo de datos de reservas

Dejar el modelo de reservas igual al diccionario de datos, porque casi todos los módulos dependen de él.

**Tareas**

- [x] Crear la tabla huespedes y migrar los campos huesped_* de reservas hacia huesped_id.
- [x] Agregar a reservas: canal, subtotales, descuento, impuesto, anticipo, notas, check_in_at, check_out_at, cancelada_at y motivo.
- [x] Crear reserva_noches y reserva_historial; completar reserva_servicio.
- [x] Registrar los permisos de reservas, servicios, tarifas, valoraciones, reportes y respaldos, también en bases ya instaladas.
- [x] Crear la tabla configuraciones con su API y pantalla para anticipo, impuestos y política de cancelación.
- [x] Corregir los nombres de estado: En estadía y Finalizada.
- [x] Cerrar el agujero de aislamiento de las pruebas: Laravel leía la conexión real desde $_SERVER.

**Terminada cuando:** migrate:fresh --seed funciona en MariaDB y las tablas coinciden con las secciones 3.10 a 3.14 del diccionario.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 101 | Tabla de huéspedes | Desarrollado | 100 % | Nada: requisito cerrado. |
| 102 | Reservas según el diccionario | Desarrollado | 100 % | Nada: requisito cerrado. |
| 103 | Noches e historial de la reserva | Desarrollado | 100 % | Nada: requisito cerrado. |
| 104 | Permisos de los módulos nuevos | Desarrollado | 100 % | Nada: requisito cerrado. |
| 105 | Parámetros del sistema | Desarrollado | 100 % | Nada: requisito cerrado. |

### Fase 2: Inventario, tarifas y servicios

Que el administrador pueda configurar todo lo que el motor de reservas necesita leer.

**Tareas**

- [x] Habitaciones: API y pantalla React; retirar su módulo Blade.
- [x] Bloqueos de habitación por fechas: tabla, API y pantalla.
- [x] Temporadas: completar columnas (nombre_en, prioridad, color), API y pantalla.
- [x] Tarifas por tipo y temporada: tabla, API y matriz editable.
- [x] Categorías de servicio y catálogo de servicios: tablas, API y pantallas.
- [x] Tipos de habitación: agregar nombre_en, descripcion_en, slug y orden.
- [x] Perfil del usuario en React: datos, contraseña y sesiones.
- [x] Convertir el estado de habitación a los valores del diccionario: disponible, limpieza y mantenimiento.

**Terminada cuando:** Un administrador carga habitaciones, temporadas, tarifas y servicios solo desde el panel React.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 201 | Tipos de habitación | Desarrollado | 100 % | Nada: requisito cerrado. |
| 202 | Galería por tipo de habitación | Desarrollado | 100 % | Nada: requisito cerrado. |
| 203 | Habitaciones | Desarrollado | 100 % | Nada: requisito cerrado. |
| 204 | Bloqueos de habitación | Desarrollado | 100 % | Nada: requisito cerrado. |
| 205 | Temporadas | Desarrollado | 100 % | Nada: requisito cerrado. |
| 206 | Tarifas por tipo y temporada | Desarrollado | 100 % | Nada: requisito cerrado. |
| 207 | Categorías de servicio | Desarrollado | 100 % | Nada: requisito cerrado. |
| 208 | Catálogo de servicios | Desarrollado | 100 % | Nada: requisito cerrado. |
| 209 | Perfil del usuario | Desarrollado | 100 % | Nada: requisito cerrado. |

### Fase 3: Motor de disponibilidad

Construir la regla central del proyecto: impedir la sobreventa de forma comprobable.

**Tareas**

- [x] Servicio de precio: calcular cada noche según temporada y tarifa, y guardar el detalle en reserva_noches.
- [x] Servicio de disponibilidad con intervalos semiabiertos, considerando reservas activas y bloqueos.
- [x] Crear la reserva dentro de una transacción con lockForUpdate sobre las filas consultadas.
- [x] Generar el código de confirmación único.
- [x] API pública GET /disponibilidad con sugerencia de fechas o tipos alternativos.
- [x] Buscador en la página de inicio y pantalla de resultados con filtros.
- [x] Prueba de concurrencia real: dos solicitudes simultáneas por la misma habitación, sobre MariaDB.
- [x] Serializar también bloqueos y cambios de habitación con la misma fila bloqueada.

**Terminada cuando:** La prueba de concurrencia confirma una sola reserva creada; salida y entrada el mismo día sí se permiten.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 301 | Cálculo de precio por noche | Desarrollado | 100 % | Nada: requisito cerrado. |
| 302 | Validación de traslape con bloqueo | Desarrollado | 100 % | Nada: requisito cerrado. |
| 303 | API de disponibilidad y alternativas | Desarrollado | 100 % | Nada: requisito cerrado. |
| 304 | Buscador y pantalla de resultados | Desarrollado | 100 % | Nada: requisito cerrado. |
| 305 | Código de confirmación | Desarrollado | 100 % | Nada: requisito cerrado. |

### Fase 4: Reserva en línea y Mi cuenta

Que el huésped complete la reserva y consulte su historial sin ayuda del personal.

**Tareas**

- [x] Registro completo: nombres, apellidos, teléfono y consentimiento.
- [x] Verificación de correo por enlace, adaptada al portal React.
- [x] Pantalla de reserva con datos del huésped, servicios y resumen de alojamiento, impuestos y anticipo.
- [x] Activar el botón Reservar del portal, hoy deshabilitado.
- [x] Mi cuenta: historial, estado y detalle de reservas.
- [x] Solicitud de cancelación aplicando la política definida.

**Terminada cuando:** Un huésped crea su cuenta, reserva y cancela de punta a punta; no puede ver reservas de otros.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 401 | Registro completo del huésped | Desarrollado | 100 % | Nada: requisito cerrado. |
| 402 | Verificación de correo | Desarrollado | 100 % | Nada: requisito cerrado. |
| 403 | Formulario de reserva con resumen | Desarrollado | 100 % | Nada: requisito cerrado. |
| 404 | Mi cuenta: mis reservas | Desarrollado | 100 % | Nada: requisito cerrado. |
| 405 | Solicitar cancelación | Desarrollado | 100 % | Nada: requisito cerrado. |

### Fase 5: Pago, comprobante y correos

Completar el flujo documentado del caso de uso «Reservar en línea» hasta la confirmación.

**Tareas**

- [x] Tabla pagos e integración con la pasarela en ambiente de pruebas, con llaves en .env y config/services.php.
- [x] Procesar la respuesta de la pasarela sin duplicar pagos; la reserva se confirma solo si el anticipo se aprueba.
- [x] Generar el comprobante PDF y guardarlo en comprobantes.
- [x] Código QR con qr_token y verificación en recepción.
- [x] Correos en cola al confirmar, modificar y cancelar, registrados en notificaciones.

**Terminada cuando:** El flujo completo termina con pago de prueba aprobado, reserva confirmada, PDF con QR y correo enviado; un pago rechazado no confirma.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 501 | Pago de anticipo en sandbox | Desarrollado | 100 % | Nada: requisito cerrado. |
| 502 | Comprobante PDF | Desarrollado | 100 % | Nada: requisito cerrado. |
| 503 | Código QR de verificación | Desarrollado | 100 % | Nada: requisito cerrado. |
| 504 | Correos de la reserva | Desarrollado | 100 % | Nada: requisito cerrado. |

### Fase 6: Operación de recepción

Sustituir el cuaderno de recepción por el módulo administrativo.

**Tareas**

- [x] Listado y búsqueda de reservas por código, documento o correo.
- [x] Reserva telefónica o presencial, con búsqueda o alta del huésped.
- [x] Confirmar, modificar y cancelar, revalidando disponibilidad y registrando reserva_historial.
- [x] Check-in por código o QR.
- [x] Check-out con servicios, saldo y pago recibido.
- [x] Panel de control con ocupación, reservas activas, entradas del día e ingresos.
- [x] Calendario mensual de ocupación por habitación.

**Terminada cuando:** El recepcionista atiende un ciclo completo, de la reserva al check-out, sin salir del panel y con permisos verificados en el servidor.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 601 | Listado y búsqueda de reservas | Desarrollado | 100 % | Nada: requisito cerrado. |
| 602 | Reserva telefónica o presencial | Desarrollado | 100 % | Nada: requisito cerrado. |
| 603 | Confirmar, modificar y cancelar | Desarrollado | 100 % | Nada: requisito cerrado. |
| 604 | Registrar entrada (check-in) | Desarrollado | 100 % | Nada: requisito cerrado. |
| 605 | Registrar salida (check-out) | Desarrollado | 100 % | Nada: requisito cerrado. |
| 606 | Panel de control operativo | Desarrollado | 100 % | Nada: requisito cerrado. |
| 607 | Calendario de ocupación | Desarrollado | 100 % | Nada: requisito cerrado. |

### Fase 7: Reportes, valoraciones y respaldos

Entregar la información gerencial y la continuidad comprometidas en el alcance.

**Tareas**

- [ ] Reporte de ocupación e ingresos con ADR y RevPAR, filtrable por período y tipo.
- [ ] Servicios más solicitados y promedio de valoraciones.
- [ ] Exportación del reporte a PDF.
- [ ] Valoración de la estadía por el huésped y por servicio, solo con estadía finalizada.
- [ ] Moderación y respuesta del hotel; publicación de aprobadas y promedios en el sitio.
- [ ] Módulo de respaldos: tabla, generación manual, listado y tarea programada diaria.

**Terminada cuando:** Los totales del reporte cuadran con un juego de datos calculado a mano; un respaldo generado se restaura correctamente.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 701 | Reporte de ocupación e ingresos | Falta | 0 % | Agregaciones, ADR y RevPAR, con gráficos. |
| 702 | Servicios y valoraciones en reportes | Falta | 0 % | Servicios más solicitados y promedios. |
| 703 | Exportar reporte a PDF | Falta | 0 % | Botón Exportar PDF. |
| 704 | Valorar la estadía | Falta | 0 % | Tablas valoraciones y valoracion_servicios, con su pantalla. |
| 705 | Moderar y responder valoraciones | Falta | 0 % | Aprobar, rechazar y responder. |
| 706 | Publicar valoraciones y promedios | Falta | 0 % | Mostrar aprobadas y promedio en el sitio. |
| 707 | Respaldos desde el panel | Falta | 0 % | Tabla respaldos, generación, listado y tarea programada. |

### Fase 8: Sitio público y bilingüe

Completar el sitio en React y ofrecerlo en español e inglés.

**Tareas**

- [ ] Pasar a React la presentación, la galería, los servicios y el contacto que hoy solo existen en Blade.
- [ ] Crear galeria_imagenes para administrar la galería general.
- [ ] Internacionalización ES/EN con selector de idioma y contenido desde los campos *_en.
- [ ] Publicar aviso de privacidad y consentimiento según el Decreto 144.
- [ ] Revisar el diseño responsivo en teléfono, tableta y computadora.

**Terminada cuando:** El flujo de reserva completo funciona en ambos idiomas y a 400 px de ancho.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 801 | Catálogo de habitaciones en el portal | Desarrollado | 92 % | Pruebas de GET /tipos-habitacion. |
| 802 | Galería, servicios y contacto | Parcial | 32 % | Pasarlos a React y crear galeria_imagenes. |
| 803 | Portal bilingüe | Falta | 0 % | Selector ES/EN, textos traducidos y campos *_en. |
| 804 | Diseño responsivo verificado | Parcial | 33 % | Revisión en dispositivos y corrección de pantallas nuevas. |
| 805 | Aviso de privacidad y consentimiento | Falta | 0 % | Aviso y aceptación al registrarse. |

### Fase 9: Cierre y puesta en producción

Desplegar, validar con usuarios y entregar la documentación final.

**Tareas**

- [ ] Retirar el panel Blade heredado, Ejemplos incluido, y mover /queue_control si se conserva.
- [ ] Compilar el front con npm run build, servirlo con HTTPS y programar los respaldos.
- [ ] Cambiar usuarios de prueba y separar los datos de demostración de los seeders de producción.
- [ ] Planificar la actualización de Laravel: el soporte de seguridad de la versión 12 termina el 24 de febrero de 2027.
- [ ] Ejecutar pruebas funcionales y rondas de usabilidad con cinco personas.
- [ ] Actualizar informe y manuales al estado real; entregar script SQL y capacitación por rol.

**Terminada cuando:** Portal en producción con HTTPS, pruebas de aceptación firmadas y manuales coherentes con el código.

| N.º | Requisito | Estado | Avance | Falta |
|---|---|---|---:|---|
| 901 | Retirar el panel Blade heredado | Parcial | 50 % | Roles, usuarios, sesiones, tipos, habitaciones, ejemplos y colas. |
| 902 | Producción con HTTPS | Parcial | 35 % | Servir dist, HTTPS, cortafuegos y tarea de respaldos. |
| 903 | Actualización de Laravel | Falta | 0 % | Actualizar antes del 24 de febrero de 2027. |
| 904 | Pruebas funcionales y de aceptación | Falta | 0 % | Escenarios críticos y rondas con usuarios reales. |
| 905 | Documentación y script SQL | Parcial | 50 % | Actualizarlos al sistema final y generar el script SQL. |
| 906 | Capacitación y puesta en marcha | Falta | 0 % | Carga inicial, operación en paralelo y capacitación por rol. |

## Decisiones que el equipo debe tomar

| Tema | Pregunta | Sugerencia |
|---|---|---|
| Anticipo | ¿Qué porcentaje se cobra? El informe lo exige, pero no fija el valor. | Aprobado: 30 %, editable en Configuración. |
| Impuestos | ¿Qué impuestos y tasas se aplican al resumen? | Aprobado: 13 %, editable en Configuración. |
| Cancelación | ¿Se devuelve el anticipo? ¿Cuándo vence una reserva pendiente? ¿Qué pasa con un no-show? | Aprobado: reembolso total hasta 48 h antes de la hora de entrada, pendiente vence en 24 h y no-show sin reembolso. Cancelar dentro de las 48 h aplica el porcentaje de no-show. |
| Pasarela | El informe no nombra ninguna pasarela de pago. | Aplicado por ahora: solo la pasarela de pruebas interna (PASARELA_DRIVER=sandbox). Una pasarela real se conecta agregando un driver cuando el hotel tenga cuenta. |
| Precio | El diccionario trae tarifas.precio_noche y temporadas.multiplicador. ¿Cuál manda? | Aplicado: manda la tarifa si existe; si no, precio base por multiplicador. |
| Selección | ¿El huésped elige una habitación concreta o solo el tipo? | Aplicado: habitación concreta, revalidada en la transacción. |
| Fecha y equipo | Falta la fecha límite y cuántas personas pueden trabajar en paralelo. | Con eso se convierte la estimación en calendario. |

## Riesgos en lo ya construido

| Severidad | Riesgo | Detalle | Dónde |
|---|---|---|---|
| Media | Tu contenedor back migra al arrancar | El código se monta en la imagen, así que cada reinicio de Docker aplica las migraciones nuevas a la base de desarrollo. | `docker/entrypoint.sh` |
| Media | Datos de prueba en el arranque | Con la base vacía, el arranque crea usuarios de prueba con una contraseña conocida y reservas ficticias, también en producción. | `docker/entrypoint.sh:72` |
| Media | Fin de soporte de Laravel 12 | Los parches de seguridad terminan el 24 de febrero de 2027, en menos de 4 meses. | `Informe, recomendación 4.2.1` |

## Diferencias entre el informe y el código

| Tema | Detalle | Dónde |
|---|---|---|
| Las conclusiones hablan de cosas sin construir | El capítulo 4 presenta como logrados el control de concurrencia, los reportes, el PDF con QR y el canal de venta. El manual técnico 1.2 sí los marca como pendientes. | `Informe, sección 4.1` |
| Conteo de entidades | El texto de 3.2.2 habla de 22 entidades; el diccionario detalla 21 tablas de dominio. El total de 39 tablas sí cuadra (21 + 18). | `Informe, secciones 3.2.2 y 3.2.4` |
| Formas de cobro más detalladas | El diccionario pone como ejemplo por unidad, por noche y por estadía. El código conserva por persona y noche, por persona, por noche y precio fijo, porque la alimentación se cobra por persona. | `Models/Servicio.php` |
| Tipo de estado_habitacion | El diccionario lo define como text. Se usa varchar(20) con los mismos tres valores, para poder filtrarlo e indexarlo. | `2026_11_04_000002` |
| Consentimiento en users | El diccionario no tiene dónde guardar el consentimiento. Se agregó users.privacidad_aceptada_at, que la ley pide para poder demostrarlo. | `2026_11_07_000001` |

## Fuera de alcance según el informe

No se planifican porque la sección 1.5.2 los excluye: cobros reales, administrador de canales, facturación electrónica, varios establecimientos o monedas, modo sin conexión, contabilidad y planillas, punto de venta, cerraduras electrónicas, migración automática de históricos y mantenimiento posterior a la entrega.
