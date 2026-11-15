# Contratos y controles

Las rutas v2 se registran con la conexión `pgsql`. Usan sesión Laravel. Las peticiones de escritura incluyen CSRF. Las respuestas API usan JSON. Para solicitudes con `Accept: application/json`, un invitado recibe `401` y un rol insuficiente recibe `403`.

| Operación | Entrada y salida | Permiso |
| --- | --- | --- |
| `GET /` | Portada informativa pública con acceso al visor, inicio de sesión y registro. | Público |
| `GET /api/geo/atlas/{tema}/{z}/{x}/{y}.pbf` | Tesela MVT con clase y referencia distrital cuando corresponde. Coordenadas acotadas y capas publicadas. | Rol activo |
| `POST /api/geo/atlas/{tema}/consulta` | Longitud y latitud en el cuerpo. Devuelve la categoría y referencia distrital del punto con la geometría completa, o `zona: null` si queda fuera de cobertura. | Rol activo |
| `GET /api/geo/atlas/provincias/{provincia}` | Límites para centrar el mapa y contorno provincial simplificado. | Rol activo |
| `GET /api/geo/atlas/equipo` | Nombre, plaza y referencia urbana aproximada. La respuesta excluye datos de autenticación y ubicación del dispositivo. | Rol activo |
| `PATCH /perfil/contrasena` | Contraseña actual y nueva confirmada. Modifica solo la cuenta autenticada. | Rol activo |
| `GET /api/geo/pronostico/{departamento}` | Salida regional de tres días y hora de consulta. Usa un punto de referencia calculado en PostGIS. | Rol activo |
| `PATCH /perfil` y `GET /api/geo/perfiles` | Actualiza las preferencias propias. La consulta devuelve nombre, ocupación y avatar de cuentas activas que decidieron mostrarlos por departamento. | Rol activo |
| `GET /panel` | Resumen de capas observadas, área unida por versión y conteos personales. | Rol activo |
| `POST /registro` | Nombre, correo, contraseña confirmada. Crea una cuenta con rol Consulta e inicia la sesión. | Invitado |
| `GET /api/geo/lugares` | Lista lugares propios. Con `grupo`, lista los lugares compartidos con el grupo de pertenencia. `404` para grupos ajenos. | Rol activo |
| `POST /mis-lugares` | Nombre y punto. Guarda como privado y devuelve un UUID. | Rol activo |
| `PATCH /mis-lugares/{lugar}` y `DELETE /mis-lugares/{lugar}` | Cambia la visibilidad a un grupo de pertenencia o vuelve el punto privado. Permite eliminar un lugar propio. | Propietario |
| `POST /grupos`, `/grupos/{grupo}/invitaciones` y `/grupos/aceptar` | Crea grupo, emite un código revocable por cierre y de un solo uso con caducidad de 24 horas, o acepta una invitación. | Rol activo. Emisión reservada al propietario. |
| `PATCH /grupos/{grupo}`, `DELETE /grupos/{grupo}` | Cambia el nombre desde la cuenta propietaria. El propietario elimina el grupo y el miembro puede salir. Los lugares afectados vuelven a ser privados. | Propietario o miembro según acción |
| `PATCH /mis-lugares/{lugar}/datos` | Cambia el nombre y coordenadas conservando la visibilidad. | Propietario |
| `POST /admin/superusuarios/{plaza}/invitacion` y `POST /perfil/credenciales` | Emite un código con correo de destino y caducidad de 24 horas. El titular de la cuenta lo canjea una sola vez. | Superusuario o administrador para la primera plaza. Cuenta activa para el canje. |
| `POST /catalogo/coberturas` | Nombre y geometría `Polygon` o `MultiPolygon` en CRS84. Redirige al catálogo. La cobertura debe caber en UTM 17 sur. | Administrador |
| `POST /catalogo/capas` | Nombre, `cobertura_id` y tipo `peligro`, `equipamiento`, `vias` o `curvas`. Redirige al catálogo. | Administrador |
| `PATCH /catalogo/gestion/{tipo}/{registro}` | Cambia la etiqueta, retira o restaura una capa o tema. El historial permanece disponible con permisos. | Administrador |
| `POST /catalogo/importaciones` | Multipart con `capa_id`, `archivo`, `fuente`, `licencia`, `origen` y `fecha_dato`. Origen `simulado` u `observado`. Fecha `AAAA-MM-DD`. Para JSON devuelve `id`, `estado`, `version_id` y `duplicada`. `201` nueva publicación, `200` duplicado publicado, `422` contenido rechazado o campos inválidos. En el formulario redirige al detalle de la carga aceptada o rechazada. | Administrador |
| `GET /catalogo/importaciones/{id}` y `/original` | Detalle de la operación y errores numerados desde 1. El número 0 indica error del documento. La descarga autorizada sirve los bytes originales como adjunto. | Administrador |
| `PATCH` o `DELETE /catalogo/importaciones/{id}/entidades/{elemento}` | Corrige o elimina una entidad de la versión vigente de una carga manual. PATCH recibe un GeoJSON Feature en `entidad`. Redirige al detalle de una nueva importación. `409` si cambió la versión de origen. | Administrador |
| `GET /catalogo/importaciones/{id}/paquete` | Descarga el ZIP de origen verificado si existe un paquete asociado a la carga. | Administrador |
| `GET /api/geo/capas/{capa}` | `capa` contiene versión vigente, fecha, cobertura, fuente, origen y condición de uso. `cobertura` es geometría GeoJSON y `peligro` es `FeatureCollection`. `404` si la capa no es de peligro o carece de versión publicada. | Rol activo |
| `POST /api/geo/capas/{capa}/consulta` | `version_id`, `longitud` de −180 a 180 y `latitud` de −90 a 90. Devuelve `estado` y `capa`. `409` si la versión cargada dejó de ser la vigente. `422` para campos inválidos. | Rol activo |
| `POST /analisis/reportes` | `peligro_id` y al menos `vias_id` o `equipamiento_id`, identificadores de versiones vigentes en capas sin retirar y de la misma cobertura. `201` con UUID y URL si se solicita JSON. Redirige al reporte desde el formulario. | Analista o Superusuario |
| `GET /analisis/reportes/{reporte}` | HTML con resultado, parámetros, versiones y procedencia. `403` ante falta de permiso. | Autor del reporte o administrador |

