# Tarifas semanales por cabaña

Cada cabaña tiene `weekly_prices` con siete tarifas por noche. Se cobra desde el
día de entrada hasta el día anterior a la salida. El día de salida no se cobra.
Las tarifas se repiten cada semana, sin reglas de temporadas ni descuentos por
mínimo de noches.

## Crear o editar tarifas

Usar los endpoints existentes `POST /api/cabins`, `PUT /api/cabins/{id}` o
`PATCH /api/cabins/{id}`, con los permisos habituales para crear o editar cabañas.
Al crear, también se requieren los campos habituales: `name`, `capacity`, `beds`,
`bathrooms` y `status`.

Ejemplo de edición:

```http
PATCH /api/cabins/1
Content-Type: application/json
Authorization: Bearer <token>
```

```json
{
  "weekly_prices": {
    "monday": 10,
    "tuesday": 10,
    "wednesday": 10,
    "thursday": 10,
    "friday": 20,
    "saturday": 20,
    "sunday": 10
  }
}
```

Cuando se envía `weekly_prices`, se requieren los siete días, escritos en inglés
y minúsculas. No se permiten claves adicionales, valores nulos, negativos,
valores mayores que 999999.99 ni más de dos decimales. Cero es una tarifa válida.
Para cambiar un solo día, enviar el mapa completo con ese día actualizado.

Las respuestas administrativas y las listas y detalles públicos incluyen
`weekly_prices`. En una cabaña, `price_per_night` conserva el precio mínimo de la
semana para compatibilidad y puede mostrarse como «desde». No sirve para
multiplicar todas las noches de una estancia.

Los clientes antiguos pueden seguir creando o editando con `price_per_night`
sin `weekly_prices`: esto establece esa misma tarifa para los siete días. Si se
envían ambos campos, prevalece `weekly_prices`. Editar otros datos de la cabaña
sin enviar precios conserva las tarifas.

## Cotizar y reservar

Los campos de fechas aceptan exclusivamente `YYYY-MM-DD`:

- `POST /api/cabins/{id}/price`: `check_in` y `check_out`, autenticado.
- `GET /api/public/reservations/availability`: `cabin_id`, `start_date` y `end_date`.
- Reservas públicas y administrativas: `start_date` y `end_date`.

Ejemplo de cotización de jueves 8 a sábado 10 de octubre de 2026:

```json
{
  "total": 30,
  "price_per_night": null,
  "average_price_per_night": 15,
  "nights": 2,
  "nightly_prices": [
    { "date": "2026-10-08", "day": "thursday", "price": 10 },
    { "date": "2026-10-09", "day": "friday", "price": 20 }
  ]
}
```

De jueves a viernes, el total sería 10 por una noche. Los totales se suman en
centavos para evitar errores de acumulación de decimales.

En cotizaciones y confirmaciones, `price_per_night` es la tarifa común cuando
todas las noches cuestan lo mismo, o `null` cuando son distintas.
`average_price_per_night` es el promedio, redondeado a dos decimales, solo para
mostrarlo; usar el total calculado por la API para cobrar.

Disponibilidad devuelve `total_price`, `total_days`, `nightly_prices`,
`price_per_night` y `average_price_per_night`. Cada nueva reserva guarda
`nightly_prices` junto con `total_price`; el desglose se devuelve también en la
creación pública y la confirmación. Cambiar tarifas después de reservar no
modifica esos valores guardados. Pagos continúan usando `total_price`.

## Migración y reglas anteriores

Aplicar la migración antes de utilizar el código actualizado:

```shell
php artisan migrate
```

La migración inicializa los siete días de las cabañas existentes con su
`price_per_night` actual. Las reglas antiguas no se convierten a tarifas semanales:
se deben configurar los precios deseados de cada cabaña. Se conserva la tabla
`cabin_price_rules` como datos históricos, pero deja de consultarse.

Se retiran los endpoints:

- `GET /api/cabins/{id}/price-rules`
- `POST /api/cabins/{id}/price-rules`
- `PUT /api/price-rules/{id}`
- `DELETE /api/price-rules/{id}`

Las respuestas ya no incluyen `price_rules` ni `rule_applied`.
Las reservas anteriores conservan su total, con `nightly_prices: null` porque no
se conoce el desglose histórico. Su confirmación obtiene el precio por noche
del total guardado y el número de noches, sin usar las tarifas actuales.

La reversión de la migración elimina los campos nuevos; no restaura las tarifas
anteriores modificadas después de migrar ni los desgloses de reservas nuevas.

## Verificación

```shell
php artisan test
```

Si SQLite está instalado pero desactivado en PHP de Windows, habilitarlo solo
para la ejecución de pruebas:

```shell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit
```

Las pruebas cubren fechas de salida, semanas completas, tarifas cero y decimales,
validación de tarifas, cotizaciones, reservas públicas y administrativas,
conservación de precios históricos y migración de cabañas existentes.
