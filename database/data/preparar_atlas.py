"""Prepara las capas nacionales desde los CSV NOAA/CHIRPS y el ZIP distrital SIGRID."""

import argparse
import csv
import hashlib
import io
import json
from collections import Counter
from pathlib import Path
from zipfile import ZipFile

import numpy as np
import rasterio.features
import shapefile
import shapely
from affine import Affine
from pyproj import CRS
from shapely.geometry import MultiPolygon, mapping, shape

FICHA = "https://sigrid.cenepred.gob.pe/sigridv3/documento/23277"
ZIP = "https://sigrid.cenepred.gob.pe/sigridv3/storage/escenario_trimestral/102_shape.zip"
COLORES_LLUVIA = ["#f2ebbd", "#ffbf66", "#8bd5ac", "#46b3c6", "#2e7ab6", "#563d94"]
RIESGO = [
    ("Muy Bajo", "#288861"), ("Bajo", "#8abe68"), ("Medio", "#f4d250"),
    ("Alto", "#ee8538"), ("Muy Alto", "#ca334b"),
]


def json_linea(objeto):
    return json.dumps(objeto, ensure_ascii=False, separators=(",", ":"), allow_nan=False)


def poligonos(g):
    if g.geom_type == "Polygon":
        return [g]
    if g.geom_type in ("MultiPolygon", "GeometryCollection"):
        return [p for parte in g.geoms for p in poligonos(parte)]
    return []


def guardar_departamentos(origen, salida):
    datos = json.loads(origen.read_text(encoding="utf-8-sig"))
    partes = []
    codigos = set()
    with salida.open("w", encoding="utf-8") as archivo:
        for dato in datos["features"]:
            props = dato["properties"]
            codigo = props.get("shapeISO") or props["shapeID"]
            if codigo in codigos:
                raise ValueError(f"Código de departamento duplicado: {codigo}")
            codigos.add(codigo)
            partes_geo = poligonos(shapely.make_valid(shape(dato["geometry"])))
            geometria = shapely.union_all(partes_geo)
            partes.append(geometria)
            archivo.write(json_linea({"codigo": codigo, "nombre": props["shapeName"],
                                      "geometria": mapping(geometria)}) + "\n")
    return shapely.union_all(partes)


def preparar_lluvia(origen, salida, fecha, limites, cobertura, unidad):
    matriz = np.full((380, 280), -1, dtype=np.int16)
    with origen.open(encoding="utf-8-sig", newline="") as archivo:
        filas = csv.DictReader(archivo)
        if next(filas)["precip"] != unidad:
            raise ValueError(f"Unidad inesperada en {origen.name}")
        for fila in filas:
            if not fila["time"].startswith(fecha + "T"):
                raise ValueError(f"Fecha inesperada en {origen.name}: {fila['time']}")
            cantidad = float(fila["precip"])
            if not np.isfinite(cantidad) or cantidad < 0:
                continue
            x = round((float(fila["longitude"]) + 81.975) / .05)
            y = round((-.025 - float(fila["latitude"])) / .05)
            if 0 <= x < matriz.shape[1] and 0 <= y < matriz.shape[0]:
                matriz[y, x] = np.searchsorted(limites, cantidad, side="right")

    total = 0
    transformacion = Affine(.05, 0, -82, 0, -.05, 0)
    with salida.open("w", encoding="utf-8") as archivo:
        for geometria, clase in rasterio.features.shapes(matriz, mask=matriz >= 0, transform=transformacion):
            recorte = shapely.make_valid(shape(geometria)).intersection(cobertura)
            for parte in poligonos(recorte):
                if not parte.is_empty and parte.area > 0:
                    archivo.write(json_linea({"clase": int(clase), "geometria": mapping(parte)}) + "\n")
                    total += 1
    return total