## Clasificación espacial y medida

- Se comprueba primero la cobertura con `ST_Covers`. Dentro de cobertura, la unión de polígonos que tocan el punto permite distinguir interior con `ST_Contains`, borde con `ST_Covers` y fuera de peligro cuando ambas comprobaciones son falsas. Los límites compartidos internos de polígonos se disuelven en la unión. [Semántica de ST_Contains](https://postgis.net/docs/ST_Contains.html), [semántica de ST_Covers](https://postgis.net/docs/ST_Covers.html).
- Cada vía se cruza con la unión de las áreas de peligro que la intersectan. Solo se conservan componentes lineales. Se disuelven solapes antes de proyectar el tramo a EPSG:32717 y medirlo en metros. El reporte muestra dos decimales y el resultado guarda la longitud calculada. Los equipamientos se incluyen también en el borde.
- La prueba de Talara cruza dos polígonos con ancho combinado de 0.003° de longitud a latitud −4.58°. La referencia aproximada es 332.77 m, obtenida con la escala longitudinal de WGS 84 a esa latitud y el factor central de UTM. Se acepta una diferencia de 0.25 m para el efecto de la distancia al meridiano central.

## Matriz de amenazas, controles y pruebas

| Amenaza | Control | Evidencia |
| --- | --- | --- |
| Solicitar un rol elevado al registrarse | El controlador fija el rol Consulta y descarta campos de autorización enviados por el navegador. | Prueba de registro con un rol de administrador en la solicitud. |
| Elevación de privilegios mediante invitación equivocada | Se valida la plaza, el correo de la cuenta activa, el hash y la caducidad bajo un bloqueo transaccional. El formulario general de usuarios excluye el rol Superusuario. | Prueba de rol forzado y de canje con otra cuenta, canje correcto y reutilización. |
| Retirar una capa utilizada en análisis previos | El retiro cambia su disponibilidad. Los reportes guardados y los datos originales conservan sus referencias. | Prueba de retiro, denegación de análisis nuevo, lectura del reporte previo y restauración. |
| Acceso a puntos privados o a un grupo ajeno | Pertenencia comprobada para cada lectura. Propiedad para editar o eliminar. Compartir requiere una acción separada. Invitación de un solo uso, hash y caducidad. | Pruebas HTTP de acceso entre cuentas, invitación reutilizada, visibilidad explícita y salida del grupo. |
| Publicar o descargar originales con una cuenta ciudadana | Comprobación de rol activo en servidor. Originales fuera de `public/`. | Prueba HTTP de `403` para publicación y descarga. |
| Leer un reporte ajeno mediante su URL | UUID del reporte y comprobación del propietario o del rol administrador. | Prueba de denegación para un segundo analista y para un ciudadano. |
| Archivo inválido desplaza una publicación válida | Validación por elemento, registro de rechazo y transacción para actualizar el puntero después del sellado. | Importación de polígono autointersectado, error visible y puntero vigente conservado. |
| Publicación duplicada o modificación histórica | SHA-256 y bloqueo por capa, claves foráneas y disparadores de inmutabilidad. | Reimportación del mismo archivo, rechazo de la escritura en un resultado y rechazo de alta en una versión sellada. |
| GPS preciso retenido durante una consulta | Permiso explícito del navegador, POST sin coordenadas en URL y consultas sin escritura. Guardar un punto es una acción independiente que lo guarda como privado. Respuestas de lugares `private, no-store`. | Prueba HTTP de cuenta propietaria y comprobación en Chrome de la URL sin coordenadas. Configurar la telemetría y el proxy para excluir cuerpos y parámetros enlazados. |
| Utilizar una simulación como observación | El visor filtra observaciones también en su API. Cada dato conserva fuente, fecha, versión y escala si está documentada. | Prueba con una capa observada y otra simulada que comprueba acceso y visibilidad. |
