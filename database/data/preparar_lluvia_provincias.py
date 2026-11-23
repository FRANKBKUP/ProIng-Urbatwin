"""Calcula lluvia diaria por provincia a partir de recortes CHIRPS existentes."""

import argparse
import calendar
import hashlib
import json
import math
from datetime import date, timedelta
from pathlib import Path

import numpy as np
import rasterio
from pyproj import Transformer
from rasterio.windows import Window, from_bounds
from shapely import make_valid, transform
from shapely.geometry import box, shape


def sha256(path):
    with path.open('rb') as archivo:
        return hashlib.file_digest(archivo, 'sha256').hexdigest()


def preparar(directorio, limites, mes, salida):
    departamento = json.loads((directorio / 'originales/departamentos.geojson').read_text())
    piura = shape(next(f['geometry'] for f in departamento['features'] if f['properties'].get('shapeISO') == 'PE-PIU'))
    fuente = json.loads(limites.read_text())
    provincias = [f for f in fuente['features'] if piura.covers(shape(f['geometry']).representative_point())]
    if len(provincias) != 8:
        raise ValueError('Se esperaban ocho provincias de Piura.')
    inicio = date.fromisoformat(mes + '-01')
    archivos = [directorio / 'originales' / f'chirps-{inicio + timedelta(days=i)}.tif' for i in range(calendar.monthrange(inicio.year, inicio.month)[1])]
    proyectar = Transformer.from_crs('EPSG:4326', 'EPSG:6933', always_xy=True).transform
    resultados = []
    with rasterio.open(archivos[0]) as referencia:
        if referencia.crs.to_epsg() != 4326 or referencia.count != 1:
            raise ValueError('La rejilla no coincide con CHIRPS.')
        rejilla = (referencia.crs, referencia.transform, referencia.width, referencia.height)
        for provincia in provincias:
            geom = make_valid(shape(provincia['geometry']))
            area = transform(geom, proyectar, interleaved=False).area
            limites_ventana = from_bounds(*geom.bounds, transform=referencia.transform)
            columna = math.floor(limites_ventana.col_off)
            fila = math.floor(limites_ventana.row_off)
            ventana = Window(columna, fila, math.ceil(limites_ventana.col_off + limites_ventana.width) - columna,
                             math.ceil(limites_ventana.row_off + limites_ventana.height) - fila)
            ventana = ventana.intersection(Window(0, 0, referencia.width, referencia.height))
            matriz = referencia.read(1, window=ventana, masked=True)
            t = referencia.window_transform(ventana)
            pesos = np.zeros(matriz.shape, dtype='float64')
            for fila in range(matriz.shape[0]):
                for columna in range(matriz.shape[1]):
                    x1, y1 = t * (columna, fila)
                    x2, y2 = t * (columna + 1, fila + 1)
                    parte = geom.intersection(box(min(x1, x2), min(y1, y2), max(x1, x2), max(y1, y2)))
                    if not parte.is_empty:
                        pesos[fila, columna] = transform(parte, proyectar, interleaved=False).area
            if area <= 0 or pesos.sum() / area < 0.999:
                raise ValueError(f"Cobertura geográfica insuficiente: {provincia['properties']['shapeName']}")
            for i, archivo in enumerate(archivos):
                with rasterio.open(archivo) as raster:
                    if (raster.crs, raster.transform, raster.width, raster.height) != rejilla:
                        raise ValueError(f'Rejilla incompatible: {archivo.name}')
                    valores = raster.read(1, window=ventana, masked=True)
                    validos = (~np.ma.getmaskarray(valores)) & np.isfinite(valores.data) & (valores.data >= 0) & (pesos > 0)
                    cobertura = float(pesos[validos].sum()) / area
                    if cobertura < 0.999:
                        raise ValueError(f'Cobertura sin datos suficiente: {archivo.name}')
                    promedio = float(np.sum(valores.data[validos] * pesos[validos]) / pesos[validos].sum())
                    resultados.append({'codigo': provincia['properties']['shapeID'], 'provincia': provincia['properties']['shapeName'],
                                       'fecha': (inicio + timedelta(days=i)).isoformat(), 'precipitacion_mm': round(promedio, 6),
                                       'cobertura': round(cobertura, 8), 'archivo_sha256': sha256(archivo)})
    salida.write_text(json.dumps({'mes': mes, 'limites_sha256': sha256(limites), 'registros': resultados}, ensure_ascii=False), encoding='utf-8')
    print(f'{len(resultados)} promedios provinciales diarios guardados en {salida}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('directorio', type=Path)
    parser.add_argument('limites', type=Path)
    parser.add_argument('mes')
    parser.add_argument('salida', type=Path)
    args = parser.parse_args()
    preparar(args.directorio, args.limites, args.mes, args.salida)
