# Pruebas de UrbanTwin Perú

## Preparación

Inicia el PostgreSQL portátil. Ejecuta las pruebas con `urbantwin_v2_test` en el puerto 55432. El archivo `phpunit.xml` fuerza el nombre de esa base y `TestCaseConBD` verifica que la conexión sea local y que se use una transacción. Las pruebas meteorológicas usan respuestas HTTP simuladas.

~~~powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='pgsql'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='55432'
$env:DB_DATABASE='urbantwin_v2_test'
$env:DB_USERNAME='urbantwin'
$env:DB_PASSWORD='(empty)'
php -d extension=pdo_pgsql vendor/bin/phpunit
npm.cmd run build
git diff --check
~~~

## Matriz de verificación

| Caso | Acción | Resultado esperado | Evidencia |
| --- | --- | --- | --- |
| P-01 | Consultar un punto contenido y otro fuera del atlas. Retirar una capa. | Se devuelven categoría y UBIGEO con cobertura, ausencia de dato fuera y exclusión de la capa retirada. | `AtlasTerritorialTest` |
| P-02 | Consultar históricos con un año bisiesto completo y un año parcial. | El año de 366 días muestra total. El año parcial muestra cobertura y total nulo. | `AtlasTerritorialTest` |
| P-03 | Pedir una animación con horas válidas, un valor cero y un valor ausente. | Se conserva el cero, se omite el valor ausente y se construye la secuencia horaria. | `AtlasTerritorialTest` |
| P-04 | Simular un fallo del proveedor en pronóstico provincial. | Se conserva el nombre de la provincia y la respuesta indica que no hay datos disponibles. | `AtlasTerritorialTest` |
| P-05 | Buscar Sullana y activar curvas, pantalla completa y consulta por punto. | Encuadre provincial, capa de cotas, mapa ampliado y ficha consultable. | Verificación en navegador |
| P-06 | Abrir y cerrar ambos paneles laterales en ancho de 390 px. | El mapa conserva el espacio y el contenido puede recorrerse sin desplazamiento horizontal. | Verificación en navegador |
| P-07 | Activar pronóstico, serie anual y animación. | Resultados con fecha, fuente, unidad y controles de reproducción. | Verificación local con proveedor público |
| P-08 | Abrir la portada desplegada y comprobar `/up` tras publicar. | Render registra el commit previsto y devuelve HTTP 200. | Panel de Render y consulta HTTP |

Para reproducir la demostración visual, selecciona Sullana y activa los módulos uno por uno. Prueba también la huella observada de Piura del 30 de marzo de 2017 en longitud `-80.6895978` y latitud `-5.4971117`. Verifica la interfaz en modo claro y oscuro. Guarda el registro de fecha, commit, navegador y resultado cuando la presentes.

## Registro local del 24 de noviembre de 2026

- Compilación de Vite completada. PHPUnit ejecutó **68 pruebas y 584 aserciones** en la base aislada.
- En Sullana, la ficha devolvió cuatro capas. La API local obtuvo siete días de pronóstico, diez años completos de 2016 a 2025 y ocho pasos horarios con ocho puntos en Piura.
- En el navegador se comprobó el encuadre provincial, la pantalla completa, la reproducción meteorológica y el dibujo de las curvas en Cusco. En ancho emulado de 390 px se verificaron los dos paneles laterales, la consulta por coordenadas y los temas claro y oscuro.

Para la vista de lluvia reciente, comprueba que **Satélite reciente** indique la fecha de la última imagen y se diferencie de **Pronóstico 24 h**. Reproduce y pausa ambas series, modifica la intensidad del color y confirma que la ficha de un reporte muestre números, fecha, ámbito y enlaces a su evidencia. El caso automatizado de recuperación comprueba que ante un HTTP 429 se reutiliza la última serie válida con la hora original.

La revisión local de esta actualización verificó **69 pruebas y 592 aserciones**, 24 pasos de pronóstico y seis imágenes satelitales. En Piura se comprobaron barras, histograma, fotos y las cifras de INDECI por distrito. A 390 px, la ficha se ajustó al ancho de pantalla y la tabla de reportes conservó su desplazamiento interno.
