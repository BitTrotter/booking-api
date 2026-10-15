# Instrucciones para frontend: precios por fechas especiales

Implementar la administración de tarifas por fecha dentro del detalle o edición
de cada cabaña y adaptar la presentación de cotizaciones al desglose de la API.
La funcionalidad del backend está descrita en [special-prices.md](special-prices.md).
Antes de integrar, el backend debe tener aplicada la migración correspondiente.

## Comportamiento esperado

- Una tarifa especial pertenece a una cabaña y una fecha completa, incluido el año.
- `2026-12-25` representa la noche del 25 al 26 de diciembre de 2026.
- Solo esa noche usa el importe especial. El resto conserva sus tarifas semanales.
- Las excepciones no se repiten anualmente y no modifican `weekly_prices`.
- La fecha de salida queda fuera del cobro.
- Cambiar o eliminar una excepción no modifica las reservas ya creadas.

## Pantalla administrativa

Agregar una sección «Precios por fechas especiales» en una cabaña existente.
Mostrar una tabla ordenada por fecha con columnas Fecha, Precio por noche y
Acciones. Incluir «Agregar fecha», «Editar» y «Eliminar tarifa especial».
Si la lista está vacía, mostrar «Esta cabaña no tiene tarifas especiales».

El formulario necesita dos campos:

| Campo | Entrada | Validación |
| --- | --- | --- |
| Fecha | Selector de fecha | Obligatoria, fecha real, enviar `YYYY-MM-DD` |
| Precio por noche | Importe | Obligatorio, entre 0 y 999999.99, máximo dos decimales |

Mostrar la ayuda: «Este precio se aplica únicamente a la noche de la fecha
seleccionada. Las demás noches conservan su tarifa habitual».
Aceptar cero como precio válido; comprobar el campo vacío antes de convertirlo
a número. No enviar símbolos monetarios, separadores de miles ni coma decimal.
Mantener la fecha como cadena; evitar conversiones UTC con `toISOString()` que
puedan desplazar el día seleccionado. La API permite administrar fechas pasadas.

Al editar una excepción, mantener su fecha fija y modificar el importe. Para
moverla a otro día, el usuario debe agregar la nueva fecha y eliminar la anterior;
son operaciones independientes. Si se agrega una fecha que ya existe, avisar que
guardar reemplazará el precio actual: PUT actualiza esa fecha sin duplicarla.

## Endpoints y permisos

Usar la URL base y el cliente HTTP existentes. Las rutas administrativas requieren:

```http
Authorization: Bearer <token>
Accept: application/json
```

Para PUT, agregar `Content-Type: application/json`.

| Acción | Método y ruta | Permiso |
| --- | --- | --- |
| Listar | `GET /api/cabins/{cabin_id}/special-prices` | `show_cabin_details` |
| Crear o actualizar | `PUT /api/cabins/{cabin_id}/special-prices/{date}` | `edit_cabin` |
| Eliminar | `DELETE /api/cabins/{cabin_id}/special-prices/{date}` | `edit_cabin` |

Mostrar las acciones de escritura únicamente a usuarios con `edit_cabin`.
La autorización definitiva la realiza el backend. No usar los endpoints antiguos
`price-rules` ni enviar excepciones dentro de `weekly_prices`.

### Listar

```http
GET /api/cabins/1/special-prices
```

La respuesta HTTP 200 es un arreglo directo, sin contenedor `data` y sin paginación.
Ejemplo abreviado, omitiendo timestamps:

```json
[
  {
    "id": 12,
    "cabin_id": 1,
    "date": "2026-12-25",
    "price_per_night": 1800
  }
]
```

Una lista vacía devuelve `[]`. Estos registros se consultan por este endpoint;
las respuestas actuales de lista y detalle de cabañas no incluyen las excepciones.

### Crear o editar

```http
PUT /api/cabins/1/special-prices/2026-12-25
```

```json
{ "price_per_night": 1800 }
```

La fecha se envía en la URL, no en el cuerpo. Responde 201 al crear o 200 al
actualizar. En ambos casos devuelve el objeto guardado, sin contenedor `data`.
Identificar las operaciones por cabaña y fecha; el `id` del registro no se usa
en la ruta. No existe un POST o PATCH para estas excepciones.

Deshabilitar Guardar mientras se procesa la solicitud. Tras el éxito, cerrar el
formulario, actualizar la lista y avisar «Tarifa especial guardada». Conservar
los campos si la petición falla.

### Eliminar

```http
DELETE /api/cabins/1/special-prices/2026-12-25
```

No requiere cuerpo. Responde HTTP 200:

```json
{ "message": "Special price deleted" }
```

La interfaz puede pedir confirmación con el texto: «¿Eliminar la tarifa especial
del 25 de diciembre de 2026? Esa noche volverá a usar la tarifa semanal para
nuevas reservas». Tras el éxito, actualizar la lista.

## Cotización y reserva

El frontend debe tomar los totales y el desglose calculados por la API. Consultar
otra vez al cambiar de cabaña, entrada o salida; mostrar un estado de carga y evitar
reservar con una cotización de otra selección. Descartar respuestas tardías de
solicitudes anteriores, usando cancelación o un identificador de solicitud.

