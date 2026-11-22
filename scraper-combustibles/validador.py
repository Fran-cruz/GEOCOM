from datetime import date

COMBUSTIBLES = ["super", "regular", "diesel", "kerosene", "glp_domestico", "glp_vehicular"]
CIUDADES = ["tegucigalpa", "san_pedro_sula"]
RANGOS = {"super": (50, 300), "regular": (50, 300), "diesel": (50, 300),
          "kerosene": (50, 300), "glp_domestico": (20, 400), "glp_vehicular": (20, 200)}
TOLERANCIA = 0.02


def por_clave(datos):
    return {(p["combustible"], p["ciudad"]): p for p in datos["precios"]}


def errores_propios(d):
    k, errs = por_clave(d), []
    faltan = [f"{c}/{z}" for c in COMBUSTIBLES for z in CIUDADES if (c, z) not in k]
    if faltan or len(d["precios"]) != 12:
        errs.append(f"faltan o sobran precios: {faltan}")
    for (c, z), p in k.items():
        lo, hi = RANGOS.get(c, (0, 0))
        if not lo <= p["precio"] <= hi:
            errs.append(f"{c}/{z}: {p['precio']} fuera de rango ({lo}-{hi})")
    return errs


def comparar(previo, actual):
    dias = (date.fromisoformat(actual["vigente_desde"]) - date.fromisoformat(previo["vigente_desde"])).days
    if dias != 7:
        return "hueco", [f"{dias} días entre {previo['vigente_desde']} y {actual['vigente_desde']}"]
    ant, det = por_clave(previo), []
    for (c, z), p in por_clave(actual).items():
        esperado = round(ant[(c, z)]["precio"] + p["variacion"], 2)
        if abs(esperado - p["precio"]) > TOLERANCIA:
            det.append(f"{c}/{z}: {ant[(c, z)]['precio']:.2f} {p['variacion']:+.2f} = {esperado:.2f}, pero se leyó {p['precio']:.2f}")
    return ("fallo" if det else "ok"), det


def verificar_secuencia(semanas):
    for s in semanas:
        s["errores"], s["aristas"] = errores_propios(s["datos"]), []
    validas = [s for s in semanas if not s["errores"]]
    for a, b in zip(validas, validas[1:]):
        res, det = comparar(a["datos"], b["datos"])
        a["aristas"].append({"con": b["datos"]["vigente_desde"], "resultado": res, "detalle": det})
        b["aristas"].append({"con": a["datos"]["vigente_desde"], "resultado": res, "detalle": det})
    respaldo = {s["datos"]["vigente_desde"]: any(x["resultado"] == "ok" for x in s["aristas"]) for s in semanas}
    for s in semanas:
        ok = respaldo[s["datos"]["vigente_desde"]]
        fallos = [x for x in s["aristas"] if x["resultado"] == "fallo"]
        contradice = any(respaldo[x["con"]] for x in fallos)
        s["estado"] = ("revision" if s["errores"] or (fallos and (not ok or contradice))
                       else "confirmado" if ok else "sin_verificar")
    return semanas


MAX_CAMBIO = 0.15


def plausible(previo, actual):
    ant, det = por_clave(previo), []
    for k, p in por_clave(actual).items():
        if k in ant and ant[k]["precio"] and abs(p["precio"] / ant[k]["precio"] - 1) > MAX_CAMBIO:
            det.append(f"{k[0]}/{k[1]}: {ant[k]['precio']:.2f} -> {p['precio']:.2f} (salto > {MAX_CAMBIO:.0%})")
    return det


def anidar(d):
    precios = {c: {} for c in COMBUSTIBLES}
    var = {c: {} for c in COMBUSTIBLES}
    for p in d["precios"]:
        precios[p["combustible"]][p["ciudad"]] = p["precio"]
        var[p["combustible"]][p["ciudad"]] = p["variacion"]
    out = {"vigente_desde": d["vigente_desde"], "precios": precios, "variaciones": var}
    if "apoyo_glp_domestico" in d:
        out["apoyo_glp_domestico"] = d["apoyo_glp_domestico"]
    return out


def filas_bd(d, **extra):
    return [{"combustible": p["combustible"], "ciudad": p["ciudad"], "precio": p["precio"],
             "variacion": p["variacion"], "vigente_desde": d["vigente_desde"], **extra}
            for p in d["precios"]]
