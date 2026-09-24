#!/usr/bin/env bash
# Nasazení Kompasu na vas-hosting.cz (webhosting bez SSH).
#
# Sestaví aplikaci z commitu HEAD (bez necommitnutých změn), zabalí ji do
# release.zip, nahraje přes FTP spolu s jednorázovým instalátorem a ten zip
# na serveru rozbalí, spustí migrace, storage:link a optimize a smaže se.
#
# Potřebuje: .env.deploy (přístupy), lftp, composer, npm.
# Výrobní .env se při prvním běhu vygeneruje do .env.production (gitignored)
# a dál se jen znovu nahrává – APP_KEY tak zůstává stejný.
#
# Použití: scripts/deploy.sh            (https://kompas.lachmart.cz)
#          DEPLOY_SCHEME=http scripts/deploy.sh   (než bude HTTPS)
set -euo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
HOST=kompas.lachmart.cz
SCHEME=${DEPLOY_SCHEME:-https}

set -a
# shellcheck disable=SC1091
source "$ROOT/.env.deploy"
set +a
WEB_IP=${WEB_IP:-$FTP_HOST}

dotenv_quote() {
    # Single quotes are literal in phpdotenv; fall back to double quotes with
    # escaping when the value itself contains a single quote.
    if [[ "$1" == *"'"* ]]; then
        local escaped=${1//\\/\\\\}
        escaped=${escaped//\"/\\\"}
        escaped=${escaped//\$/\\\$}
        printf '"%s"' "$escaped"
    else
        printf "'%s'" "$1"
    fi
}

if [[ ! -f "$ROOT/.env.production" ]]; then
    echo "→ Generuji .env.production"
    APP_KEY=$(cd "$ROOT" && php artisan key:generate --show)
    cat > "$ROOT/.env.production" <<EOF
APP_NAME=Kompas
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=https://$HOST
APP_LOCALE=cs
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=cs_CZ
APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$(dotenv_quote "$DB_PASSWORD")

SESSION_DRIVER=database
SESSION_LIFETIME=720
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

# Bez Reverbu: sledování běží přes obnovování každých 5 s, fronta synchronně.
BROADCAST_CONNECTION=null
QUEUE_CONNECTION=sync
CACHE_STORE=database
FILESYSTEM_DISK=local

# Hosting blokuje stream_socket_client, SMTP proto jde přes fsockopen
# (mailer "hosting", viz app/Mail/FsockopenSmtpStream.php).
MAIL_MAILER=hosting
MAIL_HOST=$MAIL_HOST
MAIL_PORT=$MAIL_PORT
MAIL_USERNAME=$MAIL_USERNAME
MAIL_PASSWORD=$(dotenv_quote "$MAIL_PASSWORD")
MAIL_FROM_ADDRESS=$MAIL_FROM_ADDRESS
MAIL_FROM_NAME=Kompas

# Veřejná registrace dozorů (každý vidí jen svoje hry).
REGISTRATION_ENABLED=true
EOF
fi

BUILD=$(mktemp -d)
trap 'rm -rf "$BUILD"' EXIT
RELEASE="$BUILD/release"
mkdir -p "$RELEASE"

echo "→ Exportuji $(git -C "$ROOT" rev-parse --short HEAD)"
git -C "$ROOT" archive HEAD | tar -x -C "$RELEASE"

echo "→ Composer (bez dev balíčků)"
(cd "$RELEASE" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet)

echo "→ Build frontendu (bez Reverbu = polling)"
(cd "$RELEASE" && npm ci --no-audit --no-fund --silent && VITE_APP_NAME=Kompas VITE_REVERB_APP_KEY= npm run build --silent >/dev/null)

(
    cd "$RELEASE"
    rm -rf node_modules tests docs scripts .github public/hot
    cp "$ROOT/scripts/deploy/root.htaccess" .htaccess
    cp "$ROOT/.env.production" .env
    zip -qr -X "$BUILD/release.zip" . -x '.git*' 'storage/logs/*.log'
)
echo "→ release.zip $(du -h "$BUILD/release.zip" | cut -f1)"

TOKEN=$(openssl rand -hex 16)
INSTALLER="_deploy-$TOKEN.php"
cp "$ROOT/scripts/deploy/installer.php" "$BUILD/$INSTALLER"

echo "→ Nahrávám přes FTP do $FTP_DIR"
LFTP_PASSWORD="$FTP_PASSWORD" lftp --env-password -u "$FTP_USER" "$FTP_HOST" <<EOF
set ftp:ssl-allow yes
set ssl:verify-certificate no
set net:max-retries 3
set net:timeout 30
mkdir -pf $FTP_DIR/public
put "$BUILD/release.zip" -o $FTP_DIR/release.zip
put "$ROOT/scripts/deploy/root.htaccess" -o $FTP_DIR/.htaccess
put "$BUILD/$INSTALLER" -o $FTP_DIR/public/$INSTALLER
bye
EOF

echo "→ Spouštím instalátor"
OUTPUT=$(curl -sS --max-time 300 \
    --resolve "$HOST:80:$WEB_IP" --resolve "$HOST:443:$WEB_IP" \
    "$SCHEME://$HOST/$INSTALLER" || true)
echo "$OUTPUT"

if [[ "$OUTPUT" != *"DEPLOY OK"* ]]; then
    echo "✗ Nasazení selhalo – instalátor výše vypsal, kde." >&2
    # The installer deletes itself on shutdown; make sure nothing lingers.
    LFTP_PASSWORD="$FTP_PASSWORD" lftp --env-password -u "$FTP_USER" -e "set ftp:ssl-allow yes; set ssl:verify-certificate no; rm -f $FTP_DIR/public/$INSTALLER; rm -f $FTP_DIR/release.zip; bye" "$FTP_HOST" || true
    exit 1
fi

echo "✓ Nasazeno: https://$HOST"
