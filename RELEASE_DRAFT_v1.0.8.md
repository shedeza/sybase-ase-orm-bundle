# Release v1.0.8 (Draft)

Fecha: 2025-11-10

Resumen
-------

Versión menor que corrige problemas de validación y mejora la experiencia al instalar el bundle en proyectos Symfony que usan placeholders de entorno (`%env(...)%`) o parámetros. También incluye mejoras de documentación y un pequeño fix en el parser de URLs.

Cambios principales
------------------

- Fix: DatabaseUrlParser ahora rechaza correctamente URLs inválidas y exige el scheme `sybase://` cuando se parsea una URL literal. (arregla fallos en tests y en runtime cuando se pasaba una URL mal formada).
- Fix: `Configuration` (validación de configuración) ahora permite placeholders como `%env(DATABASE_SYBASE_URL)%` o `%some_param%` y no falla durante la ejecución de recetas/`cache:clear`.
- Fix: `SybaseAseOrmExtension` no intenta parsear placeholders en tiempo de compilación — sólo parsea literal `sybase://` URLs (se recortan comillas antes de parsear). Esto evita errores durante `composer install`/`composer update` cuando la URL viene de `.env` o parámetros de Symfony.
- Docs: Actualizado `README.md` con instrucciones ampliadas sobre FreeTDS / `pdo_dblib`, ejemplos de `.env` y solución de problemas.
- Release: bump a `1.0.8` y tag anotado `v1.0.8` creado.

Commits incluidos
-----------------

- fix(dbal): require 'sybase' scheme in DatabaseUrlParser to reject malformed URLs
- fix(config): accept %env(...)% and param placeholders in connection URL validation; relax DB name chars
- fix(config): avoid parsing env/param placeholders in connection URL; only parse literal sybase:// URLs
- docs: improve installation and Sybase/FreeTDS setup in README
- chore(release): bump version to 1.0.8 and update README badge

Instrucciones para publicar el draft en GitHub
--------------------------------------------

1) Usando la CLI `gh` (GitHub CLI) — más sencillo si la tienes instalada y autenticada:

```bash
# crear un draft de release con título y contenido del fichero
gh release create v1.0.8 --title "v1.0.8" --notes-file RELEASE_DRAFT_v1.0.8.md --draft
```

2) Usando la API (curl) — necesitas un token con `repo` scope en `GITHUB_TOKEN`:

```bash
curl -X POST \
  -H "Authorization: token $GITHUB_TOKEN" \
  -H "Accept: application/vnd.github+json" \
  https://api.github.com/repos/<OWNER>/<REPO>/releases \
  -d @- <<'JSON'
{
  "tag_name": "v1.0.8",
  "name": "v1.0.8",
  "body": "$(sed -n '1,200p' RELEASE_DRAFT_v1.0.8.md | sed ':a;N;$!ba;s/"/\\"/g')",
  "draft": true,
  "prerelease": false
}
JSON
```

Revisión antes de publicar
--------------------------

- Revisa el contenido de `RELEASE_DRAFT_v1.0.8.md` y ajusta notas si quieres añadir más detalles (ejemplos, referencias a issues o PRs).
- Si deseas, puedo generar un changelog más detallado basado en los commits entre `v1.0.7..v1.0.8` y añadir referencias de PRs/issues si me indicas cómo prefieres el formato.

Notas técnicas
--------------

- Tests: la suite de tests existente se ejecutó y pasó (11 tests, 23 assertions) en el entorno local.
- Recomendación: eliminar la clave "version" de `composer.json` y usar únicamente tags para publicar versiones en Packagist/GitHub releases.

---
Este archivo es solo un borrador generado automáticamente por la herramienta de mantenimiento del repositorio. Ajusta el contenido antes de publicarlo.
