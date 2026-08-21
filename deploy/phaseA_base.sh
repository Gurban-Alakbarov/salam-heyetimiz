#!/usr/bin/env bash
# Phase A — base system + hardening (NON-destructive; does NOT change SSH auth or enable UFW).
# Safe to re-run (idempotent).
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export NEEDRESTART_SUSPEND=1

echo "===== PHASE A: base + hardening (no SSH/UFW changes) ====="

echo "--- A1: apt update & upgrade ---"
apt-get update -y
apt-get -y upgrade

echo "--- A1b: base utilities ---"
apt-get install -y curl wget gnupg ca-certificates lsb-release software-properties-common \
  apt-transport-https unzip zip git htop vim ufw fail2ban jq rsync

echo "--- A3: timezone -> Asia/Baku ---"
timedatectl set-timezone Asia/Baku

echo "--- A2: swap (4G) ---"
if ! swapon --show | grep -q '/swapfile'; then
  fallocate -l 4G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=4096
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
fi
grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab

echo "--- A4: sysctl tuning ---"
cat > /etc/sysctl.d/99-salam.conf <<'EOF'
vm.swappiness=10
vm.overcommit_memory=1
net.core.somaxconn=1024
net.ipv4.tcp_max_syn_backlog=2048
fs.file-max=200000
EOF
sysctl --system >/dev/null

echo "--- A5: fail2ban sshd jail (does NOT touch sshd_config or UFW) ---"
cat > /etc/fail2ban/jail.local <<'EOF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
EOF
systemctl enable --now fail2ban
systemctl restart fail2ban

echo "===== VERIFICATION ====="
echo "[timezone]";  timedatectl | grep -E 'Time zone'
echo "[swap]";      swapon --show; free -h | grep -E 'Mem|Swap'
echo "[swappiness]"; echo "  vm.swappiness=$(sysctl -n vm.swappiness)"
echo "[fail2ban]";  fail2ban-client status sshd 2>/dev/null | sed 's/^/  /' || fail2ban-client status
echo "[installed]"; for p in curl git ufw fail2ban-client jq rsync; do command -v "$p" >/dev/null && echo "  $p OK" || echo "  $p MISSING"; done
echo "===== PHASE A DONE ====="
