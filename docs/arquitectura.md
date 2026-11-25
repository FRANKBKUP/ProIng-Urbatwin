# Arquitectura de UrbanTwin Perú v2

## Vista lógica

Laravel gestiona el registro ciudadano, la sesión y los roles. El catálogo registra cobertura, procedencia y versiones. PostGIS almacena y consulta las geometrías. El análisis genera un resultado persistente y un reporte privado. Cada cuenta administra lugares individuales y decide cuáles compartir con un grupo.

~~~mermaid
erDiagram
    ROLES ||--o{ USUARIOS : asigna
    USUARIOS o|--o| SUPERUSUARIOS : activa
    SUPERUSUARIOS ||--o| REFERENCIAS_EQUIPO : tiene
    USUARIOS ||--o| PERFILES : configura
    DEPARTAMENTOS o|--o{ PERFILES : referencia
    DEPARTAMENTOS ||--o{ PROVINCIAS : agrupa
    TEMAS ||--o{ ZONAS_TEMATICAS : clasifica
    USUARIOS ||--o{ AUDITORIA : actua
    USUARIOS ||--o{ LUGARES : guarda
    USUARIOS ||--o{ GRUPO_MIEMBROS : participa
    GRUPOS ||--o{ GRUPO_MIEMBROS : contiene
    GRUPOS ||--o{ GRUPO_INVITACIONES : emite
    GRUPO_MIEMBROS o|--o{ LUGARES : autoriza
    USUARIOS ||--o{ IMPORTACIONES : carga
    USUARIOS ||--o{ EJECUCIONES : ejecuta
    COBERTURAS ||--o{ CAPAS : delimita
    FUENTES ||--o{ IMPORTACIONES : documenta
    ARCHIVOS ||--|| IMPORTACIONES : conserva
    PAQUETES_FUENTE o|--o{ ARCHIVOS : origina
    CAPAS ||--o{ IMPORTACIONES : recibe
    IMPORTACIONES ||--o{ IMPORTACION_ERRORES : detalla
    IMPORTACIONES ||--o| CAPAS_VERSIONES : produce
    CAPAS ||--o{ CAPAS_VERSIONES : versiona
    CAPAS_VERSIONES ||--o{ PELIGRO : contiene
    CAPAS_VERSIONES ||--o{ EQUIPAMIENTO : contiene
    CAPAS_VERSIONES ||--o{ VIAS : contiene
    CAPAS_VERSIONES ||--o{ CURVAS : contiene
    CAPAS_VERSIONES }o--o{ EJECUCIONES : insumo
    EJECUCIONES ||--|| RESULTADOS : produce
    RESULTADOS ||--|| REPORTES : sustenta
~~~

| Esquema | Datos | Invariantes |
| --- | --- | --- |
| `app` | Roles, usuarios, perfiles, plazas de superusuario, referencias del equipo, lugares, grupos, miembros, invitaciones y auditoría | Compartir un lugar requiere una membresía vigente. Las plazas se vinculan a una sola cuenta. Una referencia urbana se vincula a una plaza y contiene un punto aproximado proporcionado para el proyecto. |
| `catalogo` | Fuentes, paquetes, archivos, capas, importaciones, errores y versiones | ZIP de origen y GeoJSON asociado con SHA-256. Numeración por capa y referencia vigente de la misma capa. La publicación sella la versión. |
| `geo` | Departamentos, provincias, temas, zonas temáticas, coberturas, peligro, equipamiento, vías y curvas | Tipo según capa, SRID 4326 e índices GiST. Curvas con cota en metros. |
| `analisis` | Ejecuciones, resultados y reportes | Identificadores de versiones, parámetros, resultado persistido y UUID de reporte. |
| `legacy` | Tablas espaciales y reportes de v1 | Compatibilidad con el simulador académico original. |
| `public` | Control de migraciones, sesiones, caché y colas de Laravel | Tablas de operación del framework. |

Peligro y cobertura usan `MultiPolygon`, equipamiento usa `Point` y vías y curvas usan `MultiLineString`. GeoJSON de entrada usa CRS84, con longitud antes que latitud. El catálogo normaliza geometrías simples a sus equivalentes múltiples y conserva los bytes originales. El esquema `public` contiene las tablas operativas de Laravel. `catalogo.capas.version_vigente_id` selecciona la vigente. El análisis referencia directamente a las versiones.

## Vista de desarrollo

- `routes/web.php` declara las entradas y aplica sesión y roles. Los controladores validan el contrato HTTP y el acceso del usuario.
- `app/Services/Catalogo` importa y lee capas publicadas. Reutiliza la validación de coordenadas de `GeoJsonService`.
- `app/Services/Analisis` ejecuta el cruce espacial y registra reporte, ejecución y resultado en una transacción. Las migraciones definen restricciones y disparadores de inmutabilidad.
- `app/Services/Territorio` separa las consultas de propiedad y membresía de los controladores. Los lugares son puntos 4326 y las invitaciones se almacenan como hash. Terminar una membresía devuelve los lugares compartidos con el grupo a la cuenta privada.
- Blade renderiza el panel y el visor. MapLibre dibuja una exportación simplificada en caché por versión. PostGIS usa las geometrías de análisis publicadas. Cartografía, satélite y elevación son fuentes independientes del estado de consulta. Los polígonos y puntos se conservan al cambiar de fondo o activar relieve.

## Vista de procesos

~~~mermaid
sequenceDiagram
    actor SIG as Administrador SIG
    participant App as Laravel
    participant Disco as Archivos privados
    participant BD as PostGIS
    SIG->>App: GeoJSON y procedencia
    App->>BD: Bloquear capa y buscar SHA-256
    alt Archivo ya recibido en la capa
        BD-->>App: Importación y versión previas
    else Archivo nuevo
        App->>Disco: Conservar bytes originales
        App->>BD: Validar entidades y cobertura
        alt Validación correcta
            App->>BD: Guardar geometrías, sellar, actualizar vigente y auditar
        else Errores de contenido
            App->>BD: Registrar rechazo y errores por elemento
        end
    end
    App-->>SIG: Resultado de importación
~~~

El bloqueo de la capa se mantiene hasta confirmar la transacción. Dos cargas simultáneas de los mismos bytes a esa capa se resuelven como una sola importación. Una reimportación antigua devuelve el ID histórico. Si falla la transacción después de escribir el original, puede quedar un archivo pendiente de conciliación. El retiro de capa se realiza bajo el mismo bloqueo y la generación de reportes toma un bloqueo compartido sobre sus capas vigentes.

## Vista física

~~~mermaid
flowchart LR
    B[Navegador con Blade y MapLibre] -->|HTTP local o HTTPS en producción| W[PHP y Laravel]
    W -->|PDO PostgreSQL| P[(PostgreSQL con PostGIS)]
    W -->|Disco privado| A[Originales GeoJSON]
    W -->|HTTPS y cache por ambito| M[Open-Meteo]
    B -->|Teselas de elevacion| E[Mapterhorn]
    T[PHPUnit] --> P2[(PostGIS de pruebas)]
~~~

En el equipo de verificación, Laravel sirve la aplicación en `127.0.0.1:8013` y PostgreSQL escucha en `127.0.0.1:55432`. El clúster portátil reside en `storage/app/ut-runtime/cluster`. `urbantwin_v2_dev` contiene la aplicación y `urbantwin_v2_test` es la base aislada de PHPUnit. Los archivos de origen están en `storage/app/private/originales`. Compose define una alternativa con un volumen persistente. En producción, Render ejecuta Laravel en un contenedor y Neon aloja PostgreSQL con PostGIS. El contenedor usa un disco temporal para originales. La conexión de la aplicación a Neon se configura en Render.

## Escenarios de la vista +1

1. **Publicar una capa.** El administrador registra la procedencia, conserva el original y publica cuando pasan las validaciones.
2. **Consultar un punto.** El ciudadano inicia sesión, selecciona una capa y obtiene uno de cuatro estados. Si la versión cambia, el visor solicita repetir la consulta con los nuevos datos.
3. **Consultar un reporte histórico.** El analista guarda el cruce con su selección de versiones. Publicar otra versión mantiene el reporte anterior disponible para su autor y el administrador.

## Publicación oficial de Piura

El proceso carga el producto vectorial `EMSR199_10PIURA_DELINEATION_OVERVIEW-MONIT01_v3`, verifica su SHA-256 y usa el área de interés suministrada. Se reproyecta de EPSG:32717 a CRS84, se densifica el límite del área de interés antes de reproyectarlo y se usa una cuadrícula de `10⁻⁹` grados para codificar las coordenadas. Se recortan intersecciones marginales con el exterior del área de interés al preparar la capa. Los índices shapefile de las 96 entidades recortadas y las tres exclusiones quedan en el manifiesto. Los datos publicados son **126** polígonos del 26 de marzo, **1.251** del 30 de marzo y **4.304** segmentos de la red vial de referencia. [Producto Copernicus EMS](https://mapping.emergency.copernicus.eu/activations/EMSR199/), [condiciones de uso](https://mapping.emergency.copernicus.eu/terms-and-conditions/).

El satélite académico usa el mosaico EOxCloudless de 2024 y el relieve usa las teselas Terrarium de Mapterhorn. La consulta representa la huella geográfica de la fecha seleccionada. El relieve representa elevación del terreno. [Satélite](https://cloudless.eox.at/license-non-commercial), [elevación](https://mapterhorn.com/data-access/).

## Atlas nacional

`geo.temas` conserva metadatos, escala temporal, leyenda y hash del origen. `geo.zonas_tematicas` asocia polígonos a categorías y, para el escenario CENEPRED, a un UBIGEO. PostGIS recorta y codifica las teselas MVT para el dibujo. Al elegir un punto, otra consulta con `ST_Covers` usa la geometría completa y devuelve la categoría. El navegador centra la cámara mientras recibe la respuesta. Las coordenadas de consulta se envían por POST y no se guardan salvo que el usuario pulse **Guardar punto**.

Los límites de `geo.departamentos` y `geo.provincias` encuadran el mapa. La provincia se vincula al departamento que cubre un punto interior de su geometría. El original provincial se conserva con su SHA-256. El contorno simplificado viaja al navegador únicamente al elegir la provincia. [geoBoundaries PER ADM2](https://www.geoboundaries.org/api/current/gbOpen/PER/ADM2/).

La dona de riesgo cuenta distritos por categoría oficial. La dona de precipitación agrupa superficie cartografiada por intervalo en milímetros. La fuente, fecha e intervalo se muestran al pasar el cursor. El pronóstico diario consulta Open-Meteo para un único punto representativo por departamento y conserva la respuesta durante 20 minutos. La geometría de cada lugar privado queda en la aplicación. [CENEPRED](https://sigrid.cenepred.gob.pe/sigridv3/documento/23277), [CHIRPS](https://www.chc.ucsb.edu/data), [Open-Meteo](https://open-meteo.com/en/docs).

### Ficha territorial y fuentes meteorológicas

~~~mermaid
sequenceDiagram
    actor C as Ciudadano
    participant V as Visor
    participant A as Laravel
    participant P as PostGIS
    participant M as Open-Meteo
    C->>V: Selecciona un punto
    V->>A: POST ficha con longitud y latitud
    A->>P: ST_Covers para límites y capas vigentes
    P-->>V: Provincia, departamento y resultados por capa
    C->>V: Activa pronóstico o serie anual
    V->>A: GET producto y código territorial
    A->>P: Punto de referencia interno al polígono
    A->>M: Solicitud si falta en caché
    M-->>A: Datos diarios u horarios
    A-->>V: Serie con unidades y disponibilidad
~~~

La consulta de la ficha se limita a capas vigentes. Cuando un punto coincide con un límite, se prioriza el interior y luego un orden estable. Las consultas meteorológicas usan puntos de referencia de región o provincia y redondean sus coordenadas antes de consultar al proveedor. El historial aplica ERA5 de forma consistente en los diez últimos años completos y solo suma años con 365 o 366 días válidos. La animación consulta hasta 24 pasos horarios y suaviza el paso visual entre horas. Los controles permiten pausar, avanzar manualmente y respetan el ajuste de movimiento reducido. La caché mantiene los pronósticos durante 20 minutos y el histórico durante 30 días. Ante fallos del proveedor, el visor conserva la navegación y la consulta PostGIS.

Las curvas aproximan bandas de una tesela de elevación cada 200 m, desde el zoom 8. El cliente las renderiza a partir de los píxeles del modelo digital. La cobertura y resolución de la fuente limitan el detalle disponible para levantamientos topográficos. [Modelo digital y atribución](https://mapterhorn.com/attribution/) · [Archivo histórico ERA5](https://open-meteo.com/en/docs/historical-weather-api)

### Capas de observación y evidencia

Natural Earth aporta la geometría de referencia del lago Titicaca para mostrar su nombre sobre la superficie. [Lagos y reservorios de Natural Earth](https://www.naturalearthdata.com/downloads/10m-physical-vectors/10m-lakes/)

El navegador consulta `DescribeDomains` de NASA GIBS y reproduce hasta seis teselas IMERG del intervalo disponible más reciente. La fecha de la última imagen se muestra en hora peruana. La animación separa la precipitación reciente estimada a partir de satélites del pronóstico de Open-Meteo. Si el proveedor de pronóstico falla de forma temporal, Laravel puede devolver la última serie válida con su fecha original y un indicador de antigüedad.

El visor lee agua y cauces de OpenFreeMap con el esquema OpenMapTiles y datos de OpenStreetMap. La huella de Piura del 30 de marzo de 2017 es una representación simplificada de las 1251 geometrías del producto Copernicus EMSR199 para el dibujo. Las cifras de personas afectadas corresponden a los informes enlazados de COEN–INDECI, con corte y distrito identificados. Los marcadores usan puntos de referencia distritales y las cifras permanecen asociadas al informe, incluso si hay más de un informe para la región. [NASA GIBS](https://nasa-gibs.github.io/gibs-api-docs/access-basics/) · [OpenMapTiles](https://openmaptiles.org/docs/schema/) · [Copernicus EMSR199](https://mapping.emergency.copernicus.eu/activations/EMSR199/)
