#!/usr/bin/env python3
import argparse
import json
import sys
from datetime import date, datetime, timedelta
from io import BytesIO
from pathlib import Path

import numpy as np
from PIL import Image

import config
import db
import fuentes_senhn as fs
import parser_precios as pp
import validador as val

SALIDA = config.BASE_DIR / "salida"
IMAGENES = config.BASE_DIR / "imagenes"
MAX_FUTURO_DIAS = 14
DIAS_DESACTUALIZADO = 10
ORDEN_FUENTES = ("home", "wp_api", "historial")


def ocr_bytes(contenido, motor):
    im = Image.open(BytesIO(contenido)).convert("RGB")
    if im.width < 1400:
        im = im.resize((1600, round(im.height * 1600 / im.width)), Image.LANCZOS)
    return motor(np.array(im)), im.width, im.height


def _anterior_de(datos):
    return {(p["combustible"], p["ciudad"]): p["precio"] for p in datos["precios"]}


def _guardar_imagen(contenido, url, sha, vigente, items=None):
    IMAGENES.mkdir(exist_ok=True)
    ext = Path(url.split("?")[0]).suffix.lower() or ".jpg"
    base = IMAGENES / f"{vigente or 'sin-fecha'}_{sha[:12]}"
    base.with_suffix(ext).write_bytes(contenido)
    if items is not None:
        base.with_suffix(".ocr.json").write_text(json.dumps(items, ensure_ascii=False), encoding="utf-8")


def leer_candidata(repo, cand, motor, hoy, vistos, reprocesar, log):
    try:
        if cand.get("ruta"):
            contenido, url = Path(cand["ruta"]).read_bytes(), cand["url"]
        else:
            contenido, url = fs.bajar(cand)
    except Exception as ex:
        log(f"    no se pudo descargar {cand['url']}: {type(ex).__name__}")
        return None
    sha = fs.sha256_bytes(contenido)
    previo = repo.hash_visto(sha)
    if sha in vistos or (previo and (previo in ("guardada", "duplicada") or not reprocesar)):
        log(f"    {url.rsplit('/', 1)[-1]}: ya vista (mismo contenido, estado {previo or 'esta corrida'})")
        return None
    vistos.add(sha)
    nombre = url.rsplit("/", 1)[-1]
    base = {"sha": sha, "url": url, "fuente": cand["fuente"]}

    try:
        items, ancho, alto = ocr_bytes(contenido, motor)
    except Exception as ex:
        repo.registrar_imagen(sha, url, cand["fuente"], "descartada", f"no se pudo leer la imagen: {type(ex).__name__}: {ex}")
        log(f"    {nombre}: imagen ilegible")
        return None
    puntaje = pp.puntaje(items)
    vig = pp.leer_vigencia(items)
    if puntaje < pp.UMBRAL:
        repo.registrar_imagen(sha, url, cand["fuente"], "descartada",
                              f"no parece la tabla de precios (puntaje {puntaje}/{pp.UMBRAL})", puntaje=puntaje)
        log(f"    {nombre}: no parece la tabla de precios (puntaje {puntaje})")
        return None
    if vig is None:
        _guardar_imagen(contenido, url, sha, None, items)
        repo.registrar_imagen(sha, url, cand["fuente"], "descartada", "no se encontró 'PRECIOS A PARTIR DEL ...'", puntaje=puntaje)
        log(f"    {nombre}: sin fecha de vigencia")
        return None
    if vig > hoy + timedelta(days=MAX_FUTURO_DIAS):
        _guardar_imagen(contenido, url, sha, vig.isoformat(), items)
        repo.registrar_imagen(sha, url, cand["fuente"], "descartada",
                              f"vigencia {vig} demasiado lejos en el futuro (¿año mal leído?)", vig.isoformat(), puntaje)
        log(f"    {nombre}: vigencia {vig} sospechosa")
        return None

    datos = pp.parsear_tabla(items, ancho, alto)
    log(f"    {nombre}: vigencia {vig}" + ("" if datos else " (el parser aún no pudo leer la tabla)"))
    return {**base, "vigente": vig.isoformat(), "puntaje": puntaje, "datos": datos, "contenido": contenido,
            "ctx": (items, ancho, alto)}