### Disponibilidad pública

```http
GET /api/public/reservations/availability?cabin_id=1&start_date=2026-12-24&end_date=2026-12-27
```

No requiere token. Ejemplo con tarifa normal de 1000 y excepción de 1800 el día 25:

```json
{
  "available": true,
  "cabin_id": 1,
  "start_date": "2026-12-24",
  "end_date": "2026-12-27",
  "total_days": 3,
  "total_price": 3800,
  "price_per_night": null,
  "average_price_per_night": 1266.67,
  "nightly_prices": [
    { "date": "2026-12-24", "day": "thursday", "price": 1000 },
    { "date": "2026-12-25", "day": "friday", "price": 1800 },
    { "date": "2026-12-26", "day": "saturday", "price": 1000 }
  ]
}
```

Usar `available` para permitir continuar. La existencia de un precio en la
respuesta no significa que la cabaña esté disponible.

### Cotización administrativa

```http
POST /api/cabins/1/price
```

```json
{ "check_in": "2026-12-24", "check_out": "2026-12-27" }
```

Requiere token y permiso `show_cabin_details`. Devuelve `total` y `nights` en lugar
de `total_price` y `total_days`, junto con `price_per_night`,
`average_price_per_night` y `nightly_prices`. Este endpoint solo cotiza; no
comprueba disponibilidad.

### Presentación de importes

- Mostrar una fila por noche usando `nightly_prices[].date` y `.price`.
- Si `price_per_night` es `null`, indicar «Tarifa variable por noche»; si es cero,
  mostrar cero. No usar comprobaciones de veracidad para distinguir estos casos.
- Etiquetar `average_price_per_night` como «Promedio por noche», si se muestra.
- Usar el total de la API para la estancia; no multiplicar el precio base ni el
  promedio por el número de noches.
- Los precios de las listas de cabañas conservan el mínimo semanal. No incluyen
  excepciones más baratas y no garantizan el importe para fechas seleccionadas.
- El desglose no incluye `is_special`, `rule_applied` ni un tipo de tarifa. En la
  vista pública, mostrar los importes sin inferir una etiqueta «Especial» por su
  diferencia frente a otra noche. No consultar endpoints administrativos allí.

Los endpoints y cuerpos existentes para crear reservas no cambian. Enviar los
datos habituales de cabaña, fechas y huésped; el backend calcula los precios.
La creación pública responde con el resultado dentro de `data`; la administrativa
individual devuelve el objeto directo. Las reservas por lote devuelven
`reservations`, con el total y desglose de cada una.

Después de crear la reserva, usar su total devuelto como importe definitivo:
una cotización previa no congela las tarifas. La confirmación y las vistas de
reservas deben mostrar el desglose guardado, sin recalcularlo con tarifas actuales.
En reservas antiguas, `nightly_prices` puede ser `null`; mostrar el total guardado
sin intentar reconstruir las noches con los precios actuales.

Si el administrador cambia una tarifa mientras tiene abierta una nueva reserva,
invalidar la cotización y solicitar una nueva. Para reservas de varias cabañas,
usar el total y anticipo asignado de cada reserva según
[admin-reservation-groups.md](admin-reservation-groups.md).

## Manejo de errores

| Código | Comportamiento del frontend |
| --- | --- |
| 401 | Aplicar el flujo existente de sesión vencida o autenticación |
| 403 | Mostrar que el usuario no tiene permiso para la acción |
| 404 | Actualizar la lista; la cabaña o la excepción puede haber sido eliminada |
| 422 | Mostrar errores junto a los campos `date` o `price_per_night` |
| 429 | Mostrar que debe esperar antes de volver a consultar |
| 500 o fallo de red | Conservar el formulario y permitir reintentar |

Los errores 422 usan el formato de validación de Laravel, por ejemplo:

```json
{
  "message": "...",
  "errors": {
    "price_per_night": ["..."]
  }
}
```

Renderizar los mensajes de `errors` sin depender del idioma ni del texto exacto
de `message`. Un fallo al cargar la lista debe mostrar un estado de error, no
el mensaje de lista vacía.

## Criterios de aceptación

1. Crear una excepción para el 25/12/2026 y verla al volver a abrir la cabaña.
2. Editar su precio sin generar dos filas para la misma fecha.
3. Guardar cero; rechazar importes vacíos, negativos y con más de dos decimales.
4. Cotizar del 24 al 27 con precios 1000, 1800, 1000 y total 3800.
5. Cotizar del 24 al 25 y cobrar solo la noche del 24.
6. Confirmar que otra cabaña y el 25/12/2027 conservan sus tarifas normales.
7. Eliminar la excepción y comprobar que una nueva cotización usa la tarifa semanal.
8. Verificar que una reserva previa conserva su total y desglose tras editar o borrar.
9. Mostrar tarifa variable cuando `price_per_night` sea `null`, sin errores de formato.
10. Respetar los permisos y mostrar correctamente los estados de carga y error.
11. Cambiar rápidamente las fechas y comprobar que se muestra la última cotización.
12. Comprobar creación pública, administrativa individual y por lote con los totales
    devueltos por el backend.
