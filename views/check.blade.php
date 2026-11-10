echo "== OS"; lsb_release -ds 2>/dev/null || cat /etc/os-release 2>/dev/null | head -2 || true
echo "== Kernel"; uname -r
echo "== PHP"
for d in /etc/php/*/; do
  [ -d "$d" ] || continue
  v=$(basename "$d")
  case "$v" in [0-9]*.[0-9]*) ;; *) continue ;; esac
  if command -v "php$v" >/dev/null 2>&1; then "php$v" -v 2>/dev/null | head -1 || true; fi
  dpkg-query -W -f='  ${Package} ${Version}\n' "php$v-fpm" "php$v-cli" 2>/dev/null || true
  systemctl is-active "php$v-fpm" 2>/dev/null | sed "s/^/  php$v-fpm: /" || true
done
echo "== Web"
if command -v nginx >/dev/null 2>&1; then nginx -v 2>&1 || true; fi
caddy version 2>/dev/null || true
echo "== Redis"; redis-server --version 2>/dev/null || true
echo "== Database"; mysql --version 2>/dev/null || true; psql --version 2>/dev/null || true
echo "== Node"; node -v 2>/dev/null || true
echo "== Composer"; composer --version 2>/dev/null | head -1 || true
echo "== Pending updates (php/nginx/redis/mysql/openssl)"
apt list --upgradable 2>/dev/null | grep -Ei 'php|nginx|redis|mysql|mariadb|openssl|libssl' || echo "none"
echo "== Unattended upgrades"; systemctl is-enabled unattended-upgrades 2>/dev/null || true
echo "== Last apt update"; stat -c %y /var/lib/apt/periodic/update-success-stamp 2>/dev/null || echo "unknown"
