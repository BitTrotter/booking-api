# Reservaciones vinculadas (admin)

Cada reservación mantiene su cabaña, folio, precio, abono y estado. El campo
`reservation_group_id` es un UUID compartido; `null` significa sin grupo.
El anticipo conjunto se distribuye en abonos individuales. Cancelar o editar
una no modifica las demás.

## Preparación

Ejecutar `php artisan migrate` para agregar el campo nullable e indexado.
Las reservaciones existentes permanecen sin grupo y conservan sus datos.

## Crear una reservación

`POST /api/reservations` conserva el contrato existente (`cabin_id` y
`guest_number`). Opcionalmente acepta `reservation_group_id` para vincularla
a otras con ese UUID. El UUID puede generarse en el frontend o copiarse de una
reservación del grupo. Los UUID se guardan en minúsculas.

## Crear varias en una solicitud

Enviar `cabins` en lugar de `cabin_id` y `guest_number`:

```json
{
  "start_date": "2026-12-01",
  "end_date": "2026-12-03",
  "full_name": "Ana Pérez",
  "phone": "5551234567",
  "payment_method": "cash",
  "send_email": false,
  "group_amount_paid": 800,
  "cabins": [
    { "cabin_id": 1, "guest_number": 4 },
    { "cabin_id": 2, "guest_number": 2 }
  ]
}
```

Debe haber al menos dos cabañas distintas. Fechas, contacto, método de pago,
referencia y notas son comunes. Los huéspedes se indican por cabaña.
`amount_paid` en la raíz se rechaza para evitar duplicar un abono.
Cada precio se calcula con las tarifas de su cabaña.

### Un solo anticipo para el lote

Enviar `group_amount_paid` en la raíz. El backend reparte el importe
proporcionalmente al precio de cada reservación y guarda cada parte en
`amount_paid`. Por ejemplo: totales de $3,000 y $2,000 con un anticipo de $1,000
resultan en abonos de $600 y $400.

El cálculo se hace en centavos. Los centavos sobrantes se asignan a las
reservaciones con mayor resto proporcional; los empates se resuelven en el
orden de `cabins`. La suma de los abonos coincide exactamente con el anticipo.

El importe debe ser no negativo, tener como máximo dos decimales, no superar
99,999,999.99 ni el total del lote. Una cabaña con precio cero recibe abono cero;
si todas son gratuitas, solo se acepta un anticipo de cero.

No mezclar `group_amount_paid` con importes no nulos en
`cabins.*.amount_paid`, ni siquiera cero: la API responde 422 para evitar
ambigüedad. Para conservar la captura individual, omitir `group_amount_paid`
(o enviarlo en `null`) y usar `amount_paid` dentro de cada cabaña como antes.

`group_amount_paid` es un dato de entrada, no una columna ni un nuevo pago.
La respuesta mantiene el formato y devuelve el abono asignado en cada
`reservations.*.amount_paid`. No requiere una migración adicional.

Se aplica únicamente a las reservaciones creadas en esa solicitud. Si se
indica un grupo existente, no redistribuye sus abonos anteriores.
Solo se admite al crear un lote; `PATCH`/`PUT` rechazan este campo. La edición
de `amount_paid` por reservación sigue disponible.

En el frontend del admin, capturar un solo “Anticipo del lote” y enviar
`group_amount_paid` sin abonos por cabaña. Mostrar el reparto devuelto por la
API. Para mostrar el total, anticipo y saldo del grupo, sumar los valores de
sus reservaciones una sola vez; no volver a sumar las listas anidadas de
`related_reservations`. Mostrar las canceladas por separado del saldo vigente.

Se genera un UUID para todas, salvo que se envíe `reservation_group_id` para
incorporarlas a un grupo. La respuesta HTTP 201 contiene:

```json
{
  "reservation_group_id": "a175eb6b-5a6f-4a9e-a3ac-2e7784886015",
  "reservations": [
    { "id": 101, "related_reservations": [{ "id": 102 }] },
    { "id": 102, "related_reservations": [{ "id": 101 }] }
  ]
}
```

El ejemplo abrevia los objetos: cada uno incluye sus campos habituales.
La creación individual sigue respondiendo con un objeto, sin este contenedor.
Si alguna cabaña está ocupada (409), excede su capacidad (422), o falla la
creación, no se guarda ninguna del lote. Si `send_email` es `true`, se requiere
`email` y se envía un correo por reservación, después de guardar el lote.

## Vincular reservaciones existentes

Enviar el mismo UUID a cada reservación mediante
`PATCH /api/reservations/{id}` (también se admite `PUT`):

```json
{ "reservation_group_id": "a175eb6b-5a6f-4a9e-a3ac-2e7784886015" }
```

Para desvincular una, enviar `{"reservation_group_id": null}`.
Asignar otro UUID mueve únicamente esa reservación: no fusiona grupos.
Se conserva la restricción existente que impide editar reservaciones canceladas.
Estas operaciones usan el permiso actual `edit_reservation`.

## Consultas

`GET /api/reservations` y `GET /api/reservations/{id}` incluyen
`related_reservations`: las otras reservaciones del grupo, con su cabaña y
pago. La relación funciona en ambas direcciones, excluye la propia reservación
y no anida otras listas de relacionadas. Incluye también las canceladas para
conservar el contexto del grupo. Sin grupo, devuelve `[]`.

El frontend del admin debe mostrar esta lista y admitir la selección de varias
cabañas para usar la creación en lote. La creación pública sigue siendo de una
sola cabaña; no admite asignar grupos. Sus consultas de disponibilidad reconocen
las reservaciones individuales creadas por el admin sin cambios en el pago público.
