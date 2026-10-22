# Diagnóstico de PaymentIntent en Railway

## Flujo y hallazgos

- `routes/api.php`: `POST /api/public/payments/intent`, limitado a 60 peticiones/minuto.
- `PublicPaymentController::createIntent`: valida código/token de reserva pública pendiente, reutiliza un pago pendiente o crea el intent con el SDK y después guarda `Payment`.
- `PaymentController::createIntent`: equivalente autenticado en `POST /api/payments/intent`.
- No había un servicio separado de creación. El SDK `stripe/stripe-php` se llama directamente desde ambos controladores.
- `config/services.php`: `STRIPE_SECRET_KEY` → `services.stripe.secret`. Los controladores usan `config()`, que es correcto con caché de configuración.
- No hay Dockerfile, Procfile, railway.json/toml ni nixpacks.toml versionados. `composer.json` no define build/inicio de Railway; `composer test` limpia configuración, pero no es un paso de despliegue. Deben revisarse los comandos efectivos en Settings y Build Logs del servicio.
- El canal general por defecto es `stack` → `single`, archivo `storage/logs/laravel.log`. Esto puede explicar logs ausentes en Railway, pero las variables desplegadas no se han inspeccionado.
- Se corrigió el registro de mensajes originales y su exposición cuando debug estaba activo. Los errores de creación ahora usan un canal JSON dedicado a `stderr`, independiente de `LOG_CHANNEL`/`LOG_LEVEL`.

No se ha demostrado la causa del fallo de producción. La ausencia de peticiones visibles en Stripe no distingue por sí sola entre configuración, autenticación, transporte, cuenta/modo equivocados o un fallo local. Incluso un fallo de persistencia puede ocurrir después de crear el intent.

## Ajustes y comprobaciones externas

En Railway, selecciona el **servicio API y entorno de producción** correctos:

1. Confirma que `STRIPE_SECRET_KEY` contiene la clave nueva de servidor, de la cuenta y modo live previstos, sin espacios, saltos de línea ni comillas literales. Si es una referencia compartida, comprueba que apunta a la variable actual. No pegues la clave en logs ni comandos de consola.
2. Configura `APP_ENV=production` y `APP_DEBUG=false`. Para ver también errores generales de Laravel, configura `LOG_CHANNEL=stderr` y `LOG_LEVEL=error`. El canal dedicado de pagos ya escribe errores en `stderr`.
3. Aplica los cambios de variables y despliega esta revisión. Revisa el build y el inicio efectivos: si se genera configuración durante el build, no debe quedar congelada antes de que estén disponibles las variables definitivas. Regenera la caché **en el contenedor que inicia la API**, con las variables de runtime disponibles, antes de iniciar el servidor:

   ```sh
   php artisan config:clear && php artisan config:cache
   ```

   Antepón esa secuencia al comando de inicio actual con `&&`; conserva el servidor, argumentos y puerto actuales. No se prescribe un servidor nuevo porque el comando desplegado no está en este repositorio. Un pre-deploy en otro contenedor no basta para modificar la caché del contenedor servidor. Un cambio de caché en una réplica tampoco actualiza otras réplicas: incorpora el paso al arranque de cada una y redespliega. Reinicia también procesos persistentes que consuman esa configuración.
4. Ejecuta en el contenedor desplegado (por ejemplo mediante `railway ssh`), sin imprimir configuraciones completas:

   ```sh
   php artisan payments:diagnose-config
   ```

   El comando no contacta Stripe. Solo devuelve booleanos/null: `secret_configured=true`, `secret_has_whitespace=false`, `debug_enabled=false`. En Railway se espera `runtime_secret_present=true` y `config_matches_runtime_secret=true`; un `false` en esta última comparación demuestra discrepancia entre la configuración cargada y la variable del proceso. `null` indica que no hay ambos valores comparables. `configuration_cached` indica si se usa caché; puede ser false y funcionar correctamente sin ella. El código de salida 0 solo valida estas comprobaciones locales, no la vigencia ni los permisos de la clave.

No uses `config:show services`, `env`, `printenv`, volcados de configuración ni comandos que impriman claves. No restaures la clave anterior. La clave del webhook es una configuración diferente: no se cambia por este diagnóstico.

