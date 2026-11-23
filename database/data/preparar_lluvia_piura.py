"""Calcula lluvia diaria media ponderada por área para Piura con CHIRPS v3 RNL."""

import argparse
import calendar
import csv
import hashlib
import json
import math
from datetime import date, timedelta
from pathlib import Path

import numpy as np
import rasterio
from pyproj import Transformer
from rasterio.windows import from_bounds
from shapely import make_valid, transform
from shapely.geometry import box, mapping, shape

FUENTE = 'https://data.chc.ucsb.edu/products/CHIRPS/v3.0/daily/final/rnl/'
LIMITE = 'https://www.geoboundaries.org/api/current/gbOpen/PER/ADM1/'


def sha256(path):
    with path.open('rb') as archivo:
        return hashlib.file_digest(archivo, 'sha256').hexdigest()


def main(directorio, mes):
    originales = directorio / 'originales'
    limites = originales / 'departamentos.geojson'
    datos = json.loads(limites.read_text(encoding='utf-8'))
    regiones = [f for f in datos['features'] if f['properties'].get('shapeISO') == 'PE-PIU']
    if len(regiones) != 1:
        raise ValueError('Se esperaba un único límite para Piura.')
    piura = make_valid(shape(regiones[0]['geometry']))
    proyectar = Transformer.from_crs('EPSG:4326', 'EPSG:6933', always_xy=True).transform
    area_total = transform(piura, proyectar, interleaved=False).area
    fecha = date.fromisoformat(mes + '-01')
    dias_mes = calendar.monthrange(fecha.year, fecha.month)[1]
    archivos = [originales / f'chirps-{fecha + timedelta(days=i)}.tif' for i in range(dias_mes)]
    if not all(p.is_file() for p in archivos):
        raise ValueError(f'Faltan archivos diarios de {mes}.')
    with rasterio.open(archivos[0]) as raster:
        if raster.crs.to_epsg() != 4326 or raster.count != 1:
            raise ValueError('La proyección o las bandas no coinciden con CHIRPS.')
        limites_ventana = from_bounds(*piura.bounds, transform=raster.transform)
        columna_inicial = math.floor(limites_ventana.col_off)
        fila_inicial = math.floor(limites_ventana.row_off)
        ventana = rasterio.windows.Window(columna_inicial, fila_inicial,
            math.ceil(limites_ventana.col_off + limites_ventana.width) - columna_inicial,
            math.ceil(limites_ventana.row_off + limites_ventana.height) - fila_inicial)
        ventana = ventana.intersection(rasterio.windows.Window(0, 0, raster.width, raster.height))
        matriz = raster.read(1, window=ventana, masked=True)
        transformacion = raster.window_transform(ventana)
        pesos = np.zeros(matriz.shape, dtype='float64')
        for fila in range(matriz.shape[0]):
            for columna in range(matriz.shape[1]):
                x1, y1 = transformacion * (columna, fila)
                x2, y2 = transformacion * (columna + 1, fila + 1)
                interseccion = piura.intersection(box(min(x1, x2), min(y1, y2), max(x1, x2), max(y1, y2)))
                if not interseccion.is_empty:
                    pesos[fila, columna] = transform(interseccion, proyectar, interleaved=False).area
        referencia = (raster.crs, raster.transform, raster.width, raster.height)
    if area_total <= 0 or not np.isclose(pesos.sum(), area_total, rtol=0.0001):
        raise ValueError('La rejilla no cubre completamente el límite de Piura.')
    resultados = []
    for i, archivo in enumerate(archivos):
        fecha_dia = fecha + timedelta(days=i)
        ficha = archivo.with_suffix('.json')
        procedencia = json.loads(ficha.read_text(encoding='utf-8')) if ficha.exists() else None
        if procedencia and (procedencia['fecha'] != fecha_dia.isoformat()
                            or procedencia['recorte_sha256'] != sha256(archivo)
                            or procedencia['limite_sha256'] != sha256(limites)):
            raise ValueError(f'El recorte no coincide con sus metadatos: {archivo.name}')
        with rasterio.open(archivo) as raster:
            if (raster.crs, raster.transform, raster.width, raster.height) != referencia:
                raise ValueError(f'Rejilla incompatible: {archivo.name}')
            valores = raster.read(1, window=ventana, masked=True)
            validos = (~np.ma.getmaskarray(valores)) & np.isfinite(valores.data) & (valores.data >= 0) & (pesos > 0)
            area_valida = float(pesos[validos].sum())
            cobertura = area_valida / float(pesos.sum())
            promedio = float(np.sum(valores.data[validos] * pesos[validos]) / area_valida) if area_valida else None
            resultados.append({'fecha': fecha_dia.isoformat(), 'precipitacion_mm': round(promedio, 6) if promedio is not None else None,
                               'cobertura': round(cobertura, 8), 'archivo': archivo.name, 'sha256': sha256(archivo),
                               'tipo_archivo': 'recorte' if procedencia else 'original mundial',
                               'fuente': procedencia['fuente'] if procedencia else f'{FUENTE}{fecha_dia:%Y}/chirps-v3.0.rnl.{fecha_dia:%Y.%m.%d}.tif',
                               'fuente_original': procedencia.get('fuente_original', '') if procedencia else '',
                               'fecha_descarga_utc': procedencia.get('fecha_descarga_utc', '') if procedencia else ''})
    if any(f['cobertura'] < 0.999 for f in resultados):
        raise ValueError('Existe un día con cobertura inferior a 99,9 %; revisa los datos antes de importar.')
    mensual = originales / f'chirps-v3.0.{fecha:%Y.%m}-mensual.tif'
    ficha_mensual = mensual.with_suffix('.json')
    if ficha_mensual.exists():
        procedencia_mes = json.loads(ficha_mensual.read_text(encoding='utf-8'))
        if procedencia_mes['recorte_sha256'] != sha256(mensual) or procedencia_mes['limite_sha256'] != sha256(limites):
            raise ValueError('El recorte mensual no coincide con sus metadatos.')
    with rasterio.open(mensual) as raster:
        if (raster.crs, raster.transform, raster.width, raster.height) != referencia:
            raise ValueError('La rejilla mensual no coincide con la diaria.')
        valores_mes = raster.read(1, window=ventana, masked=True)
        validos = (~np.ma.getmaskarray(valores_mes)) & np.isfinite(valores_mes.data) & (valores_mes.data >= 0) & (pesos > 0)
        if pesos[validos].sum() / area_total < 0.999:
            raise ValueError('La cobertura del archivo mensual es insuficiente.')
        promedio_mensual = float(np.sum(valores_mes.data[validos] * pesos[validos]) / pesos[validos].sum())
    total_diario = sum(f['precipitacion_mm'] for f in resultados)
    if abs(promedio_mensual - total_diario) > 0.02:
        raise ValueError(f'La suma diaria ({total_diario:.4f}) no coincide con el producto mensual ({promedio_mensual:.4f}).')
    (directorio / 'piura.geojson').write_text(json.dumps(mapping(piura), ensure_ascii=False), encoding='utf-8')
    with (directorio / 'lluvia-diaria.csv').open('w', newline='', encoding='utf-8') as archivo:
        escritor = csv.DictWriter(archivo, fieldnames=resultados[0].keys())
        escritor.writeheader()
        escritor.writerows(resultados)
    manifiesto = {'zona': 'PE-PIU', 'nombre': 'Piura', 'inicio': fecha.isoformat(), 'fin': (fecha + timedelta(days=dias_mes - 1)).isoformat(),
                  'producto': 'CHIRPS v3.0 final RNL', 'fuente': FUENTE,
                  'metodo': 'Media de celdas ponderada por el área de intersección con Piura en EPSG:6933; valores diarios RNL derivados de totales de cinco días usando ERA5.',
                  'limite_fuente': LIMITE, 'limite_url': 'https://media.githubusercontent.com/media/wmgeolab/geoBoundaries/90a1d52/releaseData/gbOpen/PER/ADM1/geoBoundaries-PER-ADM1.geojson',
                  'limite_sha256': sha256(limites), 'limite_ano': 2008, 'limite_licencia': 'Public Domain',
                  'area_m2': area_total, 'dias': len(resultados), 'archivos': resultados,
                  'comparacion_mensual': {'fuente': f'https://edcintl.cr.usgs.gov/downloads/sciweb1/shared/fews/web/global/monthly/chirps/final/downloads/monthly/chirps-v3.0.{fecha:%Y.%m}.tif',
                                          'archivo': mensual.name, 'sha256': sha256(mensual), 'promedio_mm': promedio_mensual,
                                          'suma_diaria_mm': total_diario, 'diferencia_mm': total_diario - promedio_mensual}}
    (directorio / 'manifiesto.json').write_text(json.dumps(manifiesto, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f"Procesados {len(resultados)} días; cobertura mínima {min(r['cobertura'] for r in resultados):.2%}; acumulado {sum(r['precipitacion_mm'] for r in resultados):.2f} mm.")


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directorio', type=Path)
    parser.add_argument('--mes', default='2017-03', help='Mes a procesar en formato AAAA-MM')
    args = parser.parse_args()
    main(args.directorio, args.mes)
