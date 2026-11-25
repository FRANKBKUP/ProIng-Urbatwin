# Diccionario de datos de UrbanTwin Perú

PostgreSQL separa identidad, catálogo, geometría y resultados en esquemas. Las geometrías se almacenan con SRID 4326, longitud antes de latitud. Los identificadores son `bigint` o UUID según el recurso. Las marcas de operación usan `timestamptz`. La interfaz de pronóstico usa `America/Lima`.

## Identidad y colaboración

| Tabla | Claves y campos principales | Regla |
| --- | --- | --- |
| `app.roles` | `id`, `nombre` | Nombre único. Define el acceso funcional. |
| `app.usuarios` | `id`, `nombre`, `email`, `password`, `rol_id`, `activo`, `tema` | Correo único, contraseña con hash y preferencia claro, oscuro o del dispositivo. |
| `app.perfiles` | `usuario_id`, `departamento`, `avatar`, `ocupacion`, `descripcion`, `visible_en_mapa`, `demostracion` | La publicación del avatar requiere consentimiento. |
| `app.superusuarios` | `plaza`, `nombre`, `usuario_id`, `correo`, `hash`, `vence_en`, `activado_en` | Cinco plazas nominadas con una cuenta como máximo por plaza. El código de invitación se conserva como hash. |
| `app.referencias_equipo` | `plaza`, `ciudad`, `geom` | Punto urbano de referencia vinculado a una plaza. |
| `app.grupos` | `id`, `propietario_id`, `nombre` | El propietario administra el grupo. |
| `app.grupo_miembros` | `grupo_id`, `usuario_id` | Clave compuesta que acredita membresía. |
| `app.grupo_invitaciones` | `hash`, `grupo_id`, `vence_en`, `usado_en` | Invitaciones con vigencia y registro de uso. |
| `app.lugares` | `id`, `usuario_id`, `grupo_id`, `nombre`, `geom` | Punto privado por defecto. Compartir requiere membresía vigente. |
| `app.auditoria` | `id`, `usuario_id`, `accion`, `objeto`, `detalle`, `creado_en` | Identifica a la cuenta que realizó una operación auditada. |

## Catálogo y geometrías

| Tabla | Claves y campos principales | Regla |
| --- | --- | --- |
| `catalogo.fuentes` | `id`, `nombre`, `licencia`, `origen` | Identifica la procedencia observada o simulada. |
| `catalogo.paquetes_fuente` | `id`, `producto`, URL de ficha y descarga, `ruta`, `sha256`, `bytes`, `escala`, `preparacion` | Rastro del producto de origen y transformaciones. |
| `catalogo.archivos` | `id`, `paquete_id`, `ruta`, `sha256`, `nombre`, `bytes` | Metadatos del original. Los bytes se guardan en almacenamiento privado. |
| `catalogo.capas` | `id`, `nombre`, `tipo`, `cobertura_id`, `version_vigente_id` | La versión vigente pertenece a la misma capa. |
| `catalogo.importaciones` | `id`, `capa_id`, `archivo_id`, `usuario_id`, `fuente_id`, `fecha_dato`, `estado` | Registra publicación o rechazo. |
| `catalogo.importacion_errores` | `importacion_id`, `elemento`, `motivo` | Identifica el elemento rechazado y la causa. |
| `catalogo.capas_versiones` | `id`, `capa_id`, `tipo`, `importacion_id`, `numero`, `crs_origen`, `publicada_en` | Versiones inmutables después de publicar. GeoJSON de origen en OGC:CRS84. |
| `geo.departamentos` | `codigo`, `nombre`, `geom` | Límite departamental con índice espacial. |
| `geo.provincias` | `codigo`, `departamento`, `nombre`, `archivo_sha256`, `geom` | Multipolígono válido identificado por código de fuente. |
| `geo.temas` | `codigo`, `tipo`, `titulo`, `periodo`, `fuente`, `ficha_url`, `leyenda`, `retirada_en` | Define capa temática, procedencia y categorías. |
| `geo.zonas_tematicas` | `id`, `tema`, `clase`, `distrito`, `departamento`, `ubigeo`, `km2`, `geom` | Multipolígono clasificado, UBIGEO opcional y superficie en km². |
| `geo.coberturas` | `id`, `nombre`, `creado_por`, `geom` | Multipolígono que delimita un producto y sus capas. |
| `geo.peligro`, `geo.equipamiento`, `geo.vias`, `geo.curvas` | Identificador, versión, atributos y `geom` | Geometrías de versión. Las cotas de curvas de catálogo usan metros. |

## Análisis y operación

| Tabla o esquema | Campos o contenido principal | Regla |
| --- | --- | --- |
| `analisis.ejecuciones` | `id`, `usuario_id`, `peligro_id`, `vias_id`, `equipamiento_id`, `parametros` | Conserva las versiones que intervienen en el cruce. |
| `analisis.resultados` | `ejecucion_id`, `detalle`, `total` | El total se deriva de los elementos del resultado. |
| `analisis.reportes` | `id`, `ejecucion_id`, `creado_en` | Reporte privado con identificador UUID. |
| `legacy` | `zonas`, `tipos_infraestructura`, `escenarios`, `infraestructuras`, `reportes`, `reporte_detalles` | Mantiene compatibilidad con el simulador académico. |
| `public` | Migraciones, sesiones, caché y colas del framework | Datos operativos de Laravel. |

## Respuestas meteorológicas

| Campo | Tipo | Interpretación |
| --- | --- | --- |
| `punto.latitude`, `punto.longitude` | Número | Punto interior del ámbito, redondeado a dos decimales al consultar. |
| `dias[].fecha`, `dias[].mm` | Fecha ISO y número | Fecha en Perú y precipitación diaria pronosticada en mm. |
| `dias[].probabilidad` | Entero o `null` | Máxima probabilidad diaria en porcentaje. Nulo indica dato ausente. |
| `pasos[].fecha` | Fecha y hora ISO local | Hora del Perú representada en la animación. |
| `pasos[].muestras[].mm` | Número | Milímetros de precipitación estimada durante la hora del punto de referencia. |
| `anios[].anio`, `anios[].mm` | Entero y número o `null` | Año calendario y precipitación total ERA5. Nulo indica año incompleto. |
| `anios[].dias`, `anios[].esperados` | Entero | Cobertura diaria válida frente a 365 o 366 días esperados. |
| `disponible` | Booleano | El proveedor devolvió datos utilizables para ese producto. |

El pronóstico y la serie anual usan caché de Laravel. La ficha espacial no guarda las coordenadas consultadas como lugar. El [DER y los flujos](arquitectura.md) amplían las relaciones.
