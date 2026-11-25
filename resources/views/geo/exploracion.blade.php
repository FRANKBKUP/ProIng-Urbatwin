<section class="geo-detalle geo-ficha" aria-labelledby="geo-ficha-titulo">
    <h3 id="geo-ficha-titulo">Ficha de la zona</h3>
    <p id="geo-ficha-ubicacion">Elige una región, provincia o punto en el mapa.</p>
    <fieldset class="geo-ficha-filtros">
        <legend>Qué quieres consultar</legend>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="territorio" checked> Territorio</label>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="capas" checked> Riesgo y lluvia histórica</label>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="pronostico"> Pronóstico de 7 días</label>
        <label class="geo-check"><input type="checkbox" data-consulta-geo="historico"> Serie anual (ERA5)</label>
    </fieldset>
    <p id="geo-ficha-estado" role="status"></p>
    <div id="geo-ficha-territorio"></div>
    <div id="geo-ficha-capas"></div>
    <div id="geo-ficha-pronostico" hidden></div>
    <div id="geo-ficha-historico" hidden></div>
    <div class="geo-fuentes-lluvia">
        <a href="https://www.senamhi.gob.pe/?p=aviso-meteorologico" target="_blank" rel="noopener noreferrer">Avisos de SENAMHI</a>
        <a href="https://enfen.imarpe.gob.pe/comunicados/" target="_blank" rel="noopener noreferrer">Comunicados ENFEN</a>
    </div>
</section>
