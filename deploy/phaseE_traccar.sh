#!/usr/bin/env bash
# Phase E — Traccar 6.14.5 (Docker) + dedicated traccar-db (MariaDB) container.
# Exposes ONLY 5011 (Wialon device port) publicly and 8082 (UI) on localhost.
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
. /root/salam_secrets.env
DIR=/opt/salam-traccar

echo "===== PHASE E: Traccar (Docker) ====="

echo "--- E1: install Docker CE + compose plugin ---"
if ! command -v docker >/dev/null; then
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" > /etc/apt/sources.list.d/docker.list
  apt-get update -y
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker

echo "--- E2: traccar config + compose ---"
mkdir -p "$DIR"

cat > "$DIR/traccar.xml" <<EOF
<?xml version='1.0' encoding='UTF-8'?>
<!DOCTYPE properties SYSTEM 'http://java.sun.com/dtd/properties.dtd'>
<properties>
    <entry key='config.default'>./conf/default.xml</entry>

    <entry key='database.driver'>org.mariadb.jdbc.Driver</entry>
    <entry key='database.url'>jdbc:mariadb://traccar-db:3306/traccar?allowPublicKeyRetrieval=true&amp;useSSL=false&amp;serverTimezone=UTC</entry>
    <entry key='database.user'>traccar</entry>
    <entry key='database.password'>${TRACCAR_DB_PASSWORD}</entry>

    <entry key='web.port'>8082</entry>

    <!-- GT06 decoder port (Jimi VL110C). Traccar's default is 5023; pinned for clarity. -->
    <entry key='gt06.port'>5023</entry>

    <!-- Forward positions to the Salam backend ingestion webhook (R-GSM, v1.2) -->
    <entry key='forward.enable'>true</entry>
    <entry key='forward.url'>http://host.docker.internal/v1/traccar/forward?token=${TRACCAR_FORWARD_TOKEN}</entry>
    <entry key='forward.type'>json</entry>
</properties>
EOF

cat > "$DIR/docker-compose.yml" <<EOF
services:
  traccar-db:
    image: mariadb:11.4
    container_name: traccar-db
    restart: unless-stopped
    environment:
      MARIADB_ROOT_PASSWORD: ${TRACCAR_DB_PASSWORD}
      MARIADB_DATABASE: traccar
      MARIADB_USER: traccar
      MARIADB_PASSWORD: ${TRACCAR_DB_PASSWORD}
    command: --innodb-buffer-pool-size=256M --explicit-defaults-for-timestamp=1
    volumes:
      - traccar-db-data:/var/lib/mysql
    networks: [traccarnet]
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 30

  traccar:
    image: traccar/traccar:6.14.5
    container_name: traccar
    restart: unless-stopped
    depends_on:
      traccar-db:
        condition: service_healthy
    ports:
      - "127.0.0.1:8082:8082"
      # Host 5011 (device-facing, firewalled, gps.salamheyetimiz.com) -> container 5039 = Traccar's
      # WIALON decoder. NB: container 5011 is the SUNTECH decoder (verified 2026-06-26), NOT Wialon.
      - "5011:5039"
      # Host 5023 (device-facing) -> container 5023 = Traccar's GT06 decoder (Jimi VL110C).
      # Online-only onboarding (2026-07). Requires UFW `allow 5023/tcp` (phaseH_ufw.sh).
      - "5023:5023"
    extra_hosts:
      - "host.docker.internal:host-gateway"
    environment:
      _JAVA_OPTIONS: "-Xms512m -Xmx1024m"
    mem_limit: 1536m
    volumes:
      - "$DIR/traccar.xml:/opt/traccar/conf/traccar.xml:ro"
      - traccar-logs:/opt/traccar/logs
    networks: [traccarnet]

networks:
  traccarnet:

volumes:
  traccar-db-data:
  traccar-logs:
EOF
chmod 600 "$DIR/traccar.xml" "$DIR/docker-compose.yml"

echo "--- E3: pull + start ---"
cd "$DIR"
docker compose pull -q
docker compose up -d

echo "--- E4: wait for Traccar web (schema init via Liquibase) ---"
for i in $(seq 1 30); do
  code=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8082/ || true)
  if [ "$code" = "200" ] || [ "$code" = "302" ] || [ "$code" = "301" ]; then echo "  traccar web up (http=$code) after ${i}x"; break; fi
  sleep 4
done

echo "===== VERIFICATION ====="
echo "[containers]"; docker compose ps --format '  {{.Name}}  {{.Image}}  {{.Status}}'
echo "[traccar web]"; curl -s -o /dev/null -w "  http=%{http_code}\n" http://127.0.0.1:8082/
echo "[listening 8082/5011/5023]"; ss -tlnpH 'sport = :8082 or sport = :5011 or sport = :5023' | awk '{print "  "$4}'
echo "[traccar schema tables]"; docker exec traccar-db mariadb -utraccar -p"${TRACCAR_DB_PASSWORD}" -N traccar -e "SELECT CONCAT('tables=',COUNT(*)) FROM information_schema.tables WHERE table_schema='traccar';" 2>/dev/null
echo "[traccar log tail]"; docker logs --tail 6 traccar 2>&1 | sed 's/^/  /'
echo "[mem]"; free -h | grep -E 'Mem|Swap'
echo "===== PHASE E DONE ====="
