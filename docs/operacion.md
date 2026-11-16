# Operación, copias y transición

## Publicación del visor ciudadano

El [Blueprint de Render](../render.yaml) compila Laravel y Vite en un contenedor y permite levantar el servicio web con el plan gratuito de Render. Crea un proyecto propio de Neon con PostgreSQL 17 en la región de Ohio y configura en Render `DB_URL` con la conexión directa de esa base nueva, con `sslmode=require`. Render genera la clave de la aplicación. El contenedor ejecuta las migraciones y verifica el arranque en `/up`.

Para preparar el atlas desde la base local de UrbanTwin, exporta únicamente estas tablas, con las herramientas PostgreSQL en `PATH`:

~~~powershell
pg_dump -h 127.0.0.1 -p 55432 -U urbantwin -d urbantwin_v2_dev -Fc --data-only -t geo.departamentos -t geo.provincias -t geo.temas -t geo.zonas_tematicas -f atlas-publico.dump
~~~

Después del primer arranque de Render, configura las credenciales de **la base de Neon** en tu cliente PostgreSQL. Restaura en la base recién migrada:

~~~powershell
pg_restore --no-owner --no-privileges --single-transaction --exit-on-error -d urbantwin_web atlas-publico.dump
~~~

El comando supone que la base se llama `urbantwin_web` y que el cliente ya conoce el host, el usuario y la configuración SSL de Neon. El respaldo contiene solo departamentos, provincias y capas temáticas. El registro ciudadano crea cuentas nuevas en la base pública.