def _mismos_precios(a, b):
    ka, kb = val.por_clave(a), val.por_clave(b)
    return ka.keys() == kb.keys() and all(abs(ka[k]["precio"] - kb[k]["precio"]) < 0.005 for k in ka)


def integrar(repo, regs, log):
    guardadas, revision = [], []
    sem_db = repo.semanas()

    def a_revision(r, motivo, guardar_img=True):
        if guardar_img:
            _guardar_imagen(r["contenido"], r["url"], r["sha"], r["vigente"], r["ctx"][0])
        repo.registrar_imagen(r["sha"], r["url"], r["fuente"], "revision", motivo, r["vigente"], r["puntaje"])
        revision.append({"vigente": r["vigente"], "motivo": motivo})
        log(f"    REVISIÓN {r['vigente']}: {motivo}")

    nuevas = {}
    for r in sorted(regs, key=lambda r: (r["vigente"], r["datos"] is None)):
        v = r["vigente"]
        previa = sem_db[v]["datos"] if v in sem_db else (nuevas[v]["datos"] if v in nuevas else None)
        if v in sem_db or v in nuevas:
            if r["datos"] is not None and previa is not None and not _mismos_precios(previa, r["datos"]):
                if v in nuevas:
                    nuevas[v]["conflicto"] = f"otra imagen trae la misma vigencia {v} con precios distintos"
                a_revision(r, f"misma vigencia {v} que otra imagen, pero con precios distintos (no se sobrescribe)")
            else:
                nota = "semana ya guardada" + ("" if r["datos"] is not None else " (esta copia no se pudo leer)")
                repo.registrar_imagen(r["sha"], r["url"], r["fuente"], "duplicada", nota, v, r["puntaje"])
                log(f"    {v}: {nota}")
            continue
        nuevas[v] = r

    conocidas = {k: s["datos"] for k, s in sem_db.items()}
    for v in sorted(nuevas):
        r = nuevas[v]
        if r["datos"] is None:
            ant = (date.fromisoformat(v) - timedelta(days=7)).isoformat()
            if ant in conocidas:
                r["datos"] = pp.parsear_tabla(*r["ctx"], anterior=_anterior_de(conocidas[ant]))
                r["signo_deducido"] = r["datos"] is not None
        if r["datos"] is not None:
            conocidas[v] = r["datos"]

    legibles = []
    for v, r in sorted(nuevas.items()):
        if r.get("conflicto"):
            a_revision(r, r["conflicto"])
        elif r["datos"] is None:
            a_revision(r, "tabla detectada pero el parser no pudo leerla (¿diseño distinto?)")
        else:
            legibles.append(r)
    if not legibles:
        return {"guardadas": guardadas, "revision": revision}

    mezcla = [{"datos": s["datos"], "db": True, "estado_db": s["estado"], "vigente": k} for k, s in sem_db.items()]
    mezcla += [{"datos": r["datos"], "db": False, "rec": r, "vigente": r["vigente"]} for r in legibles]
    mezcla.sort(key=lambda s: s["vigente"])
    val.verificar_secuencia(mezcla)

    for i, s in enumerate(mezcla):
        if s["db"]:
            if s["estado_db"] == "sin_verificar" and s["estado"] == "confirmado":
                repo.actualizar_estado(s["vigente"], "confirmado")
                log(f"    {s['vigente']}: pasa de sin_verificar a confirmado")
            continue
        r, estado = s["rec"], s["estado"]
        if estado == "revision":
            fallos = [d for x in s["aristas"] if x["resultado"] == "fallo" for d in x["detalle"][:3]]
            a_revision(r, "; ".join(s["errores"] + fallos) or "no cuadra con la semana vecina")
            continue
        if estado == "sin_verificar":
            vecino = next((m for m in reversed(mezcla[:i]) if m["datos"] and m["vigente"] != s["vigente"]), None) \
                or next((m for m in mezcla[i + 1:] if m["datos"]), None)
            if vecino:
                saltos = val.plausible(vecino["datos"], s["datos"])
                if saltos:
                    a_revision(r, f"sin semana consecutiva para comparar y hay saltos sospechosos: {'; '.join(saltos[:3])}")
                    continue
        repo.guardar_semana(s["datos"], estado, r["fuente"], r["url"], r["sha"])
        _guardar_imagen(r["contenido"], r["url"], r["sha"], r["vigente"])
        repo.registrar_imagen(r["sha"], r["url"], r["fuente"], "guardada", estado, r["vigente"], r["puntaje"])
        guardadas.append({"vigente": r["vigente"], "estado": estado, "fuente": r["fuente"]})
        log(f"    GUARDADA {r['vigente']} ({estado}, fuente {r['fuente']})")
    return {"guardadas": guardadas, "revision": revision}


