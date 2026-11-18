#!/usr/bin/env bash
# Publica o gateway Mercado Pago Checkout Pro numa instalação do WHMCS, com backup.
#
# Uso:
#   ./deploy.sh                       # deploy em ~/public_html (pede confirmação)
#   ./deploy.sh --dry-run             # mostra o que mudaria, sem copiar
#   ./deploy.sh --yes                 # sem confirmação
#   WHMCS=/outro/caminho ./deploy.sh  # outro destino
#
# Copia só os arquivos do gateway:
#   modules/gateways/mercadopagopro.php
#   modules/gateways/mercadopagopro/      (espelhado, com --delete)
#   modules/gateways/callback/mercadopagopro.php
set -euo pipefail

WHMCS="${WHMCS:-$HOME/public_html}"
SRC="$(cd "$(dirname "$0")" && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$HOME/WHMCS-MercadoPago-backups}"
FILES=( "modules/gateways/mercadopagopro.php" "modules/gateways/callback/mercadopagopro.php" )
DIR_REL="modules/gateways/mercadopagopro"
DRY=0
YES=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY=1 ;;
    --yes|-y)  YES=1 ;;
    *) echo "Opção desconhecida: $arg"; exit 1 ;;
  esac
done

WHMCS="$(cd "$WHMCS" && pwd)"
if [[ ! -f "$WHMCS/init.php" || ! -d "$WHMCS/modules/gateways/callback" ]]; then
  echo "ERRO: $WHMCS não parece ser uma instalação do WHMCS."; exit 1
fi

echo "Origem : $SRC"
echo "Destino: $WHMCS"
[[ $DRY -eq 1 ]] && echo "(simulação: nada será alterado)"

if [[ $YES -eq 0 && $DRY -eq 0 ]]; then
  read -r -p "Confirmar deploy? [s/N] " ans
  [[ "$ans" =~ ^[sSyY]$ ]] || { echo "Cancelado."; exit 0; }
fi

if [[ $DRY -eq 0 ]]; then
  existing=()
  for f in "${FILES[@]}" "$DIR_REL"; do [[ -e "$WHMCS/$f" ]] && existing+=( "$f" ); done
  if [[ ${#existing[@]} -gt 0 ]]; then
    mkdir -p "$BACKUP_DIR"
    STAMP="$(date +%Y%m%d-%H%M%S)"
    tar -czf "$BACKUP_DIR/mercadopagopro-$STAMP.tar.gz" -C "$WHMCS" "${existing[@]}"
    echo "Backup : $BACKUP_DIR/mercadopagopro-$STAMP.tar.gz"
    echo "         (restaurar: tar -xzf $BACKUP_DIR/mercadopagopro-$STAMP.tar.gz -C $WHMCS)"
    ls -1t "$BACKUP_DIR"/mercadopagopro-*.tar.gz 2>/dev/null | tail -n +21 | xargs -r rm -f
  fi
fi

RSYNC=( rsync -a --chmod=D755,F644 --itemize-changes --exclude='.DS_Store' --exclude='*.log' --exclude='*.bak' )
[[ $DRY -eq 1 ]] && RSYNC+=( --dry-run )

for f in "${FILES[@]}"; do
  "${RSYNC[@]}" "$SRC/$f" "$WHMCS/$f"
done
mkdir -p "$WHMCS/$DIR_REL"
"${RSYNC[@]}" --delete "$SRC/$DIR_REL/" "$WHMCS/$DIR_REL/"
[[ $DRY -eq 0 ]] && echo "Deploy concluído."
