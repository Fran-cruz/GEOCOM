import hashlib
import os
import re
import time
from pathlib import Path
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

import config

BASE = os.getenv("SEN_BASE_URL", "https://sen.hn").rstrip("/")
URL_HOME = BASE + "/"
URL_API = BASE + "/wp-json/wp/v2/media"
URL_HISTORIAL = BASE + "/historico-de-precios-de-los-combustibles/"

RE_MINIATURA = re.compile(r"-\d+x\d+(\.(?:jpe?g|png))$", re.I)
RE_IMAGEN = re.compile(r"\.(?:jpe?g|png|webp)(?:\?.*)?$", re.I)
RE_TITULO = re.compile(r"precios?\b.*combustibles?", re.I)
ENCABEZADOS = ("h1", "h2", "h3", "h4", "h5", "h6")


def pedir(url, reintentos=3, params=None):
    for i in range(reintentos):
        try:
            r = requests.get(url, headers=config.user_agent(), timeout=60, params=params)
            r.raise_for_status()
            return r
        except requests.RequestException as ex:
            codigo = getattr(getattr(ex, "response", None), "status_code", None)
            if i == reintentos - 1 or (codigo and 400 <= codigo < 500 and codigo != 429):
                raise
            time.sleep(2 * (i + 1))


def sha256_bytes(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()


def sha256(ruta):
    return sha256_bytes(Path(ruta).read_bytes())


def bajar(candidata):
    ultimo = None
    for u in [candidata["url"], *candidata.get("alternativas", [])]:
        try:
            return pedir(u).content, u
        except requests.RequestException as ex:
            ultimo = ex
    raise ultimo


def _urls_de_img(img, base):
    urls = []
    a = img.find_parent("a")
    if a and a.get("href") and RE_IMAGEN.search(a["href"]):
        urls.append(a["href"])
    srcset = img.get("srcset") or img.get("data-srcset") or img.get("data-lazy-srcset") or ""
    partes = []
    for trozo in srcset.split(","):
        p = trozo.strip().split()
        if p:
            w = int(p[1][:-1]) if len(p) > 1 and p[1].endswith("w") and p[1][:-1].isdigit() else 0
            partes.append((w, p[0]))
    urls += [u for _, u in sorted(partes, reverse=True)]
    for atr in ("data-lazy-src", "data-src", "data-orig-file", "src"):
        if img.get(atr):
            urls.append(img[atr])
    out = []
    for u in urls:
        if u.startswith("data:"):
            continue
        u = urljoin(base, u.strip())
        for c in (RE_MINIATURA.sub(r"\1", u), u):
            if c not in out:
                out.append(c)
    return out


def candidata_home(html: str, base=URL_HOME):
    soup = BeautifulSoup(html, "html.parser")
    enc = None
    for t in soup.find_all(True):
        es_enc = t.name in ENCABEZADOS or any("heading" in c for c in (t.get("class") or []))
        if es_enc and "precio de los combustibles" in " ".join(t.get_text(" ", strip=True).lower().split()):
            enc = t
            break
    if enc is None:
        return None
    for t in enc.find_all_next(True):
        if t.name in ENCABEZADOS and t.get_text(strip=True):
            return None
        if t.name != "img":
            continue
        urls = _urls_de_img(t, base)
        if not urls or re.search(r"logo|cropped-|icon", urls[0], re.I):
            continue
        return {"url": urls[0], "alternativas": urls[1:], "fuente": "home", "subida": None}
    return None


def candidatas_home():
    return [c for c in [candidata_home(pedir(URL_HOME).text)] if c]


def candidatas_api(n=3):
    params = {"search": "Precios-de-los-Combustibles", "media_type": "image", "orderby": "date",
              "order": "desc", "per_page": max(n * 3, 10),
              "_fields": "id,date_gmt,title,source_url,media_details"}
    out = []
    for m in pedir(URL_API, params=params).json():
        det = m.get("media_details") or {}
        w, h = det.get("width") or 0, det.get("height") or 0
        if not RE_TITULO.search((m.get("title") or {}).get("rendered", "")):
            continue
        if w and h and not 1.1 <= h / w <= 1.8:
            continue
        out.append({"url": m["source_url"], "alternativas": [], "fuente": "wp_api", "subida": m.get("date_gmt")})
    return out[:n]


def listar_historial(url=None):
    url = url or URL_HISTORIAL
    p = urlparse(url)
    patron = re.compile(r"https?://" + re.escape(p.netloc) +
                        r"/wp-content/uploads/(\d{4})/\d{2}/[^\s)\"'\]<>]+?\.(?:jpe?g|png)", re.I)
    vistos, salida = set(), []
    for m in patron.finditer(pedir(url).text):
        archivo = m[0].rsplit("/", 1)[-1]
        if archivo.lower() in vistos or archivo.lower().startswith("cropped-") or RE_MINIATURA.search(archivo):
            continue
        vistos.add(archivo.lower())
        salida.append({"url": re.sub(r"^https?://", p.scheme + "://", m[0]),
                       "archivo": archivo, "anio": int(m[1])})
    return salida


def candidatas_historial(n=3):
    return [{"url": e["url"], "alternativas": [], "fuente": "historial", "subida": None}
            for e in listar_historial()[-n:]]


def descargar(url, carpeta, pausa=1.0):
    destino = Path(carpeta) / url.rsplit("/", 1)[-1]
    if not destino.exists():
        destino.parent.mkdir(parents=True, exist_ok=True)
        destino.write_bytes(pedir(url).content)
        time.sleep(pausa)
    return destino


FUENTES = {"home": candidatas_home, "wp_api": candidatas_api, "historial": candidatas_historial}
