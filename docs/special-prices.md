# Tarifas especiales por fecha y cabaña

Una excepción asigna un precio a una noche de una cabaña en una fecha completa
`YYYY-MM-DD`. Por ejemplo, `2026-12-25` cambia únicamente la noche del 25 al 26
de diciembre de 2026. No se repite cada año ni cambia la tarifa semanal.
La fecha de salida no se cobra.

## Preparación

Antes de usar el código actualizado, ejecutar `php artisan migrate` para crear
`cabin_special_prices`. La tabla empieza vacía y tiene una restricción única por
cabaña y fecha. No se importan datos de la antigua tabla `cabin_price_rules`.

## Administración

Todos los endpoints requieren `Authorization: Bearer <token>`.

| Método | Endpoint | Permiso | Resultado |
| --- | --- | --- | --- |
| GET | `/api/cabins/{cabin}/special-prices` | `show_cabin_details` | Lista completa ordenada por fecha, HTTP 200 |
| PUT | `/api/cabins/{cabin}/special-prices/{date}` | `edit_cabin` | Crea (201) o reemplaza (200) el precio de esa fecha |
| DELETE | `/api/cabins/{cabin}/special-prices/{date}` | `edit_cabin` | Elimina la excepción (200); si no existe, 404 |

Crear o editar la tarifa navideña de la cabaña 1:

```http
PUT /api/cabins/1/special-prices/2026-12-25
Content-Type: application/json
Authorization: Bearer <token>
```

```json
{ "price_per_night": 1800 }
```

Respuesta de creación, con los timestamps omitidos aquí:

```json
{
  "id": 1,
  "cabin_id": 1,
  "date": "2026-12-25",
  "price_per_night": 1800
}
```

GET devuelve un arreglo de esos objetos, o `[]` si no hay excepciones.
Repetir PUT sobre la misma fecha actualiza el mismo registro; no crea duplicados.
La fecha va en la URL. El cuerpo requiere `price_per_night`, numérico entre cero
y 999999.99, con máximo dos decimales. Cero es válido. Fechas inválidas, campos
requeridos ausentes o importes inválidos devuelven 422. Se permiten fechas pasadas
para administrar registros históricos; eso no modifica reservas existentes.

DELETE restaura la tarifa semanal para futuras cotizaciones de esa noche.
Cada operación afecta únicamente la cabaña indicada. Eliminar una cabaña elimina
también sus excepciones. No se modifica `weekly_prices` ni el precio base.

## Cotizaciones y reservas

El calculador selecciona por cada noche, en este orden:

1. Tarifa especial de la cabaña para esa fecha exacta.
2. Tarifa semanal del día.
3. Precio base como respaldo.

Con precio normal de $1,000 y tarifa especial de $1,800 el 25, una estancia
del 24 al 27 de diciembre de 2026 cuesta $3,800. Su desglose es:

```json
[
  { "date": "2026-12-24", "day": "thursday", "price": 1000 },
  { "date": "2026-12-25", "day": "friday", "price": 1800 },
  { "date": "2026-12-26", "day": "saturday", "price": 1000 }
]
```

Se conserva el contrato de cotización y disponibilidad: `nightly_prices` contiene
los importes aplicados y `price_per_night` es `null` cuando varían las noches.
`average_price_per_night` es un promedio para mostrar; cobrar el total de la API.
Las cotizaciones y las reservas públicas, administrativas y por lote usan el
mismo calculador. Los anticipos del lote se reparten sobre los totales resultantes.

Cada reserva conserva su `total_price` y `nightly_prices`: crear, editar o borrar
excepciones después no cambia lo reservado. Los pagos usan el total guardado.
La disponibilidad cotiza las tarifas actuales; crear la reserva vuelve a calcular
el precio, por lo que una cotización no congela el importe.

## Integración del frontend

Guía de implementación, flujos y criterios de aceptación:
[frontend-special-prices.md](frontend-special-prices.md).

En el administrador, listar las excepciones por cabaña y capturar fecha e importe.
Guardar con PUT y quitar con DELETE. Mostrar los errores 422 junto al campo
correspondiente (`date` o `price_per_night`).

En la reserva pública y administrativa, usar disponibilidad o cotización para
mostrar total y desglose. Las listas y detalles de cabañas conservan los campos
actuales; `price_per_night` es el mínimo semanal y no incluye tarifas especiales
menores. No calcular la estancia multiplicando ese valor por las noches.

## Verificación

```shell
php artisan test
```

Si SQLite está desactivado en PHP de Windows:

```shell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit
```
