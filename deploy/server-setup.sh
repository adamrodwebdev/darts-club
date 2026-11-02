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
apt-get install -yq ufw fail2ban python3-systemd unattended-upgrades age curl ca-certificates rsync

echo "→ Mises à jour de sécurité automatiques"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
# Le moteur Docker (runc, containerd : les évasions de conteneur se corrigent là) vient du dépôt de Docker,
# que unattended-upgrades ignore par défaut. Un noyau ou une libc corrigés ne servent qu'après redémarrage :
# fait la nuit, seulement quand une mise à jour l'exige (les conteneurs repartent seuls)
cat > /etc/apt/apt.conf.d/52skull-darts-71-upgrades <<'EOF'
Unattended-Upgrade::Origins-Pattern {
        "origin=Docker";
};
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "04:15";
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
# Clé de l'administrateur (celle utilisée pour cette connexion)
for source in /root/.ssh/authorized_keys /home/debian/.ssh/authorized_keys; do
  [ -f "$source" ] && cat "$source" >> /home/deploy/.ssh/authorized_keys
done
sort -u -o /home/deploy/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys

install -d -m 750 -o deploy -g deploy "$APP_DIR"
install -d -m 700 -o deploy -g deploy "$BACKUP_DIR"

# Clé de GitHub Actions : déploiement seulement (commande imposée deploy/ssh-gate.sh, installée ici pour
# le premier déploiement ; les suivants la mettent à jour depuis l'image)
if [ -n "$DEPLOY_KEY" ]; then
  here=$(dirname "$0")
  install -d -m 755 -o deploy -g deploy "$APP_DIR/deploy"
  install -m 755 -o deploy -g deploy "$here/ssh-gate.sh" "$here/authorize-github-key.sh" "$APP_DIR/deploy/"
  printf '%s\n' "$DEPLOY_KEY" | AUTHORIZED_KEYS=/home/deploy/.ssh/authorized_keys sh "$APP_DIR/deploy/authorize-github-key.sh" > /dev/null
fi

echo "→ Pare-feu : SSH, HTTP, HTTPS uniquement"
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "→ SSH : clés uniquement, pas de connexion root"
# Préfixe 00 : lu avant 50-cloud-init.conf (la première valeur rencontrée l'emporte)
rm -f /etc/ssh/sshd_config.d/90-skull-darts-71.conf
# Ni affichage graphique ni agent relayés (un agent relayé prête les clés de l'ordinateur au serveur) ;
# 30 s pour s'authentifier
cat > /etc/ssh/sshd_config.d/00-skull-darts-71.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
X11Forwarding no
AllowAgentForwarding no
LoginGraceTime 30
EOF
sshd -t && systemctl reload ssh

echo "→ fail2ban (bannit les IP qui multiplient les échecs SSH)"
# Debian 12 : pas de /var/log/auth.log, lecture du journal systemd
cat > /etc/fail2ban/jail.local <<'EOF'
[DEFAULT]
backend = systemd
bantime = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
EOF
systemctl enable fail2ban
systemctl restart fail2ban

echo "✓ Serveur prêt : se connecter désormais avec « ssh deploy@<serveur> »"
