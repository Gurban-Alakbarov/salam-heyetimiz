# Server Sizing Guide — Salam Həyətimiz (v1.2 / UMKa + Traccar)

**Date:** 2026-06-14
**Companion:** `INFRASTRUCTURE_REQUIREMENTS.md` (topology, roles, Traccar co-location decision).
**Sizing basis:** **stationary, mains-powered** barriers — low, infrequent telemetry (~1 msg/min/device
when static, plus open events). Opens are bursty but low in daily volume. All figures are **production
with headroom**; non-prod can be smaller. Tiers: **100 / 500 / 1,000 / 5,000 devices** (assume ~1–5 mobile
users per device).

**Global standards (all components, all tiers):**
- **OS:** Ubuntu Server **24.04 LTS** (22.04 LTS acceptable), x86-64.
- **Docker vs bare metal:** **Docker** for Backend, Traccar, Redis, Reverb, Monitoring (Compose ≤ 1k;
  orchestration optional at 5k). **MariaDB:** Docker acceptable ≤ 1k; **bare-metal or managed DB at 5k**
  for predictable IO. Put DB data on **NVMe SSD**.
- **Network:** backend↔Traccar↔DB↔Redis on a **private subnet**; only the LB (443/WSS) and the Traccar
  device port are internet-facing (firewalled).

---

## Tier 1 — 100 devices (single box, co-located)

| Component | vCPU | RAM | Storage | Placement |
|---|---|---|---|---|
| Backend (nginx+PHP-FPM+Horizon) | shared | — | — | **1 VM total** |
| Traccar + its DB | shared | — | — | (containers on the one VM, with limits) |
| MariaDB (app) | shared | — | — | |
| Redis / Reverb | shared | — | — | |
| **VM TOTAL** | **2–4 vCPU** | **8 GB** | **80 GB SSD** | 1 VM |
| Monitoring/backups | 1 vCPU | 2 GB | 60 GB | small VM or hosted |

Capacity: trivial. Scalability: vertical only; split out when approaching Tier 2.

## Tier 2 — 500 devices (co-location acceptable, Traccar containerised w/ own DB)

| Component | vCPU | RAM | Storage |
|---|---|---|---|
| Backend + Redis + Reverb | 4 | 8 GB | 60 GB |
| MariaDB (app) | 2 | 4 GB | 80 GB |
| Traccar + Traccar DB | 2 | 4 GB | 60 GB |
| **Recommended:** 1–2 VMs (e.g. app+DB+Redis on one, Traccar on another) | — | **~12–16 GB total** | — |
| Monitoring/backups | 2 | 4 GB | 100 GB |

Capacity: comfortable. Scalability: prefer separating Traccar now; ready to split DB next.

## Tier 3 — 1,000 devices (Traccar DEDICATED — required; DB replica)

| Component | vCPU | RAM | Storage | Host |
|---|---|---|---|---|
| Backend (app node) | 4 | 8 GB | 80 GB | App VM |
| MariaDB primary | 4 | 8 GB | 150 GB NVMe | DB VM |
| MariaDB read replica | 4 | 8 GB | 150 GB NVMe | DB-replica VM |
| Redis (Sentinel HA, 3 small) | 2 | 2 GB | 20 GB | Redis VM(s) |
| Reverb | 1 | 2 GB | 20 GB | (on app VM or its own) |
| Traccar + Traccar DB | 2 | 4 GB | 80 GB | **Dedicated Traccar VM** |
| Monitoring/backups | 2 | 4 GB | 150 GB | Mon VM |

Capacity: ample. Scalability: backend horizontal-ready; DB primary+replica; Redis HA.

## Tier 4 — 5,000 devices (tiered, HA)

| Component | vCPU | RAM | Storage | Host |
|---|---|---|---|---|
| Backend app nodes ×2 (HA, behind LB) | 8 each | 16 GB each | 100 GB each | 2 App VMs |
| MariaDB primary | 8 | 32 GB | 400–500 GB NVMe | DB VM (bare-metal/managed) |
| MariaDB read replica | 8 | 32 GB | 400–500 GB NVMe | DB-replica VM |
| Redis (Sentinel HA) | 4 | 4–8 GB | 40 GB | Redis cluster |
| Reverb ×2 (sticky LB) | 2 each | 4 GB each | 20 GB each | 2 Reverb VMs |
| Traccar + Traccar DB | 4 | 8 GB | 250 GB | **Dedicated Traccar VM** |
| Monitoring/backups | 4 | 8 GB | 300 GB + object storage | Mon VM |

