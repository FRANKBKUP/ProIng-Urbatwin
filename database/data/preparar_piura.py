"""Reproyecta el paquete vectorial EMSR199 Piura v3 de EPSG:32717 a OGC:CRS84."""

import argparse
import hashlib
import io
import json
from collections import defaultdict
from pathlib import Path
from zipfile import ZipFile

import shapefile
import shapely
from pyproj import CRS, Transformer
from shapely.geometry import MultiLineString, MultiPolygon, mapping, shape
from shapely.validation import explain_validity

SHA256 = "746091f3d86f10a8e50fbd948a951c1fd102680abd302b20e6c10c6f1cec374a"
FECHAS = {"2017-03-26", "2017-03-30"}


def leer_capa(paquete, nombre):
    base, = (ruta[:-4] for ruta in paquete.namelist() if ruta.endswith(f"_{nombre}.shp"))
    crs = CRS.from_wkt(paquete.read(base + ".prj").decode())
    if not crs.equals(CRS.from_epsg(32717)):
        raise ValueError(f"CRS inesperado en {nombre}")
    return shapefile.Reader(
        shp=io.BytesIO(paquete.read(base + ".shp")),
        shx=io.BytesIO(paquete.read(base + ".shx")),
        dbf=io.BytesIO(paquete.read(base + ".dbf")),
        encoding="latin1",
    )


def guardar(ruta, objeto):
    ruta.write_text(json.dumps(objeto, ensure_ascii=False, separators=(",", ":"), default=str), encoding="utf-8")


def reproyectar(proyeccion, geometria):
    proyectada = shapely.transform(geometria, proyeccion.transform, interleaved=False)
    return shapely.set_precision(proyectada, 1e-9, mode="pointwise")


def recortar(geometria, aoi, poligono):
    resultado = geometria.intersection(aoi.buffer(-1e-8))
    simple, multiple = ("Polygon", MultiPolygon) if poligono else ("LineString", MultiLineString)
    partes = [pieza for pieza in shapely.get_parts(resultado) if pieza.geom_type == simple]
    return shapely.set_precision(multiple(partes), 1e-9, mode="pointwise")


def preparar(zip_path, destino):
    if hashlib.sha256(zip_path.read_bytes()).hexdigest() != SHA256:
        raise ValueError("El SHA-256 no coincide con el producto EMSR199 Piura v3 documentado.")

    destino.mkdir(parents=True, exist_ok=True)
    proyeccion = Transformer.from_crs(32717, 4326, always_xy=True)
    inundaciones = defaultdict(list)
    vias = []
    omitidas = []
    recortadas = []

    with ZipFile(zip_path) as paquete:
        cobertura, = leer_capa(paquete, "area_of_interest").shapes()
        aoi = reproyectar(proyeccion, shapely.segmentize(shape(cobertura.__geo_interface__), 80))
        if not aoi.is_valid:
            raise ValueError(explain_validity(aoi))

        for capa in ("crisis_information_poly", "transportation_line"):
            for fila in leer_capa(paquete, capa).iterShapeRecords():
                propiedades = fila.record.as_dict()
                fecha = str(propiedades["src_date"])
                if capa == "crisis_information_poly" and (fecha not in FECHAS or propiedades["interpret"] != "Flooded Area"):
                    raise ValueError(f"Clasificación o fecha inesperada en la entidad {fila.shape.oid}")

                geo = reproyectar(proyeccion, shape(fila.shape.__geo_interface__))
                if geo.is_valid and geo.is_simple and not aoi.covers(geo):
                    geo = recortar(geo, aoi, capa == "crisis_information_poly")
                    recortadas.append({"capa": capa, "indice_shapefile": fila.shape.oid})
                if geo.is_empty or not geo.is_valid or not geo.is_simple or not aoi.covers(geo):
                    motivo = explain_validity(geo) if not geo.is_valid else "Vacía, no simple o fuera del AOI"
                    omitidas.append({"capa": capa, "indice_shapefile": fila.shape.oid, "fecha": fecha, "motivo": motivo})
                    continue

                feature = {"type": "Feature", "geometry": mapping(geo), "properties": {
                    **propiedades, "indice_shapefile": fila.shape.oid,
                    "nombre": propiedades["nam"] if propiedades["nam"] not in ("", "Unk") else None,
                }}
                if capa == "crisis_information_poly":
                    inundaciones[fecha].append(feature)
                else:
                    vias.append(feature)

    guardar(destino / "aoi.geojson", mapping(aoi))
    for fecha in sorted(FECHAS):
        guardar(destino / f"inundacion-{fecha}.geojson", {"type": "FeatureCollection", "features": inundaciones[fecha]})
    guardar(destino / "vias.geojson", {"type": "FeatureCollection", "features": vias})
    preparar = {
        "sha256_original": SHA256, "crs_origen": "EPSG:32717",
        "conteos": {**{fecha: len(filas) for fecha, filas in sorted(inundaciones.items())}, "vias": len(vias)},
        "omitidas": omitidas, "recortadas_en_aoi": recortadas,
    }
    guardar(destino / "preparacion.json", preparar)
    print(json.dumps({"entidades": preparar["conteos"], "omitidas": len(omitidas), "recortadas": len(recortadas)}, ensure_ascii=False))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("zip", type=Path)
    parser.add_argument("destino", type=Path)
    args = parser.parse_args()
    preparar(args.zip, args.destino)