def ejecutar(repo, motor, fuentes=ORDEN_FUENTES, hoy=None, log=print, reprocesar=False, getters=None):
    hoy = hoy or date.today()
    getters = getters or fs.FUENTES
    vistos, errores, leidas = set(), [], 0
    guardadas, revision = [], []
    for nombre in fuentes:
        log(f"[{nombre}]")
        try:
            cands = getters[nombre]()
        except Exception as ex:
            errores.append(f"{nombre}: {type(ex).__name__}: {ex}")
            log(f"    falló: {type(ex).__name__}: {ex}")
            continue
        if not cands:
            errores.append(f"{nombre}: no se encontró la imagen de precios")
            log("    no se encontró la imagen de precios")
            continue
        regs = []
        for c in cands:
            r = leer_candidata(repo, c, motor, hoy, vistos, reprocesar, log)
            leidas += 1
            if r:
                regs.append(r)
        res = integrar(repo, regs, log) if regs else {"guardadas": [], "revision": []}
        guardadas += [dict(g) for g in res["guardadas"]]
        revision += res["revision"]
        if guardadas:
            break
        log("    nada nuevo en esta fuente")

    if guardadas:
        ult = max(guardadas, key=lambda g: g["vigente"])
        resultado, fuente, vig = "nueva_semana", ult["fuente"], ult["vigente"]
        detalle = "; ".join(f"{g['vigente']} ({g['estado']})" for g in guardadas)
    elif revision:
        resultado, fuente, vig = "revision", None, None
        detalle = "; ".join(f"{r['vigente']}: {r['motivo']}" for r in revision)[:1500]
    elif not leidas:
        resultado, fuente, vig, detalle = "fuente_caida", None, None, " | ".join(errores)[:1500]
    else:
        resultado, fuente, vig = "sin_cambios", None, None
        detalle = " | ".join(errores)[:1500] or None
    repo.registrar_ejecucion(resultado, fuente, vig, detalle)
    ultimo = escribir_salida(repo, hoy)
    return {"resultado": resultado, "guardadas": guardadas, "revision": revision, "errores": errores, "ultimo": ultimo}


def escribir_salida(repo, hoy=None):
    hoy = hoy or date.today()
    sem = repo.semanas()
    if not sem:
        return None
    v = next(reversed(sem))
    dias = (hoy - date.fromisoformat(v)).days
    out = {"actualizado": v, "estado": sem[v]["estado"], "revisado_en": datetime.now().isoformat(timespec="seconds"),
           "desactualizado": dias > DIAS_DESACTUALIZADO, **val.anidar(sem[v]["datos"])}
    out.pop("vigente_desde")
    SALIDA.mkdir(exist_ok=True)
    (SALIDA / "ultimo.json").write_text(json.dumps(out, indent=1, ensure_ascii=False), encoding="utf-8")
    return out


NOMBRES = {"super": "Súper", "regular": "Regular", "diesel": "Diésel", "kerosene": "Kerosene",
           "glp_domestico": "GLP doméstico", "glp_vehicular": "GLP vehicular"}


