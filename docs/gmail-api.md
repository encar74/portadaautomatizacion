# Configuración de Gmail API

La integración utiliza OAuth 2.0 con acceso offline y el scope:

```text
https://www.googleapis.com/auth/gmail.modify
```

Este scope permite leer mensajes y añadir la etiqueta que impide importarlos de nuevo. La aplicación no elimina mensajes, no los mueve a la papelera y no modifica su estado leído/no leído.

## Preparación en Google Cloud

1. Crear o seleccionar un proyecto en Google Cloud Console.
2. Habilitar **Gmail API**.
3. Configurar la pantalla de consentimiento OAuth.
4. Crear un cliente OAuth 2.0.
5. Autorizar la cuenta de recepción con `access_type=offline` y el scope `gmail.modify`.
6. Obtener el `refresh_token` y guardarlo únicamente en `.env`.

Google solo devuelve normalmente el refresh token durante el primer consentimiento offline. Si ya se autorizó la aplicación sin acceso offline, puede ser necesario revocar el consentimiento anterior y autorizar de nuevo.

## Variables

```dotenv
MAILBOX_PROVIDER=gmail
GMAIL_CLIENT_ID=
GMAIL_CLIENT_SECRET=
GMAIL_REFRESH_TOKEN=
GMAIL_USER_ID=me
GMAIL_QUERY="in:inbox -label:portada-importado"
GMAIL_IMPORTED_LABEL=portada-importado
GMAIL_MAX_RESULTS=50
```

Después de modificar `.env`:

```bash
php artisan config:clear
php artisan press-releases:fetch --limit=50
```

## Comportamiento

- Gmail devuelve los IDs que coinciden con `GMAIL_QUERY` y con las fuentes activas configuradas. Sin fuentes activas no se consulta Gmail.
- Se comprueba también que el remitente coincide exactamente con una fuente activa antes de importar.
- La aplicación descarga el payload MIME estructurado y el mensaje RFC completo.
- El mensaje se persiste mediante `PressReleaseService`.
- Solo después de persistirlo correctamente se añade `GMAIL_IMPORTED_LABEL`.
- Si la etiqueta aún no existe, la aplicación la crea.
- Los mensajes fallidos no se etiquetan y podrán reintentarse.

## Importación programada en Plesk

`routes/console.php` programa `press-releases:fetch --limit=50` cada cinco minutos. El bloqueo `withoutOverlapping()` evita solapar ejecuciones del programador (no bloquea ejecuciones manuales directas). Usa una caché persistente, como `database` o `file`, compartida entre procesos. El bloqueo caduca a las 24 horas si el proceso termina abruptamente; tras comprobar que no queda ninguna importación en marcha, se puede liberar con `php artisan schedule:clear-cache`.

La salida se añade a `storage/logs/press-releases-fetch.log`; los errores reportados por Laravel también se registran según su configuración de logging. Configura la rotación del archivo de salida en el servidor.

Después de desplegar, comprueba la programación con `php artisan schedule:list`.

En Plesk, añade una sola tarea programada bajo el usuario del alojamiento, de tipo **Ejecutar un comando**, con frecuencia estilo cron `* * * * *`. Laravel decidirá qué minutos ejecutar la importación.

Obtén la ruta de PHP con `command -v php` en la terminal donde funciona la importación. Sustituye `/RUTA/ABSOLUTA/PHP` por ese resultado:

```bash
cd /var/www/vhosts/portada.info/prensa.portada.info && /RUTA/ABSOLUTA/PHP artisan schedule:run
```

Este comando presupone una shell sin chroot. Si Plesk ejecuta las tareas en chroot, configura la shell de tareas de la suscripción adecuadamente o adapta las rutas a ese entorno. No uses el usuario root para la tarea. Comprueba el comando con **Ejecutar ahora**; en minutos no múltiplos de cinco es normal que indique que no hay tareas pendientes.

No añadas además otra tarea que ejecute directamente `press-releases:fetch`, pues duplicaría la programación.

Documentación oficial:

- https://developers.google.com/workspace/gmail/api/guides/list-messages
- https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/get
- https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/modify
- https://developers.google.com/identity/protocols/oauth2/web-server
