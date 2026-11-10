#!/usr/bin/env bash
# LER BEM — verificação de sintaxe de todos os arquivos PHP do projeto.
# Uso: bash scripts/check-php.sh
# Sai com código 1 se qualquer arquivo tiver erro de sintaxe.
set -uo pipefail

fail=0
count=0

while IFS= read -r -d '' file; do
    count=$((count + 1))
    if ! out=$(php -l "$file" 2>&1); then
        echo "FALHA: $file"
        echo "$out"
        fail=1
    fi
done < <(find . -name '*.php' -not -path './.git/*' -print0)

echo "Arquivos PHP verificados: $count"

if [ "$fail" -ne 0 ]; then
    echo "Sintaxe PHP: FALHOU"
    exit 1
fi

echo "Sintaxe PHP: OK"