def texto_precios(u):
    lineas = [f"Última actualización: {u['actualizado']}  (estado: {u['estado']})"]
    for c, n in NOMBRES.items():
        p = u["precios"][c]
        lineas.append(f"  {n:<14} Tegucigalpa L {p['tegucigalpa']:.2f} | San Pedro Sula L {p['san_pedro_sula']:.2f}")
    if "apoyo_glp_domestico" in u:
        a = u["apoyo_glp_domestico"]
        lineas.append(f"  (Apoyo económico temporal GLP doméstico: Tegucigalpa L {a['tegucigalpa']:.2f}, "
                      f"San Pedro Sula L {a['san_pedro_sula']:.2f})")
    return "\n".join(lineas)


def cmd_estado(repo):
    u = escribir_salida(repo)
    if not u:
        print("La tabla combustibles está vacía. Corre: python scraper.py run")
        return 1
    print(texto_precios(u))
    e = repo.ultima_ejecucion()
    if e:
        print(f"\nÚltima corrida: {e['ejecutado_en']:%Y-%m-%d %H:%M} -> {e['resultado']}" + (f" ({e['detalle']})" if e["detalle"] else ""))
    ok = repo.ultima_exitosa()
    print(f"Última semana nueva guardada por el scraper: {ok:%Y-%m-%d %H:%M}" if ok else "Aún no hay semanas guardadas por el scraper (solo datos cargados a mano o seed).")
    if u["desactualizado"]:
        print(f"\n⚠ DATOS POSIBLEMENTE DESACTUALIZADOS: la última vigencia ({u['actualizado']}) tiene más de {DIAS_DESACTUALIZADO} días.")
        return 1
    return 0


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd")
    r = sub.add_parser("run", help="corrida normal")
    r.add_argument("--fuentes", default=",".join(ORDEN_FUENTES), help="orden de fuentes separado por comas")
    r.add_argument("--reprocesar", action="store_true")
    a = sub.add_parser("archivo", help="procesar imágenes locales")
    a.add_argument("rutas", nargs="+")
    a.add_argument("--reprocesar", action="store_true")
    sub.add_parser("estado")
    sub.add_parser("init-db")
    for p in (r, a):
        p.add_argument("--motor", choices=["rapidocr", "easyocr"], default="rapidocr")
    args = ap.parse_args(argv)
    args.cmd = args.cmd or "run"
    if args.cmd == "run" and not hasattr(args, "fuentes"):
        args = ap.parse_args(["run"])

    repo = db.Repo()
    if args.cmd == "init-db":
        print(f"Tablas listas en {config.db_url().render_as_string(hide_password=True)}")
        return 0
    if args.cmd == "estado":
        return cmd_estado(repo)

    motor = pp.ocr_rapidocr if args.motor == "rapidocr" else pp.ocr_easyocr
    if args.cmd == "archivo":
        cands = [{"url": str(Path(p).resolve()), "ruta": p, "alternativas": [], "fuente": "archivo", "subida": None}
                 for p in args.rutas]
        getters = {"archivo": lambda: cands}
        res = ejecutar(repo, motor, ("archivo",), reprocesar=args.reprocesar, getters=getters)
    else:
        fuentes = tuple(f.strip() for f in args.fuentes.split(",") if f.strip())
        malas = [f for f in fuentes if f not in fs.FUENTES]
        if malas:
            ap.error(f"fuente desconocida: {malas}. Opciones: {list(fs.FUENTES)}")
        res = ejecutar(repo, motor, fuentes, reprocesar=args.reprocesar)

    print(f"\nResultado: {res['resultado']}")
    if res["ultimo"]:
        print(texto_precios(res["ultimo"]))
        print(f"\nJSON listo en {SALIDA / 'ultimo.json'}")
    return {"fuente_caida": 2, "revision": 1}.get(res["resultado"], 0)


if __name__ == "__main__":
    sys.exit(main())
