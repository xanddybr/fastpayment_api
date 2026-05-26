#!/bin/bash
set -e

SSH_USER="u967889760"
SSH_HOST="82.112.247.211"
SSH_PORT="65002"
REMOTE_PATH="domains/misturadeluz.com/public_html/agendabeta"
SSH_TARGET="${SSH_USER}@${SSH_HOST}"

echo "==> Enviando arquivos para o servidor..."

rsync -avz --delete \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='vendor/' \
  --exclude='public/assets/' \
  --exclude='public/images/' \
  --exclude='public/index.html' \
  --exclude='public/vite.svg' \
  --exclude='deploy.sh' \
  --exclude='test.php' \
  --exclude='*.local' \
  -e "ssh -p ${SSH_PORT}" \
  ./ "${SSH_TARGET}:${REMOTE_PATH}/"

echo "==> Instalando dependências no servidor..."

ssh -p "${SSH_PORT}" "${SSH_TARGET}" bash << EOF
  set -e
  cd "${REMOTE_PATH}"
  composer install --no-dev --optimize-autoloader --no-interaction
  echo "==> Deploy concluído!"
EOF
