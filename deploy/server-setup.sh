#!/bin/sh
# Préparation d'un serveur Debian 12 neuf (à lancer une fois, en root ou avec sudo).
# Idempotent : peut être relancé sans dommage.
#   sudo sh deploy/server-setup.sh "<clé publique SSH de déploiement GitHub Actions>"
set -eu

DEPLOY_KEY="${1:-}"
APP_DIR=/opt/skull-darts-71
BACKUP_DIR=/var/backups/skull-darts-71

echo "→ Mises à jour du système"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get full-upgrade -yq
apt-get install -yq ufw fail2ban unattended-upgrades age curl ca-certificates rsync

echo "→ Mises à jour de sécurité automatiques"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF

echo "→ Docker"
if ! command -v docker > /dev/null; then
  curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

echo "→ Compte de déploiement « deploy » (sans mot de passe, clés SSH seulement)"
id deploy > /dev/null 2>&1 || adduser --disabled-password --gecos '' deploy
usermod -aG docker deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
touch /home/deploy/.ssh/authorized_keys
# Clé de l'administrateur (celle utilisée pour cette connexion) + clé de GitHub Actions
for source in /root/.ssh/authorized_keys /home/debian/.ssh/authorized_keys; do
  [ -f "$source" ] && cat "$source" >> /home/deploy/.ssh/authorized_keys
done
[ -n "$DEPLOY_KEY" ] && echo "$DEPLOY_KEY" >> /home/deploy/.ssh/authorized_keys
sort -u -o /home/deploy/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys

install -d -m 750 -o deploy -g deploy "$APP_DIR"
install -d -m 700 -o deploy -g deploy "$BACKUP_DIR"

echo "→ Pare-feu : SSH, HTTP, HTTPS uniquement"
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "→ SSH : clés uniquement, pas de connexion root"
cat > /etc/ssh/sshd_config.d/90-skull-darts-71.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
EOF
sshd -t && systemctl reload ssh

echo "→ fail2ban (protection SSH contre les essais de mots de passe)"
systemctl enable --now fail2ban

echo "✓ Serveur prêt : se connecter désormais avec « ssh deploy@<serveur> »"
