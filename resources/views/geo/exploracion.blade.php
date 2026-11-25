<dialog class="geo-ficha geo-ficha-dialogo" id="geo-ficha-dialogo" aria-labelledby="geo-ficha-titulo">
    <header class="geo-ficha-cabecera">
        <div>
            <h2 id="geo-ficha-titulo">Ficha de la zona</h2>
            <p id="geo-ficha-ubicacion">Elige una región, provincia o punto en el mapa.</p>
        </div>
        <form method="dialog"><button type="submit" class="btn-sec" aria-label="Cerrar ficha">Cerrar</button></form>
    </header>
    <div class="geo-ficha-cuerpo">
    <fieldset class="geo-ficha-filtros">
        <legend>Qué quieres consultar</legend>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="territorio" checked> Territorio</label>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="capas" checked> Riesgo y lluvia histórica</label>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="pronostico"> Pronóstico de 7 días</label>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="historico"> Serie anual (ERA5)</label>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="evidencia" checked> Reportes, fotos y noticias</label>
    </fieldset>
    <p class="geo-unidad">1 milímetro de lluvia (mm) equivale a 1 litro de agua por metro cuadrado. Por ejemplo, 10 mm son 10 litros sobre cada metro cuadrado.</p>
    <p id="geo-ficha-estado" role="status"></p>
    <div id="geo-ficha-territorio"></div>
    <div id="geo-ficha-capas" class="geo-ficha-capas"></div>
    <div id="geo-ficha-pronostico" hidden></div>
    <div id="geo-ficha-historico" hidden></div>
    <div id="geo-ficha-evidencia"></div>
    <div class="geo-fuentes-lluvia">
        <a href="https://www.senamhi.gob.pe/?p=aviso-meteorologico" target="_blank" rel="noopener noreferrer">Avisos de SENAMHI</a>
        <a href="https://enfen.imarpe.gob.pe/comunicados/" target="_blank" rel="noopener noreferrer">Comunicados ENFEN</a>
        <a href="https://global-flood.emergency.copernicus.eu/" target="_blank" rel="noopener noreferrer">Monitoreo de inundaciones · Copernicus</a>
    </div>
    </div>
</dialog>
