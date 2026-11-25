# UrbanTwin Perú

Atlas, catálogo y portal ciudadano sobre Laravel y PostGIS. El visor de Perú incluye escenarios distritales oficiales de CENEPRED, precipitación CHIRPS de marzo de 2017 y el piloto de huellas de inundación de Piura y Bajo Piura. [Escenario CENEPRED/SIGRID](https://sigrid.cenepred.gob.pe/sigridv3/documento/23277), [CHIRPS/NOAA](https://coastwatch.pfeg.noaa.gov/erddap/griddap/chirps20GlobalMonthlyP05.html), [Copernicus EMSR199](https://mapping.emergency.copernicus.eu/activations/EMSR199/).

## Abrir el portal

Abre la [portada pública](http://127.0.0.1:8013/). Desde **Entrar al visor**, inicia sesión para abrir el [visor](http://127.0.0.1:8013/visor) o navega al [panel territorial](http://127.0.0.1:8013/panel). Puedes [crear tu cuenta ciudadana](http://127.0.0.1:8013/registro) con nombre, correo y una contraseña propia. El rol de consulta se asigna en el servidor.

1. En el visor nacional alcanza un distrito con el cursor. El tooltip muestra su categoría oficial, nombre, período y UBIGEO. Haz clic o toca para fijar la lectura. Cambia a marzo de 2017 para leer intervalos de precipitación en milímetros.
2. Selecciona un departamento y, si quieres, una de sus provincias. El visor encuadra el límite al elegirlo. **Menú** contrae la navegación y **Cómo usar** abre una guía dentro del visor. **Perfil y preferencias** incluye tema claro, oscuro y del sistema.
3. Marca un punto, usa **Mi ubicación** o ingresa longitud y latitud y pulsa **Centrar y consultar**. El mapa se desplaza al punto y PostGIS lee la capa activa. Para comprobar una huella, elige **Piura · 30 de marzo de 2017** e ingresa `-80.6895978` de longitud y `-5.4971117` de latitud. Puedes alternar satélite y relieve.
4. Abre **Guardar punto** y ponle un nombre. En **Lugares y grupos** puedes editar o eliminar tus puntos y controlar su visibilidad. El propietario puede renombrar o eliminar el grupo. Al eliminarlo, todos los puntos compartidos vuelven a sus cuentas privadas. **Ver en mapa** encuadra los lugares compartidos.
5. En **Personas en el mapa**, elige alguien del equipo para enfocar y consultar su ciudad de referencia. Los iconos enlazan ubicaciones urbanas aproximadas. Desde **Perfil y preferencias** puedes publicar de manera voluntaria tu avatar en el punto de referencia de tu departamento y cambiar tu contraseña. Los perfiles de demostración quedan ocultos por defecto.
6. En el panel alterna las distribuciones por categoría y consulta el pronóstico de tres días de Open-Meteo para un punto regional de referencia. Las dos fotografías satelitales se pueden abrir a tamaño completo. [API de Open-Meteo](https://open-meteo.com/en/docs), [NASA MODIS de marzo](https://science.nasa.gov/earth/earth-observatory/flood-waters-inundate-coastal-peru-89931/), [NASA Landsat de abril](https://science.nasa.gov/earth/earth-observatory/flooding-in-northern-peru-89978/).

La fuente identifica las observaciones históricas y su escala de trabajo de **1:80 000**. La cobertura corresponde al área de interés de ese producto. Los resultados sirven para consulta territorial histórica.

La base local que usa la aplicación es `urbantwin_v2_dev`, servida por PostgreSQL con PostGIS en `127.0.0.1:55432`. `urbantwin_v2_test` está reservada para pruebas. En la instalación portátil, los datos de PostgreSQL viven bajo `storage/app/ut-runtime/cluster` y los archivos originales bajo `storage/app/private/originales`. Revisa el [DER y las vistas de arquitectura](docs/arquitectura.md) y el [procedimiento de publicación](docs/operacion.md).

## Explorar lluvia y relieve

Abre **Visor territorial** e introduce una región o provincia en **Explorar**. El visor encuadra el límite elegido. Pulsa **Pantalla completa** para ampliar el área de trabajo y oculta el explorador si quieres usar todo el mapa. En el celular, **Menú** abre la navegación de la aplicación y **Explorar** abre el panel del mapa.

1. Pasa el cursor por la capa activa para ver su categoría y período. Elige un punto por toque, clic o coordenadas. Abre **Ver ficha del punto** y marca las consultas que necesitas. La ficha muestra el distrito y UBIGEO si están en la fuente, la provincia, las categorías de las capas vigentes y sus referencias.
2. Activa **Curvas de nivel · 200 m** y acércate a una provincia. Las líneas aproximan cotas del modelo digital de elevación de Mapterhorn desde el zoom 8.
3. Activa **Animación de precipitación** y recorre la línea de tiempo o pulsa **Reproducir**. Cada hora mostrada proviene de Open-Meteo y usa hora del Perú. El mapa representa muestras modeladas en puntos territoriales.
4. Marca **Pronóstico de 7 días** y **Serie anual (ERA5)** para comparar estimación diaria y acumulados de los diez últimos años completos en el punto de referencia elegido. Consulta los avisos de SENAMHI y los comunicados de ENFEN desde la ficha.

La entrega de Ingeniería de Software incluye [requerimientos y aceptación](docs/requerimientos.md), [diccionario de datos](docs/diccionario-datos.md), [arquitectura](docs/arquitectura.md), [pruebas](docs/pruebas.md) y [operación](docs/operacion.md).

## Administrar capas y accesos

- En **Catálogo geoespacial → Cargar GeoJSON**, elige una capa, adjunta un archivo `.geojson` o `.json` y completa su procedencia. La vista previa indica el número y tipos de geometrías. El servidor valida la cobertura y la topología antes de publicar. Si rechaza la carga, el detalle enlaza los errores por entidad.
- Para corregir una publicación, usa **Retirar**, carga un nuevo GeoJSON en la misma capa y luego pulsa **Restaurar**. Los análisis nuevos requieren versiones vigentes. El nombre del visor se puede cambiar desde **Editar nombre**. Los reportes históricos conservan referencias, nombres de origen, resultados y archivos.
- Para corregir un registro de un GeoJSON manual, abre la importación vigente en **Historial de importaciones → Entidades de la versión vigente**. Usa **Editar** o **Eliminar** en la fila. El resultado se valida y publica como nueva versión con un enlace a su importación de origen. Para retirar la última entidad, usa **Retirar** en la capa.
- En **Superusuarios** hay cinco plazas nominadas para Frank Suarez, Gabriela Reyes, Daniel Huertas, Rosa Rumiche y Arturo Sandoval. El administrador existente genera la primera invitación para el correo personal de Frank. Frank podrá invitar a los otros cuatro tras la activación. Cada persona crea su cuenta de consulta y activa el código en **Perfil y preferencias → Activar credenciales**. El código queda ligado al correo, dura 24 horas y se muestra al generarlo para que el administrador lo comparta en privado.

## Preparar un entorno local

Se verificó con PHP 8.4, Composer, Node.js 24, PostgreSQL 17.10 y PostGIS 3.6.2. La aplicación usa las bases aisladas `urbantwin_v2_dev` y `urbantwin_v2_test`.

~~~powershell
composer install --no-interaction
npm.cmd ci --ignore-scripts
php --ri pdo_pgsql
~~~

En Windows, si la extensión PostgreSQL está incluida en PHP y aún no se carga, habilítala para la sesión de PowerShell y sus procesos hijos.

~~~powershell
$phpRoot = Split-Path (Get-Command php).Source
$iniPath = (New-Item -ItemType Directory -Force storage/app/ut-runtime/php-conf).FullName
('extension="' + $phpRoot.Replace('\','/') + '/ext/php_pdo_pgsql.dll"') | Set-Content (Join-Path $iniPath 'postgis.ini')
@('upload_max_filesize=5M', 'post_max_size=8M') | Set-Content (Join-Path $iniPath 'cargas.ini')
$env:PHP_INI_SCAN_DIR = $iniPath
php --ri pdo_pgsql
~~~

El catálogo muestra el menor límite entre PHP y sus 5 MiB por archivo. Reinicia el servidor PHP después de cambiar la configuración de cargas.

Compose define contenedores y volúmenes separados. Su arranque sigue por verificar en una máquina con Docker.

~~~powershell
docker compose --env-file .env.example --profile test up -d --wait postgis postgis-test
~~~

Este equipo usa PostgreSQL portátil con ambas bases en el puerto `55432`. Si el clúster ya está preparado y está detenido, puedes iniciarlo con el comando siguiente. [Binarios PostgreSQL](https://www.postgresql.org/download/windows/), [binarios PostGIS](https://postgis.net/documentation/getting_started/install_windows/released_versions/).

~~~powershell
$runtime = (Resolve-Path 'storage/app/ut-runtime').Path
& "$runtime/pgsql/bin/pg_ctl.exe" -D "$runtime/cluster" -l "$runtime/postgres.log" -o '-h 127.0.0.1 -p 55432' -w start
~~~

En PowerShell configura el destino aislado. Para el clúster portátil usa `$env:DB_PASSWORD=''`. La clave de aplicación del ejemplo dura lo que dure la sesión de PowerShell.

~~~powershell
$env:APP_ENV='local'
$env:APP_DEBUG='false'
$env:APP_URL='http://127.0.0.1:8013'
$env:APP_KEY='base64:' + [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$env:BOOST_BROWSER_LOGS_WATCHER='false'
$env:DB_CONNECTION='pgsql'
$env:DB_URL=''
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='55432'
$env:DB_DATABASE='urbantwin_v2_dev'
$env:DB_USERNAME='urbantwin'
$env:DB_PASSWORD=''
$env:CACHE_STORE='database'
$env:SESSION_DRIVER='database'
$env:SESSION_DOMAIN=''
$env:SESSION_SECURE_COOKIE='false'
php artisan migrate --no-interaction
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8013 --no-reload --no-interaction
~~~

## Reproducir la importación oficial

En una segunda terminal con el mismo entorno PostgreSQL, usa el correo de un administrador activo. En una base recién creada, primero registra una cuenta propia y aprovisiona su rol con una cuenta de mantenimiento de la base. El registro público asigna exclusivamente `Consulta`.

~~~powershell
$runtime = (New-Item -ItemType Directory -Force storage/app/ut-runtime).FullName
$producto = 'https://cems-mapping-website.s3.eu-west-1.amazonaws.com/static/activations/EMSR199/EMSR199_10PIURA_DELINEATION_OVERVIEW-MONIT01_v3_vector.zip'
curl.exe -fLsS $producto -o "$runtime/piura-oficial.zip"
python -m pip install --target "$runtime/python-geo" pyshp==3.1.8 shapely==2.2.0 pyproj==3.8.0
$env:PYTHONPATH=(Resolve-Path "$runtime/python-geo").Path
python database/data/preparar_piura.py "$runtime/piura-oficial.zip" "$runtime/piura"
php artisan territorio:importar-piura correo-del-admin "$runtime/piura-oficial.zip" "$runtime/piura" --no-interaction
~~~

La importación conserva el ZIP exacto, su SHA-256, los GeoJSON derivados y el registro de preparación. El paquete aporta **126** polígonos para el 26 de marzo, **1.251** para el 30 y **4.304** tramos viales de 2011. Se excluyeron un polígono inválido y dos líneas no simples. El detalle de catálogo muestra las exclusiones y el ajuste al límite del área de interés.

La exportación de dibujo simplifica los polígonos y se guarda en caché por versión. La consulta espacial y los reportes usan la geometría de análisis persistida. Las teselas tienen proveedores y atribuciones independientes. El satélite está habilitado para uso académico y se puede desactivar con `URBANTWIN_SATELITE_ACADEMICO=false`. [OpenStreetMap](https://operations.osmfoundation.org/policies/tiles/), [EOxCloudless](https://cloudless.eox.at/license-non-commercial), [Mapterhorn](https://mapterhorn.com/attribution/).

### Reconstruir el atlas nacional

El programa lee el ZIP de SIGRID, los CSV recortados de NOAA y el límite departamental de geoBoundaries. La reconstrucción requiere espacio para el ZIP original de SIGRID, los intermedios y su copia de archivo. El visor sirve teselas vectoriales de PostGIS y guarda versiones en caché. [Datos SIGRID](https://sigrid.cenepred.gob.pe/sigridv3/documento/23277), [CHIRPS mensual](https://coastwatch.pfeg.noaa.gov/erddap/griddap/chirps20GlobalMonthlyP05.html), [CHIRPS por péntadas](https://coastwatch.pfeg.noaa.gov/erddap/griddap/chirps20GlobalPentadP05.html), [geoBoundaries Perú ADM1](https://www.geoboundaries.org/api/current/gbOpen/PER/ADM1/).

~~~powershell
$atlas = (New-Item -ItemType Directory -Force storage/app/ut-runtime/peru).FullName
$region = Invoke-RestMethod 'https://www.geoboundaries.org/api/current/gbOpen/PER/ADM1/'
$limites = $region.simplifiedGeometryGeoJSON.Replace('https://github.com/wmgeolab/geoBoundaries/raw/', 'https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/')
curl.exe -fLsS $limites -o "$atlas/departamentos.json"
curl.exe -fLsS 'https://sigrid.cenepred.gob.pe/sigridv3/storage/escenario_trimestral/102_shape.zip' -o "$atlas/riesgo-oficial.zip"
curl.exe -fLsS 'https://coastwatch.pfeg.noaa.gov/erddap/griddap/chirps20GlobalMonthlyP05.csv?precip%5B(2017-03-01T00:00:00Z)%5D%5B(-19):(-0.01)%5D%5B(-82):(-68)%5D' -o "$atlas/mes.csv"
curl.exe -fLsS 'https://coastwatch.pfeg.noaa.gov/erddap/griddap/chirps20GlobalPentadP05.csv?precip%5B(2017-03-26T00:00:00Z)%5D%5B(-19):(-0.01)%5D%5B(-82):(-68)%5D' -o "$atlas/pentada.csv"
python -m pip install --target "$atlas/python" rasterio==1.5.2 pyshp==3.1.8 shapely==2.2.0 pyproj==3.8.0
$env:PYTHONPATH=(Resolve-Path "$atlas/python").Path
python database/data/preparar_atlas.py $atlas
php artisan territorio:importar-atlas $atlas --no-interaction
curl.exe -fLsS 'https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/9469f09/releaseData/gbOpen/PER/ADM2/geoBoundaries-PER-ADM2.geojson' -o "$atlas/provincias-adm2.geojson"
php artisan territorio:importar-provincias "$atlas/provincias-adm2.geojson" --no-interaction
~~~

El comando provincial publica las 196 provincias de la fuente IGN/OCHA 2020 y conserva su GeoJSON íntegro por SHA-256. El límite mostrado es cartográfico y se usa para navegación. [geoBoundaries Perú ADM2 y licencia CC BY 3.0 IGO](https://www.geoboundaries.org/api/current/gbOpen/PER/ADM2/).

Los campos `nriesg_inu` y `nriesg_mm` aportan la categoría oficial de cada distrito. Cada escenario tiene 1.893 distritos y corresponde a noviembre de 2026 a enero de 2027. El análisis normalizó una geometría para dibujo en cada escenario. La capa de precipitación clasifica los valores CHIRPS en intervalos explícitos, conserva sus unidades y recorta la rejilla por el límite de Perú. El pronóstico diario es una serie separada y muestra su fecha y hora de consulta.

En el PHP portátil de Windows se usaron las autoridades raíz públicas del almacén de confianza de Windows en formato PEM para la conexión HTTPS a Open-Meteo. La aplicación busca ese PEM en `storage/app/private/certificados/autoridades.pem` o en la ruta indicada en `URBANTWIN_PRONOSTICO_CA`. En entornos con certificados configurados en PHP puede usarse su almacén predeterminado.

## Verificación y documentación

Apunta la terminal a la base de pruebas sin datos persistentes. Con Compose usa puerto `55433` y contraseña `urbantwin_test_only`. En el PostgreSQL portátil mantén el puerto `55432` y la contraseña vacía.

~~~powershell
$env:APP_ENV='testing'
$env:DB_DATABASE='urbantwin_v2_test'
php artisan migrate --no-interaction
php artisan test --compact
npm.cmd run build
git diff --check
~~~

Las simulaciones de Talara siguen cubiertas por las pruebas de regresión. El visor y el panel operativos muestran observaciones por defecto. El acceso académico queda disponible con `URBANTWIN_MOSTRAR_SIMULACIONES=true`.

Consulta el [modelo y vistas 4+1](docs/arquitectura.md), los [contratos y controles](docs/contratos-y-seguridad.md) y la [guía de operación y respaldo](docs/operacion.md).