El plan gratuito de Render suspende el servicio por inactividad y su sistema de archivos es efímero. La publicación de otras capas con archivos descargables y la administración de cargas requieren almacenamiento privado persistente. [Render gratuito](https://render.com/docs/free) · [PostGIS en Neon](https://neon.com/docs/extensions/postgis).

## Despliegue propuesto

GitHub puede alojar el repositorio y GitHub Pages puede publicar la documentación estática. Para un enlace que permita al profesor iniciar sesión, usar el visor y consultar los datos, instala la aplicación en un servidor web con PHP y PostgreSQL/PostGIS. Puedes usar un servidor de la universidad o un VPS Linux. [GitHub Pages](https://docs.github.com/en/pages/getting-started-with-github-pages/creating-a-github-pages-site) · [Despliegue de Laravel 13](https://laravel.com/docs/13.x/deployment).

### Preparar una instancia pública para la revisión

1. **Entorno.** Usa PHP 8.4 con `pdo_pgsql`, Composer y Node para compilar el frontend. Prepara PostgreSQL 17 con PostGIS y un volumen persistente para `storage/app/private/originales`. El `compose.yaml` del repositorio trae los perfiles de desarrollo y pruebas. En el servidor crea una base nueva, usa credenciales propias y limita su conexión a la red privada.
2. **Configuración.** Parte de `.env.example` en la máquina de destino. Configura `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con el dominio HTTPS, `SESSION_SECURE_COOKIE=true` y `DB_CONNECTION=pgsql`. Ajusta `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. Crea una `APP_KEY` una sola vez para esa instancia y consérvala fuera del repositorio. En PHP, usa `upload_max_filesize=5M` y un `post_max_size` mayor para el formulario multipart.
3. **Compilación y base.** Ejecuta los comandos siguientes desde el directorio de la aplicación. La cuenta de migraciones debe poder crear esquemas. Si el proveedor administra la extensión, habilita PostGIS en la base antes de migrar.

~~~bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force --no-interaction
php artisan optimize --no-interaction
~~~

4. **Servidor web y contenido.** Configura Nginx con PHP-FPM, HTTPS y raíz en `public/`. Ejecuta las importaciones del [README](../README.md) para reproducir el atlas, las provincias y las huellas históricas. Conserva los archivos de origen en el volumen privado. Crea el primer administrador en esa instancia y habilita las plazas de superusuario mediante sus propias invitaciones.
5. **Acceso del profesor.** Envía el enlace HTTPS de la portada. El profesor puede crear su cuenta de consulta y empezar a probar. Para activar su plaza, el superusuario emite una invitación para su correo y el profesor la canjea desde su perfil. Usa cuentas creadas en la instancia pública. Las contraseñas iniciales de desarrollo y las bases de prueba quedan en el equipo local.
6. **Ensayo.** Comprueba `/up`, inicio de sesión, atlas, geolocalización con permiso, importación pequeña, descarga privada y generación de un reporte. Prueba un respaldo y su restauración en otra base antes de abrir el acceso. Las operaciones implementadas son síncronas.

La aplicación y sus datos se verificaron en Windows local. La instancia pública descrita sigue por desplegar.

## Respaldo y restauración

Detén las escrituras durante la captura de ambos componentes. Guarda la BD y los archivos en una ubicación de respaldo controlada, cifrada y distinta del volumen de trabajo. Usa credenciales gestionadas. Un ejemplo para la demo, con herramientas PostgreSQL disponibles en PATH, es el siguiente.

~~~powershell
pg_dump -h 127.0.0.1 -p 55432 -U urbantwin -d urbantwin_v2_dev -Fc -f urbantwin-v2.dump
tar -czf urbantwin-originales.tar.gz -C storage/app/private originales
~~~

Restaura primero en una base vacía y un directorio privado nuevo. Asegúrate de que el servidor de destino dispone de PostGIS. No apuntes el sitio activo a una restauración incompleta.

~~~powershell
createdb -h 127.0.0.1 -p 55432 -U urbantwin urbantwin_v2_restore
pg_restore --exit-on-error --no-owner -h 127.0.0.1 -p 55432 -U urbantwin -d urbantwin_v2_restore urbantwin-v2.dump
New-Item -ItemType Directory -Force storage/app/restore-privado
tar -xzf urbantwin-originales.tar.gz -C storage/app/restore-privado
~~~

Compara el número de cuentas, grupos, lugares, capas, versiones, archivos, entidades espaciales, ejecuciones y reportes con lo registrado al tomar el respaldo. Verifica `catalogo.archivos.ruta` y `catalogo.paquetes_fuente.ruta` con `Get-FileHash -Algorithm SHA256`. Comprueba que coincide con su `sha256` y tamaño. Restablece el acceso al área de almacenamiento con los permisos de aplicación. Repite una consulta en la versión oficial de Piura y abre un reporte histórico. Los originales sin referencia pueden venir de una carga interrumpida. Consérvalos para conciliación con los logs de operación y los respaldos antes de aplicar una política de retención. Un archivo referenciado y ausente se recupera del respaldo por ruta y SHA-256.

## Ensayo desde MariaDB

La base de la v1 sigue siendo el origen durante el ensayo. Se requiere autorización antes de conectarse a ella o exportar datos reales.

1. Obtén una copia consistente de MariaDB y registra esquema, colaciones, zona horaria, conteos, valores nulos, geometrías por tipo y SRID, archivos referenciados y datos personales. Conserva el volcado y una copia de solo lectura.
2. Levanta una base PostGIS separada y ejecuta las migraciones. Prepara un ETL limitado a la copia. Migra primero identidad y catálogos de referencia y después zonas, escenarios, infraestructura y reportes con un mapa de IDs. Revisa que los reportes que se importen al esquema legado puedan estar disponibles para sus usuarios, ya que el acceso de la v1 era compartido.
3. Exporta geometrías como WKB o GeoJSON con SRID y procedencia explícitos. Determina el CRS de origen antes de transformar. Solo asigna SRID 4326 a geometrías confirmadas como WGS 84 en orden longitud y latitud. Deja invalidaciones, líneas o polígonos fuera de cobertura en un registro de rechazos revisable.
4. Importa las capas destinadas a v2 por el catálogo y conserva su exportación como original. Los reportes históricos de v1 retienen su procedencia. Para crear un reporte v2 a partir de esos datos, ejecuta el análisis con las versiones importadas.
5. Compara origen y destino por tabla y categoría. Verifica claves foráneas y ausencia de huérfanos, hash de originales, tipo, extensión espacial, CRS y validez, más una muestra de cruces espaciales. Registra aprobaciones, rechazos y las diferencias justificadas. Repite el ensayo sobre un destino nuevo hasta que los resultados sean reproducibles.
6. Para el corte aprobado, conserva el despliegue y la base de MariaDB restaurables. Documenta el cambio de configuración y define una ventana sin escrituras. Si falla la aceptación, vuelve al despliegue y conexión de v1 y conserva el resultado de PostGIS para diagnóstico. Si entraron operaciones nuevas en v2, exporta y concilia esos cambios antes de volver a habilitar escritura en v1.

En el ensayo local se ejecutaron el respaldo y la restauración en otra base y otro directorio. Coincidieron los conteos espaciales y de catálogo. Se comprobó el tamaño y el SHA-256 de los cinco originales restaurados. El ensayo con datos reales, la validación de restauración en producción y el corte quedan pendientes.
