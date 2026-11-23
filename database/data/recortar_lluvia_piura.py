"""Guarda únicamente la ventana de Piura de un archivo diario CHIRPS v3 RNL."""

import argparse
import calendar
import hashlib
import json
import math
import shutil
from concurrent.futures import ThreadPoolExecutor
from datetime import date, datetime, timedelta, timezone
from pathlib import Path

import rasterio
from rasterio.windows import Window, from_bounds
from shapely import make_valid
from shapely.geometry import shape

FUENTE = 'https://data.chc.ucsb.edu/products/CHIRPS/v3.0/daily/final/rnl/{ano}/chirps-v3.0.rnl.{ano}.{mes}.{dia}.tif'
FUENTE_COG = 'https://data.source.coop/wfp/chirps-rnl-daily/v3.0/{ano}/{mes}/{dia}/chirps-v3.0.rnl.{ano}.{mes}.{dia}.tif'
FUENTE_MENSUAL = 'https://edcintl.cr.usgs.gov/downloads/sciweb1/shared/fews/web/global/monthly/chirps/final/downloads/monthly/chirps-v3.0.{ano}.{mes}.tif'


def sha256(archivo):
    with archivo.open('rb') as datos:
        return hashlib.file_digest(datos, 'sha256').hexdigest()


def recortar(fecha, limite, destino, mensual=False):
    datos = json.loads(limite.read_text(encoding='utf-8'))
    regiones = [f for f in datos['features'] if f['properties'].get('shapeISO') == 'PE-PIU']
    if len(regiones) != 1:
        raise ValueError('Se esperaba un único límite para Piura.')
    piura = make_valid(shape(regiones[0]['geometry']))
    if piura.is_empty or not piura.is_valid:
        raise ValueError('El límite de Piura no es válido.')

    destino.mkdir(parents=True, exist_ok=True)
    nombre = f'chirps-v3.0.{fecha:%Y.%m}-mensual' if mensual else f'chirps-{fecha.isoformat()}'
    salida = destino / f'{nombre}.tif'
    metadatos = destino / f'{nombre}.json'
    url = (FUENTE_MENSUAL if mensual else FUENTE_COG).format(ano=fecha.strftime('%Y'), mes=fecha.strftime('%m'), dia=fecha.strftime('%d'))
    if salida.exists() or metadatos.exists():
        if not salida.exists() or not metadatos.exists():
            raise FileExistsError('El recorte está incompleto; revisa los archivos existentes.')
        registro = json.loads(metadatos.read_text(encoding='utf-8'))
        if registro['recorte_sha256'] != sha256(salida) or registro['limite_sha256'] != sha256(limite) or registro['fuente'] != url:
            raise ValueError('El recorte existente no coincide con sus metadatos.')
        print(f'{salida.name}: recorte existente verificado.')
        return
    with rasterio.Env(GDAL_DISABLE_READDIR_ON_OPEN='EMPTY_DIR', GDAL_HTTP_MAX_RETRY='3', GDAL_HTTP_RETRY_DELAY='2'):
        with rasterio.open('/vsicurl/' + url) as origen:
            if origen.crs is None or origen.crs.to_epsg() != 4326 or origen.count != 1:
                raise ValueError('La proyección o las bandas no coinciden con CHIRPS.')
            limites = from_bounds(*piura.bounds, transform=origen.transform)
            columna = math.floor(limites.col_off)
            fila = math.floor(limites.row_off)
            ventana = Window(columna, fila, math.ceil(limites.col_off + limites.width) - columna,
                             math.ceil(limites.row_off + limites.height) - fila)
            ventana = ventana.intersection(Window(0, 0, origen.width, origen.height))
            valores = origen.read(1, window=ventana)
            perfil = origen.profile.copy()
            perfil.update(driver='GTiff', height=int(ventana.height), width=int(ventana.width),
                          transform=origen.window_transform(ventana), compress='lzw')
            with rasterio.open(salida, 'w', **perfil) as archivo:
                archivo.write(valores, 1)

    registro = {
        'fecha': fecha.isoformat(),
        'producto': 'CHIRPS v3.0 mensual final' if mensual else 'CHIRPS v3.0 final RNL',
        'fuente': url,
        'fuente_original': (FUENTE_MENSUAL if mensual else FUENTE).format(ano=fecha.strftime('%Y'), mes=fecha.strftime('%m'), dia=fecha.strftime('%d')),
        'distribuidor': 'USGS' if mensual else 'Programa Mundial de Alimentos / Source Cooperative',
        'zona': 'PE-PIU',
        'tipo_recorte': 'ventana rectangular que contiene el límite de Piura; no es una máscara administrativa',
        'limite_sha256': sha256(limite),
        'archivo': salida.name,
        'recorte_sha256': sha256(salida),
        'filas': valores.shape[0],
        'columnas': valores.shape[1],
        'fecha_descarga_utc': datetime.now(timezone.utc).isoformat(),
    }
    metadatos.write_text(json.dumps(registro, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f'{salida.name}: {salida.stat().st_size} bytes; {valores.shape[0]} × {valores.shape[1]} celdas.')


def recortar_mes(mes, limite, directorio):
    inicio = date.fromisoformat(mes + '-01')
    originales = directorio / 'originales'
    originales.mkdir(parents=True, exist_ok=True)
    copia_limite = originales / 'departamentos.geojson'
    if copia_limite.exists() and sha256(copia_limite) != sha256(limite):
        raise ValueError('El límite existente no coincide con el límite solicitado.')
    if not copia_limite.exists():
        shutil.copyfile(limite, copia_limite)
    fechas = [inicio + timedelta(days=dia) for dia in range(calendar.monthrange(inicio.year, inicio.month)[1])]
    with ThreadPoolExecutor(max_workers=3) as ejecutor:
        list(ejecutor.map(lambda fecha: recortar(fecha, copia_limite, originales), fechas))
    recortar(inicio, copia_limite, originales, mensual=True)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('fecha', help='Fecha AAAA-MM-DD o mes AAAA-MM con --mes')
    parser.add_argument('limite', type=Path, help='GeoJSON original de departamentos de Perú')
    parser.add_argument('destino', type=Path, help='Directorio para guardar el recorte y su procedencia')
    parser.add_argument('--mes', action='store_true', help='Descarga todos los días y el contraste mensual')
    args = parser.parse_args()
    if args.mes:
        recortar_mes(args.fecha, args.limite, args.destino)
    else:
        recortar(date.fromisoformat(args.fecha), args.limite, args.destino)
