import re
import unicodedata
from datetime import date

ORDEN = ["super", "regular", "diesel", "kerosene", "glp_domestico", "glp_vehicular"]
MESES = {m: i for i, m in enumerate(
    "enero febrero marzo abril mayo junio julio agosto septiembre octubre noviembre diciembre".split(), 1)}

RE_NUM = re.compile(r"(?<![\d.,])(\d{1,3}(?:,\d{3})*\.\d{2})(?!\d)")
RE_VIG = re.compile(r"apartirdel(\d{1,2})de([a-z]+)del(\d{4})")


def norm(s: str) -> str:
    s = unicodedata.normalize("NFD", s.lower())
    return re.sub(r"[^a-z0-9]", "", s)


def _x(i): return (i["x0"] + i["x1"]) / 2
def _y(i): return (i["y0"] + i["y1"]) / 2


UMBRAL = 12


def leer_vigencia(items):
    texto = "".join(norm(i["text"]) for i in sorted(items, key=lambda i: (_y(i), _x(i))))
    m = RE_VIG.search(texto)
    if not m or m[2] not in MESES:
        return None
    try:
        return date(int(m[3]), MESES[m[2]], int(m[1]))
    except ValueError:
        return None


def _ancla(items, clave):
    return next((i for i in items if clave in norm(i["text"])), None)


def _a_items(res):
    out = []
    for box, texto, conf in res:
        xs, ys = [p[0] for p in box], [p[1] for p in box]
        out.append({"x0": min(xs), "y0": min(ys), "x1": max(xs), "y1": max(ys),
                    "text": texto, "conf": float(conf)})
    return out


_MOTORES = {}


def ocr_rapidocr(img):
    from rapidocr_onnxruntime import RapidOCR
    res, _ = _MOTORES.setdefault("rapidocr", RapidOCR())(img)
    return _a_items(res or [])


def ocr_easyocr(img):
    import easyocr
    return _a_items(_MOTORES.setdefault("easyocr", easyocr.Reader(["es", "en"], gpu=False)).readtext(img))


def puntaje(items) -> int:
    t = "".join(norm(i["text"]) for i in items)
    p = 4 * bool(RE_VIG.search(t)) + 2 * sum(c in t for c in ("tegucigalpa", "sanpedrosula"))
    p += sum(c in t for c in ("super", "regular", "diesel", "kerosene", "glp"))
    p += int("porgalon" in t)
    p += 3 * (len(RE_NUM.findall(" ".join(i["text"] for i in items))) >= 10)
    return p


def parsear_tabla(items, ancho, alto, anterior=None):
    anterior = anterior or {}

    vigente = leer_vigencia(items)
    if vigente is None:
        return None

    h_pre, h_gal = _ancla(items, "nuevoprecio"), _ancla(items, "porgalon")
    tgu, sps = _ancla(items, "tegucigalpa"), _ancla(items, "sanpedrosula")
    if not all((h_pre, h_gal, tgu, sps)):
        return None
    x_div = (_x(tgu) + _x(sps)) / 2
    y_ini, y_fin = _y(h_pre), _y(h_gal)

    nums = []
    for it in items:
        if not y_ini < _y(it) < y_fin:
            continue
        t, w = it["text"], it["x1"] - it["x0"]
        for mm in RE_NUM.finditer(t):
            antes = t[:mm.start()]
            signo = ("-" if re.search(r"[-−–—_~]\s*L?\s*$", antes)
                     else "+" if re.search(r"\+\s*L?\s*$", antes) else None)
            nums.append({"x": it["x0"] + w * (mm.start() + mm.end()) / 2 / len(t),
                         "y": _y(it), "v": float(mm[1].replace(",", "")), "signo": signo})

    filas = []
    for n in sorted(nums, key=lambda n: n["y"]):
        if filas and n["y"] - filas[-1][0]["y"] <= 0.012 * alto:
            filas[-1].append(n)
        else:
            filas.append([n])

    ys_apoyo = [_y(i) for i in items if "apoyo" in norm(i["text"])]
    ys_mant = [(_y(i), _x(i)) for i in items if "mantiene" in norm(i["text"])]
    es_apoyo = lambda f: any(abs(f[0]["y"] - y) < 0.015 * alto for y in ys_apoyo)
    apoyo = [f for f in filas if es_apoyo(f)]
    filas = [f for f in filas if not es_apoyo(f)]
    if len(filas) != len(ORDEN):
        return None

    precios = []
    for comb, fila in zip(ORDEN, filas):
        y_fila = sum(n["y"] for n in fila) / len(fila)
        for ciudad, es_izq in (("tegucigalpa", True), ("san_pedro_sula", False)):
            mitad = sorted((n for n in fila if (n["x"] < x_div) == es_izq), key=lambda n: n["x"])
            if not mitad or len(mitad) > 2:
                return None
            precio = mitad[0]["v"]
            if len(mitad) == 2:
                v, signo = mitad[1]["v"], mitad[1]["signo"]
                if v and signo is None:
                    prev = anterior.get((comb, ciudad))
                    if prev is None:
                        return None
                    signo = "-" if precio < prev else "+"
                var = -v if signo == "-" else v
            else:
                if not any(abs(y_fila - ym) < 0.02 * alto and (xm < x_div) == es_izq
                           for ym, xm in ys_mant):
                    return None
                var = 0.0
            precios.append({"combustible": comb, "ciudad": ciudad,
                            "precio": precio, "variacion": round(var, 2) + 0.0})

    datos = {"vigente_desde": vigente.isoformat(), "precios": precios}
    if apoyo:
        tg = [n["v"] for n in apoyo[0] if n["x"] < x_div]
        sp = [n["v"] for n in apoyo[0] if n["x"] >= x_div]
        if len(tg) == 1 and len(sp) == 1:
            datos["apoyo_glp_domestico"] = {"tegucigalpa": tg[0], "san_pedro_sula": sp[0]}
    return datos


def extraer_reglas(ruta, anterior=None, ocr=ocr_rapidocr):
    from PIL import Image
    ancho, alto = Image.open(ruta).size
    return parsear_tabla(ocr(ruta), ancho, alto, anterior)


if __name__ == "__main__":
    import json, sys
    print(json.dumps(extraer_reglas(sys.argv[1]), indent=1, ensure_ascii=False))