def preparar_riesgo(paquete, nombre, campo, salida):
    base = "Shape/" + nombre
    crs = CRS.from_wkt(paquete.read(base + ".prj").decode())
    if not crs.equals(CRS.from_epsg(4326), ignore_axis_order=True):
        raise ValueError(f"CRS inesperado en {nombre}")
    clases = {etiqueta.casefold(): indice for indice, (etiqueta, _) in enumerate(RIESGO)}
    lector = shapefile.Reader(
        shp=io.BytesIO(paquete.read(base + ".shp")), shx=io.BytesIO(paquete.read(base + ".shx")),
        dbf=io.BytesIO(paquete.read(base + ".dbf")), encoding="utf-8",
    )
    conteo = Counter()
    omitidos = []
    normalizadas = 0
    with salida.open("w", encoding="utf-8") as archivo:
        for fila in lector.iterShapeRecords():
            datos = fila.record.as_dict()
            etiqueta = str(datos[campo] or "").strip()
            if etiqueta.casefold() not in clases:
                omitidos.append({"ubigeo": str(datos["UBIGEO"]), "valor": etiqueta})
                continue
            original = shape(fila.shape.__geo_interface__)
            normalizadas += not original.is_valid
            partes = poligonos(shapely.make_valid(original))
            if not partes:
                raise ValueError(f"Geometría vacía en {datos['UBIGEO']}")
            geometria = shapely.union_all(partes).simplify(.00025, preserve_topology=True)
            if not geometria.is_valid or geometria.is_empty:
                raise ValueError(f"Geometría de dibujo inválida en {datos['UBIGEO']}")
            clase = clases[etiqueta.casefold()]
            conteo[etiqueta] += 1
            archivo.write(json_linea({"clase": clase, "ubigeo": str(datos["UBIGEO"]).zfill(6),
                                      "distrito": datos["NOMBDIST"].title(), "departamento": datos["NOMBDEP"].title(),
                                      "geometria": mapping(geometria)}) + "\n")
    return {"conteo": dict(conteo), "sin_categoria": omitidos, "normalizadas": normalizadas}


def huella(path):
    with path.open("rb") as archivo:
        return hashlib.file_digest(archivo, "sha256").hexdigest()


def main(directorio):
    salida = directorio / "atlas"
    salida.mkdir(exist_ok=True)
    cobertura = guardar_departamentos(directorio / "departamentos.json", salida / "departamentos.ndjson")
    temas = []
    for codigo, insumo, fecha, umbrales, periodo, unidad, ficha in [
        ("lluvia-marzo-2017", "mes.csv", "2017-03-01", [25, 75, 150, 300, 500], "Marzo de 2017 · mm/mes", "mm/month",
         "https://coastwatch.pfeg.noaa.gov/erddap/griddap/chirps20GlobalMonthlyP05.html"),
        ("lluvia-26-marzo-2017", "pentada.csv", "2017-03-26", [5, 15, 35, 75, 125], "Péntada iniciada el 26 de marzo de 2017 · mm", "mm/(5days)",
         "https://coastwatch.pfeg.noaa.gov/erddap/griddap/chirps20GlobalPentadP05.html"),
    ]:
        cantidad = preparar_lluvia(directorio / insumo, salida / f"{codigo}.ndjson", fecha, umbrales, cobertura, unidad)
        rangos = [f"< {umbrales[0]}"] + [f"{a} a < {b}" for a, b in zip(umbrales, umbrales[1:])] + [f"≥ {umbrales[-1]}"]
        temas.append({
            "codigo": codigo, "tipo": "lluvia", "titulo": "Precipitación acumulada",
            "periodo": periodo, "fuente": "Climate Hazards Center UCSB · CHIRPS v2.0 / NOAA ERDDAP",
            "ficha_url": ficha, "archivo": insumo, "archivo_sha256": huella(directorio / insumo),
            "leyenda": [{"clase": i, "nombre": rango + " mm", "color": color}
                        for i, (rango, color) in enumerate(zip(rangos, COLORES_LLUVIA))],
            "notas": "Estimación histórica de precipitación en una rejilla de 0,05° recortada a Perú. Los rangos representan milímetros.",
            "preparacion": {"poligonos": cantidad},
        })

    riesgo = directorio / "riesgo-oficial.zip"
    sha = huella(riesgo)
    with ZipFile(riesgo) as paquete:
        for codigo, nombre, campo, titulo in [
            ("riesgo-inundacion-nde-2026", "Dist_Riesgo_Inundacion", "nriesg_inu", "Riesgo de inundación"),
            ("riesgo-movimientos-nde-2026", "Dist_Riesgo_MMasa", "nriesg_mm", "Riesgo por movimientos en masa"),
        ]:
            detalle = preparar_riesgo(paquete, nombre, campo, salida / f"{codigo}.ndjson")
            temas.append({
                "codigo": codigo, "tipo": "riesgo", "titulo": titulo, "periodo": "Noviembre de 2026 a enero de 2027",
                "fuente": "CENEPRED · SIGRID / SENAMHI · Informe técnico N.º 14-2026",
                "ficha_url": FICHA, "descarga_url": ZIP, "archivo": riesgo.name, "archivo_sha256": sha,
                "leyenda": [{"clase": i, "nombre": etiqueta, "color": color} for i, (etiqueta, color) in enumerate(RIESGO)],
                "notas": "Escenario oficial por distrito. La categoría corresponde a la unidad distrital y al período publicado.",
                "preparacion": detalle,
            })
    (salida / "manifest.json").write_text(json_linea(temas), encoding="utf-8")
    for tema in temas:
        print(tema["codigo"], tema["preparacion"])


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directorio", type=Path)
    main(parser.parse_args().directorio)
