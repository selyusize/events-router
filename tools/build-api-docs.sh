#!/usr/bin/env bash
# Генерирует справочник API (docs/api/*.md) из кода и PHPDoc.
# Используется и локально (make docs-api), и в CI (.github/workflows/docs.yml).
set -euo pipefail

cd "$(dirname "$0")/.."

PHPDOC_VERSION="3.10.0"
PHAR="var/tools/phpDocumentor-${PHPDOC_VERSION}.phar"

if [[ ! -f "$PHAR" ]]; then
    mkdir -p var/tools
    curl -sSfL -o "$PHAR" \
        "https://github.com/phpDocumentor/phpDocumentor/releases/download/v${PHPDOC_VERSION}/phpDocumentor.phar"
fi

rm -rf docs/api
php "$PHAR" run --no-interaction --quiet

# Генератор сайта открывает раздел по index.md, шаблон называет главную страницу Home.md
{ printf '# Справочник API\n\n'; cat docs/api/Home.md; } > docs/api/index.md
rm docs/api/Home.md

# Шаблон saggre/phpdocumentor-markdown экранирует % в описаниях как %%
find docs/api -name '*.md' -exec perl -pi -e 's/%%/%/g' {} +
