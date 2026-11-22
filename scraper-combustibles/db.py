from datetime import date, datetime

from sqlalchemy import (CHAR, Column, Date, DateTime, Index, Integer, MetaData, Numeric, String, Table, Text,
                        UniqueConstraint, create_engine, func, insert, select, update)

import config

meta = MetaData()

combustibles = Table(
    "combustibles", meta,
    Column("id", Integer, primary_key=True, autoincrement=True),
    Column("combustible", String(20), nullable=False),
    Column("ciudad", String(20), nullable=False),
    Column("precio", Numeric(10, 2), nullable=False),
    Column("variacion", Numeric(10, 2), nullable=False),
    Column("apoyo", Numeric(10, 2)),
    Column("vigente_desde", Date, nullable=False),
    Column("estado", String(20), nullable=False),
    Column("fuente", String(20), nullable=False),
    Column("imagen_url", String(500)),
    Column("imagen_sha256", CHAR(64)),
    Column("created_at", DateTime, nullable=False, default=datetime.now),
    UniqueConstraint("combustible", "ciudad", "vigente_desde", name="uq_combustible_ciudad_semana"),
    Index("ix_combustibles_vigente", "vigente_desde"),
)

scraper_imagenes = Table(
    "scraper_imagenes", meta,
    Column("sha256", CHAR(64), primary_key=True),
    Column("url", String(500)),
    Column("fuente", String(20), nullable=False),
    Column("vigente_desde", Date),
    Column("puntaje", Integer),
    Column("estado", String(20), nullable=False),
    Column("motivo", Text),
    Column("procesada_en", DateTime, nullable=False, default=datetime.now),
)

scraper_ejecuciones = Table(
    "scraper_ejecuciones", meta,
    Column("id", Integer, primary_key=True, autoincrement=True),
    Column("ejecutado_en", DateTime, nullable=False, default=datetime.now),
    Column("resultado", String(30), nullable=False),
    Column("fuente", String(20)),
    Column("vigente_desde", Date),
    Column("detalle", Text),
)


def _f(x):
    return None if x is None else float(x)


class Repo:
    def __init__(self, engine=None):
        self.engine = engine or create_engine(config.db_url(), future=True)
        meta.create_all(self.engine)

    def semanas(self):
        with self.engine.connect() as c:
            filas = c.execute(select(combustibles).order_by(combustibles.c.vigente_desde, combustibles.c.id)).mappings().all()
        out = {}
        for f in filas:
            k = f["vigente_desde"].isoformat()
            s = out.setdefault(k, {"datos": {"vigente_desde": k, "precios": []}, "estado": f["estado"]})
            s["datos"]["precios"].append({"combustible": f["combustible"], "ciudad": f["ciudad"],
                                          "precio": _f(f["precio"]), "variacion": _f(f["variacion"])})
            if f["apoyo"] is not None:
                s["datos"].setdefault("apoyo_glp_domestico", {})[f["ciudad"]] = _f(f["apoyo"])
        return dict(sorted(out.items()))

    def ultima(self):
        s = self.semanas()
        if not s:
            return None
        k = next(reversed(s))
        return k, s[k]["estado"]

    def hash_visto(self, sha):
        with self.engine.connect() as c:
            return c.execute(select(scraper_imagenes.c.estado).where(scraper_imagenes.c.sha256 == sha)).scalar()

    def ultima_ejecucion(self):
        with self.engine.connect() as c:
            r = c.execute(select(scraper_ejecuciones).order_by(scraper_ejecuciones.c.id.desc()).limit(1)).mappings().first()
        return dict(r) if r else None

    def ultima_exitosa(self):
        with self.engine.connect() as c:
            return c.execute(select(func.max(scraper_ejecuciones.c.ejecutado_en))
                             .where(scraper_ejecuciones.c.resultado == "nueva_semana")).scalar()

    def guardar_semana(self, datos, estado, fuente, url=None, sha=None):
        apoyo = datos.get("apoyo_glp_domestico", {})
        filas = [{"combustible": p["combustible"], "ciudad": p["ciudad"], "precio": p["precio"],
                  "variacion": p["variacion"],
                  "apoyo": apoyo.get(p["ciudad"]) if p["combustible"] == "glp_domestico" else None,
                  "vigente_desde": date.fromisoformat(datos["vigente_desde"]), "estado": estado,
                  "fuente": fuente, "imagen_url": url, "imagen_sha256": sha, "created_at": datetime.now()}
                 for p in datos["precios"]]
        with self.engine.begin() as c:
            c.execute(insert(combustibles), filas)

    def actualizar_estado(self, vigente, estado):
        with self.engine.begin() as c:
            c.execute(update(combustibles).where(combustibles.c.vigente_desde == date.fromisoformat(vigente))
                      .values(estado=estado))

    def registrar_imagen(self, sha, url, fuente, estado, motivo=None, vigente=None, puntaje=None):
        v = {"url": url, "fuente": fuente, "vigente_desde": date.fromisoformat(vigente) if vigente else None,
             "puntaje": puntaje, "estado": estado, "motivo": motivo, "procesada_en": datetime.now()}
        with self.engine.begin() as c:
            if c.execute(select(scraper_imagenes.c.sha256).where(scraper_imagenes.c.sha256 == sha)).first():
                c.execute(update(scraper_imagenes).where(scraper_imagenes.c.sha256 == sha).values(**v))
            else:
                c.execute(insert(scraper_imagenes).values(sha256=sha, **v))

    def registrar_ejecucion(self, resultado, fuente=None, vigente=None, detalle=None):
        with self.engine.begin() as c:
            c.execute(insert(scraper_ejecuciones).values(
                ejecutado_en=datetime.now(), resultado=resultado, fuente=fuente,
                vigente_desde=date.fromisoformat(vigente) if vigente else None, detalle=detalle))
