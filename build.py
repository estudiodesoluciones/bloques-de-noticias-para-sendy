"""Build the distributable ZIP without site data or development files."""
from pathlib import Path
import re
import zipfile

root = Path(__file__).resolve().parent
version = re.search(r"Version:\s*([\d.]+)", (root / "estudio-de-soluciones-bloques-de-noticias-para-sendy.php").read_text()).group(1)
output = root / "dist" / f"estudio-de-soluciones-bloques-de-noticias-para-sendy-{version}.zip"
output.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(root.rglob("*")):
        relative = path.relative_to(root)
        if not path.is_file() or any(part.startswith(".") for part in relative.parts):
            continue
        if relative.parts[0] in ("includes", "assets") or str(relative) in (
            "estudio-de-soluciones-bloques-de-noticias-para-sendy.php", "uninstall.php", "readme.txt", "LICENSE"
        ):
            archive.write(path, "estudio-de-soluciones-bloques-de-noticias-para-sendy/" + relative.as_posix())
print(output)
