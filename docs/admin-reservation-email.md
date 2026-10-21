# Correo de nuevas reservas al administrador

Configurar los destinatarios separados por comas en `.env`:

```dotenv
RESERVATION_ADMIN_EMAILS=admin@example.com,gerencia@example.com,recepcion@example.com
```

Laravel los lee mediante `config('mail.reservation_admin_emails')`. Se eliminan
espacios, entradas vacías y duplicados. No requiere
migraciones ni cambios en el frontend. Si la variable está vacía, no se envía
el aviso al administrador.

Se envía un correo independiente a cada destinatario, sin revelar las demás
direcciones. Si un envío falla, se intentan los restantes. La variable anterior
`RESERVATION_ADMIN_EMAIL` sigue funcionando si no se define
`RESERVATION_ADMIN_EMAILS`; la nueva variable tiene prioridad, incluso vacía.

Después de cambiar la variable en un despliegue con configuración cacheada,
ejecutar `php artisan config:cache`.

Se envía un correo independiente al administrador después de guardar una reserva
desde el sitio público o el panel. En una creación de varias cabañas se envía un
correo por reserva, incluyendo el identificador del grupo. El aviso contiene el
código de reserva, origen, contacto del huésped, cabaña, fechas, huéspedes, total
y estado. Las reservas públicas se notifican como pendientes; el aviso no implica
que el pago haya sido confirmado.

`send_email` sigue controlando únicamente el correo al cliente desde el panel.
El aviso al administrador se envía incluso si el cliente no tiene email.
El destinatario del administrador no aparece en el correo del cliente.

Se utiliza el transporte de correo existente (`MAIL_MAILER`, credenciales, etc.).
Con `MAIL_MAILER=log`, los mensajes se registran en logs en lugar de entregarse.
Los errores de envío se registran sin revertir la reserva ni impedir que se
intente enviar el otro correo.
