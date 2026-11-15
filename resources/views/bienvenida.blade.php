<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="UrbanTwin Perú es un atlas territorial para consultar escenarios oficiales de riesgo, lluvias históricas y observaciones de inundación.">
    <title>UrbanTwin Perú · Atlas de lluvias y riesgo territorial</title>
    <link rel="stylesheet" href="{{ asset('css/bienvenida.css') }}">
</head>
<body class="bienvenida">
    <a class="bienvenida-saltar" href="#contenido">Saltar al contenido</a>
    <header class="bienvenida-cabecera">
        <nav class="bienvenida-ancho bienvenida-nav" aria-label="Navegación principal">
            <a class="bienvenida-marca" href="{{ route('bienvenida') }}"><span><x-icono n="mapa"/></span>UrbanTwin<span class="bienvenida-pais">Perú</span></a>
            <div class="bienvenida-nav-enlaces">
                <a href="#herramientas">Herramientas</a>
                @guest
                    <a href="{{ route('login') }}">Iniciar sesión</a>
                    <a class="bienvenida-boton bienvenida-boton-sec" href="{{ route('registro') }}">Crear cuenta</a>
                @else
                    <a class="bienvenida-boton bienvenida-boton-sec" href="{{ route('geo.dashboard') }}">Mi panel</a>
                @endguest
            </div>
        </nav>
    </header>
    <main id="contenido">
        <section class="bienvenida-hero bienvenida-ancho" aria-labelledby="titulo-bienvenida">
            <div class="bienvenida-hero-texto">
                <p class="bienvenida-contexto">Atlas territorial del Perú</p>
                <h1 id="titulo-bienvenida">Lluvia y riesgo, en el mapa</h1>
                <p>Ubica tu distrito y explora escenarios oficiales de riesgo, registros de lluvia y las huellas de las inundaciones de 2017. UrbanTwin reúne estas fuentes para apoyar la consulta y el análisis preventivo.</p>
                <a class="bienvenida-boton bienvenida-boton-principal" href="{{ route('geo.portal') }}">Entrar al visor <span aria-hidden="true">↗</span></a>
                @guest <p class="bienvenida-aviso">Inicia sesión o crea una cuenta ciudadana para consultar el visor y guardar tus lugares.</p> @endguest
            </div>
            <figure class="bienvenida-imagen">
                <img src="{{ asset('img/piura-modis-2017.jpg') }}" fetchpriority="high" alt="Vista de la costa de Piura captada el 23 de marzo de 2017, con áreas inundadas al sur del río Piura">
                <figcaption>
                    <div><strong>Piura durante El Niño Costero</strong><span>Imagen satelital del 23 de marzo de 2017</span></div>
                    <a href="https://science.nasa.gov/earth/earth-observatory/flood-waters-inundate-coastal-peru-89931/" target="_blank" rel="noopener noreferrer">NASA Earth Observatory&nbsp;↗</a>
                </figcaption>
            </figure>
        </section>
        <section id="herramientas" class="bienvenida-herramientas" aria-labelledby="titulo-herramientas">
            <div class="bienvenida-ancho">
                <h2 id="titulo-herramientas">Qué puedes consultar</h2>
                <div class="bienvenida-servicios">
                    <article>
                        <span class="bienvenida-icono"><x-icono n="zonas"/></span>
                        <h3>Riesgo por distrito</h3>
                        <p>Selecciona el escenario de inundaciones o movimientos en masa y señala un distrito para ver su categoría de CENEPRED.</p>
                    </article>
                    <article>
                        <span class="bienvenida-icono"><x-icono n="escenarios"/></span>
                        <h3>Lluvia histórica y pronóstico</h3>
                        <p>Consulta la precipitación estimada de marzo de 2017 y el pronóstico de los próximos tres días para un punto por departamento.</p>
                    </article>
                    <article>
                        <span class="bienvenida-icono"><x-icono n="usuarios"/></span>
                        <h3>Lugares y grupos privados</h3>
                        <p>Guarda ubicaciones de interés y elige cuáles compartir con un grupo al que hayas invitado.</p>
                    </article>
                </div>
            </div>
        </section>
    </main>
    <footer class="bienvenida-ancho bienvenida-pie">
        <p>UrbanTwin Perú · Proyecto académico</p>
        <div><a href="https://sigrid.cenepred.gob.pe/sigridv3/que" target="_blank" rel="noopener noreferrer">SIGRID / CENEPRED</a><a href="https://www.chc.ucsb.edu/data/chirps" target="_blank" rel="noopener noreferrer">CHIRPS</a></div>
    </footer>
</body>
</html>