Capacity: a single Traccar instance handles 5,000 stationary devices; backend scales horizontally; DB is
the watch-item (buffer pool ≈ 70% RAM; partition pruning + 24-mo retention keep the hot set bounded).
Scalability beyond: add app nodes; shard Traccar only past ~10k devices.

---

## Per-component requirement summary

| Component | CPU driver | RAM driver | Storage driver | Scale path |
|---|---|---|---|---|
| **Traccar** | persistent sessions (light) | JVM heap (1–4 GB) | stored positions × retention | vertical to 5k; shard past ~10k |
| **Laravel Backend** | PHP-FPM + Horizon workers | per-worker memory | logs/app (small) | **horizontal** behind LB |
| **MariaDB** | query + write volume | **innodb_buffer_pool** (≈70% RAM) | `open_commands` (partitioned, 24-mo) + payments/audit | vertical + read replica |
| **Redis** | low | dataset (cache+queue+cooldown) small | snapshot | Sentinel HA |
| **Reverb** | connection count | per-connection memory | none | 2+ nodes, sticky LB |
| **Monitoring** | scrape/store | retention | metrics+logs retention | scale store |

---

## Message for IT Department

> **Subject: Server provisioning — Salam Həyətimiz (barrier access platform)**
>
> Please provision the following (Ubuntu Server 24.04 LTS, x86-64, NVMe SSD, private subnet + one
> internet-facing LB). Pick the block matching the target device count. All hosts: daily backups,
> monitoring agent, automatic security updates.
>
> **Up to 500 devices (pilot/small):**
> - **VM-APP** — 4 vCPU, 8 GB RAM, 80 GB SSD — runs Backend (nginx/PHP-FPM/Horizon) + Redis + Reverb (Docker).
> - **VM-DB** — 2 vCPU, 4 GB RAM, 100 GB SSD — MariaDB 11 (app database).
> - **VM-TRACCAR** — 2 vCPU, 4 GB RAM, 80 GB SSD — Traccar + its own MariaDB (internet-facing device port; firewalled).
> - **VM-MON** — 2 vCPU, 4 GB RAM, 100 GB SSD — monitoring + backup target (or use a managed service + object storage).
>
> **1,000 devices:**
> - **VM-APP** — 4 vCPU, 8 GB, 80 GB.
> - **VM-DB-PRIMARY** — 4 vCPU, 8 GB, 150 GB NVMe (MariaDB primary).
> - **VM-DB-REPLICA** — 4 vCPU, 8 GB, 150 GB NVMe (MariaDB read replica).
> - **VM-REDIS** — 2 vCPU, 2 GB, 20 GB (Redis + Sentinel; HA recommended).
> - **VM-TRACCAR** — 2 vCPU, 4 GB, 80 GB (Traccar + its DB; **dedicated, required**).
> - **VM-MON** — 2 vCPU, 4 GB, 150 GB.
>
> **5,000 devices:**
> - **VM-APP-1 / VM-APP-2** — 8 vCPU, 16 GB, 100 GB each (behind a load balancer).
> - **VM-DB-PRIMARY** — 8 vCPU, 32 GB, 500 GB NVMe (MariaDB primary; bare-metal/managed preferred).
> - **VM-DB-REPLICA** — 8 vCPU, 32 GB, 500 GB NVMe.
> - **VM-REDIS** — 4 vCPU, 8 GB (Redis Sentinel HA).
> - **VM-REVERB-1 / VM-REVERB-2** — 2 vCPU, 4 GB each (WebSocket, sticky LB).
> - **VM-TRACCAR** — 4 vCPU, 8 GB, 250 GB (Traccar + its DB; **dedicated, required**).
> - **VM-MON** — 4 vCPU, 8 GB, 300 GB + object storage for backups.
>
> **Networking:** one public LB (443 HTTPS + WSS); Traccar device-ingest port public but firewalled
> (restrict to the SIM operators' IP ranges where feasible); everything else private. **Traccar always
> uses a separate database from the application** (co-located only ≤ 500 devices; dedicated host ≥ 1,000).

---

*Figures are production estimates with headroom for stationary-barrier telemetry; revisit after the
Phase-0 latency/volume measurements (`PHASE0_TRANSPORT_VALIDATION.md` T2) provide real numbers.*
