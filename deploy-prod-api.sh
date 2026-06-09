#!/bin/bash
set -e

SSH_USER="u967889760"
SSH_HOST="82.112.247.211"
SSH_PORT="65002"
REMOTE_PATH="domains/misturadeluz.com/public_html/agenda/api"
SSH_TARGET="${SSH_USER}@${SSH_HOST}"

echo "==> [PRODUÇÃO - API] Enviando arquivos para o servidor..."
rsync -avz --delete \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='env.*' \
  --exclude='vendor/' \
  --exclude='deploy*.sh' \
  --exclude='test.php' \
  --exclude='*.local' \
  -e "ssh -p ${SSH_PORT}" \
  ./ "${SSH_TARGET}:${REMOTE_PATH}/"

echo "==> [PRODUÇÃO - API] Enviando .env.production como .env..."
scp -P "${SSH_PORT}" .env.production "${SSH_TARGET}:${REMOTE_PATH}/.env"

echo "==> [PRODUÇÃO - API] Instalando dependências e ajustando permissões..."
ssh -p "${SSH_PORT}" "${SSH_TARGET}" bash << EOF
  set -e
  cd "${REMOTE_PATH}"
  composer install --no-dev --optimize-autoloader --no-interaction
  chmod 644 .env
  echo "==> API de produção online!"
EOF

echo ""
echo "  API: https://agenda.misturadeluz.com/api"
