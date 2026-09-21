# Despliegue en Plesk

Destino: `/var/www/vhosts/portada.info/prensa.portada.info`, PHP 8.3,
usuario `portada.info_y80x4y4qh3m`, SSH puerto 22.
La raíz web en Plesk debe ser `prensa.portada.info/public`, con certificado HTTPS válido.

## Preparación única

El puerto SSH está fijado en 22 en el flujo; no necesita un secreto.
Configurar los secretos de Actions: SSH_HOST, SSH_USER,
SSH_PRIVATE_KEY, SSH_KNOWN_HOSTS, DEPLOY_PATH.
SSH_KNOWN_HOSTS debe contener la clave pública del host obtenida de una
conexión de confianza y verificada con el servidor; no la clave del usuario.

Crear `.env` en el destino a partir de `.env.production.example`, con permisos 600.
Rellenar las credenciales reales de la base de datos. APP_KEY se genera solo si
está vacía. El ejemplo guarda documentos en almacenamiento local privado;
para usar S3 configurar PRESS_RELEASES_DISK=press-releases-s3 y las variables
AWS de config/filesystems.php. Para Gmail consultar docs/gmail-api.md.
No copiar credenciales de desarrollo al repositorio.

## Ejecución

Subir los archivos a main activa el despliegue. También se puede ejecutar desde
Actions → Desplegar prensa.portada.info → Run workflow (rama main).
Se compilan assets en GitHub y se instala Composer en el servidor usando PHP 8.3.
Las migraciones se ejecutan automáticamente, sin seeders. Después del primer
éxito, ejecutar en el servidor, dentro de la carpeta del proyecto:

```sh
/opt/plesk/php/8.3/bin/php artisan app:create-admin
```

La base de datos local no se importa automáticamente. El ejemplo usa colas
síncronas; la importación periódica de correo necesita configuración separada.

## Actualizaciones y fallos

El despliegue es en la misma carpeta y tiene una ventana de mantenimiento.
Rsync elimina archivos de código obsoletos; conserva .env, storage, vendor,
bootstrap/cache, bases SQLite, public/storage y public/.well-known.
Solo debe utilizarse esta carpeta para esta aplicación.
Crear copias de base de datos y storage en Plesk antes de actualizar.
No hay rollback automático: si falla tras activar mantenimiento, permanece
cerrada hasta corregir el problema y repetir el flujo. No ejecutar artisan up
hasta que Composer, migraciones y optimización hayan terminado correctamente.
La comprobación /up verifica el arranque HTTP, no las integraciones Gmail/S3.
