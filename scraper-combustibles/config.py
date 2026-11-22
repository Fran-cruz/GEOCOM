import os
from pathlib import Path

from dotenv import load_dotenv
from sqlalchemy.engine import URL

BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR / ".env")


def db_url() -> URL:
    conn = os.getenv("DB_CONNECTION", "sqlite").strip().lower()
    if conn == "sqlite":
        ruta = Path(os.getenv("DB_DATABASE") or "gasscraper.sqlite")
        if not ruta.is_absolute():
            ruta = BASE_DIR / ruta
        return URL.create("sqlite", database=str(ruta))
    if conn in ("mysql", "mariadb"):
        return URL.create(
            "mysql+pymysql",
            username=os.getenv("DB_USERNAME") or "root",
            password=os.getenv("DB_PASSWORD") or None,
            host=os.getenv("DB_HOST") or "127.0.0.1",
            port=int(os.getenv("DB_PORT") or 3306),
            database=os.getenv("DB_DATABASE") or "GasScraper",
            query={"charset": "utf8mb4"},
        )
    raise ValueError(f"DB_CONNECTION={conn!r} no soportado (usa sqlite o mysql)")


def contacto() -> str:
    return os.getenv("SCRAPER_CONTACTO") or "CAMBIAR@correo.com"


def user_agent() -> dict:
    return {"User-Agent": f"Mozilla/5.0 (proyecto universitario GasScraper; contacto: {contacto()})"}
