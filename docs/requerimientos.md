# Requerimientos y aceptación

UrbanTwin Perú permite explorar el territorio peruano, consultar fuentes geoespaciales y comparar precipitación histórica con pronósticos. El visor requiere una cuenta activa. El catálogo, análisis y administración mantienen los permisos por rol y recurso descritos en [contratos y seguridad](contratos-y-seguridad.md).

| ID | Requerimiento | Aceptación |
| --- | --- | --- |
| RF-01 | Ampliar el visor | **Pantalla completa** ocupa la ventana. El usuario puede ocultar el inspector y volver al modo integrado. |
| RF-02 | Buscar regiones y provincias | Buscar por nombre filtra sugerencias. Al seleccionar una provincia se encuadra su polígono y se identifica el ámbito. |
| RF-03 | Inspeccionar y cruzar capas | El cursor muestra categoría, período y UBIGEO si la capa lo ofrece. Clic, toque o coordenadas fijan el punto. Casillas independientes muestran territorio, riesgo y lluvia histórica, pronóstico y serie anual. |
| RF-04 | Mostrar topografía | Al activar las curvas y acercarse a zoom 8 o superior se muestran cotas aproximadas cada 200 m y la atribución del modelo. |
| RF-05 | Pronosticar precipitación | Para el punto representativo de una región o provincia se muestran hasta siete fechas, acumulado diario, probabilidad máxima cuando está disponible y hora del Perú. |
| RF-06 | Animar el mapa | La línea de tiempo recorre las muestras del pronóstico horario, permite pausar y muestra el período elegido. El relieve y las capas temáticas se controlan de forma independiente. |
| RF-07 | Consultar años | La ficha compara los diez años completos anteriores al actual. Los años con días faltantes indican su cobertura sin presentar un total parcial como anual. |
| RF-08 | Conservar las consultas originales | El ciudadano puede seguir consultando las huellas de Piura, midiendo distancias, eligiendo cartografía o satélite y guardando lugares privados. |
| RNF-01 | Uso móvil y accesibilidad | A 390 px se puede abrir y cerrar la navegación lateral y el inspector. Los controles tienen nombre accesible y las transiciones respetan movimiento reducido. Los resultados expresan valores de forma textual y visual. |
| RNF-02 | Trazabilidad | Se distinguen riesgo CENEPRED, observación de inundación, reanálisis ERA5 y pronóstico modelado. Cada lectura tiene período o fuente. |
| RNF-03 | Resiliencia y límites | Las consultas requieren sesión y tienen límites de frecuencia. Si el proveedor meteorológico falla, la ficha distingue la falta de datos y el mapa territorial sigue operando. |
| RNF-04 | Integridad de producción | La actualización se publica por Git y Render. Conserva la base de Neon, los usuarios y sus datos. Las verificaciones destructivas usan exclusivamente una base aislada. |

## Fuentes y alcance

| Fuente | Uso en el visor | Escala de lectura |
| --- | --- | --- |
| CENEPRED / SIGRID | Escenarios distritales aprobados en el catálogo | Categoría y UBIGEO por distrito |
| CHIRPS / NOAA | Precipitación de marzo de 2017 y de la péntada delimitada en el catálogo | Intervalos históricos por celda |
| Copernicus EMSR199 | Huellas de inundación observada de marzo de 2017 | Polígono de la cobertura de Piura |
| Open-Meteo | Pronóstico diario y animación horaria | Estimación modelada en puntos representativos |
| ERA5 / Copernicus vía Open-Meteo | Acumulados anuales en milímetros | Reanálisis de aproximadamente 25 km, para comparación puntual |
| Mapterhorn | Curvas aproximadas y relieve | Modelo digital de elevación |
| SENAMHI y ENFEN | Enlaces a avisos oficiales y seguimiento de El Niño | Cobertura y vigencia según cada publicación |

El comunicado ENFEN 20-2026 mantiene la alerta de El Niño Costero y distingue la evolución del Pacífico central. El detalle por localidad y vigencia se consulta en los avisos oficiales. [ENFEN](https://enfen.imarpe.gob.pe/comunicados/) · [Boletín INDECI del 24 de noviembre de 2026](https://portal.indeci.gob.pe/wp-content/uploads/2026/11/BOLET%C3%8DN-INFORMATIVO-MONITOREO-DE-PELIGROS-Y-PERSPECTIVAS-N%C2%B0328-del-24-11-2026.pdf)