Laravel documenta que la configuración cacheada conserva los valores resueltos hasta regenerarla: [caché de configuración](https://laravel.com/docs/12.x/configuration#configuration-caching). Railway captura stdout/stderr: [logs](https://docs.railway.com/observability/logs).

## Interpretar un fallo nuevo

Busca `Payment intent creation failed` en Deployment Logs, con hora y réplica correspondientes. Los campos son:

- `flow`: public/authenticated; `stage`: configuration, stripe_request o persist_payment.
- `exception_type`: clase de excepción; `message`: texto fijo seguro. Se descartan completamente el mensaje original, traza, excepción anterior, SQL y cuerpos de respuesta.
- Comprobaciones de configuración anteriores, sin claves, prefijos ni huellas.
- Para errores del SDK: `stripe_http_status`, `stripe_error_code` y `stripe_request_id` cuando existen. Código e ID solo se conservan si cumplen formatos acotados; no se registran cabeceras completas. `null` no demuestra que Stripe no recibió la petición.

`configuration` con clave ausente indica un fallo local; compara variable/caché. `AuthenticationException`/401 orienta a clave inválida, vencida o incorrecta. `PermissionException`/403 requiere revisar el error y los permisos concretos de PaymentIntents de esa clave; no amplíes permisos globalmente sin evidencia. `ApiConnectionException` orienta a DNS, TLS, red o timeout; revisa conectividad sin desactivar verificación TLS. `persist_payment` indica que Stripe ya devolvió un intent: revisa esquema/conexión de base de datos y concilia el intent en Stripe antes de repetir para evitar duplicados. [Errores HTTP de Stripe](https://docs.stripe.com/api/errors).

## Prueba manual de creación, sin cobro

Esta prueba queda pendiente para el operador; durante el diagnóstico local no se hicieron peticiones reales a Stripe.

1. En una sesión privada de tu cliente API, usa una reserva pública nueva, pendiente y sin pago previo, con su código y token válidos. Ten en cuenta que el comando `reservations:expire-unpaid` puede cancelarla al cumplir cinco minutos si está programado. No borres pagos anteriores para forzar la prueba. Una reserva de prueba puede bloquear disponibilidad temporalmente.
2. Envía una sola petición `POST https://TU-API/api/public/payments/intent`, `Content-Type: application/json`, con estos únicos campos (sustituye los marcadores en privado):

   ```json
   {
     "reservation_id": "CODIGO_PUBLICO",
     "confirmation_token": "TOKEN_PRIVADO_DE_LA_RESERVA"
   }
   ```

   No envíes `confirm`, `payment_method` ni datos de tarjeta. No uses el frontend que confirma el pago automáticamente. El backend crea el intent sin confirmar; no ejecutes después `confirmPayment` ni endpoints de confirmación de Stripe.
3. Espera HTTP **201** con `payment_intent_id`. La respuesta contiene `client_secret`: trátalo como secreto, no lo publiques ni exportes la respuesta completa a logs o capturas. HTTP **200** significa reutilización de un pago existente y **no prueba la nueva clave**.
4. En la cuenta y modo correctos del Dashboard de Stripe, comprueba el ID nuevo y estado `requires_payment_method`, sin método de pago ni cargo. La reserva y el registro local de pago deben seguir `pending`. Si falla, utiliza los campos seguros del nuevo log; no compartas el cuerpo completo de la petición/respuesta.

Crear un PaymentIntent sin confirmar no realiza el pago: [API de creación](https://docs.stripe.com/api/payment_intents/create). Esta prueba **no verifica por sí sola el webhook, su firma, la confirmación del pago ni la confirmación de una reserva**. Esos flujos requieren una validación separada, preferiblemente en un entorno de pruebas aislado.

## Pruebas locales sin secretos ni red

`StripePaymentDiagnosticsTest` sustituye el cargador de `.env`, evita cachés locales con credenciales, usa SQLite en memoria y reemplaza el transporte HTTP de Stripe por mocks que rechazan llamadas inesperadas. Ejecutar directamente PHPUnit evita que Artisan arranque antes de esa protección:

```sh
php vendor/phpunit/phpunit/phpunit tests/Feature/StripePaymentDiagnosticsTest.php
```

Si las extensiones SQLite están instaladas pero deshabilitadas en PHP local:

```sh
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/Feature/StripePaymentDiagnosticsTest.php
```
