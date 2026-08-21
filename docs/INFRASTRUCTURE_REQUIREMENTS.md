# Infrastructure Requirements — Production Deployment

**Date:** 2026-06-14
**Scope:** production infrastructure for the Salam Həyətimiz platform with the v1.2 transport
(UMKa 310 ↔ self-hosted Traccar ↔ Laravel backend). Numeric per-tier sizing is in
`SERVER_SIZING_GUIDE.md`; this document covers topology, component roles, the Traccar
co-location decision, monitoring/backups, and scalability.
**Workload note:** barriers are **stationary, mains-powered** devices reporting infrequently — telemetry
volume is far lower than a moving-vehicle fleet of the same count. Opens are bursty but low in absolute
daily volume. Sizing is therefore modest and driven by **persistent device sessions + DB durability +
availability**, not raw throughput.

---

## 1. Components & roles

| # | Component | Role | Notes |
|---|---|---|---|
| 1 | **Traccar Server** | Holds the UMKa devices' persistent Wialon sessions; sends open commands; ingests telemetry; forwards events to the backend. | Java/Netty. **Its own database** (do not share the app DB). Internet-facing inbound device port. |
| 2 | **Laravel Backend** | Control plane: auth, subscriptions, cooldown, audit, open-command lifecycle, Traccar command calls, event-forward ingestion. | nginx + PHP-FPM + **Horizon** workers (queues: open, device-comm, payments, notifications, default). |
| 3 | **MariaDB** | Application database (our schema — partitioned `open_commands`, etc.). | 11.x InnoDB. Separate from Traccar's DB. Backups + (≥1k) a replica. |
| 4 | **Redis** | Cache, queue backend, **open cooldown (SETNX)**, Horizon, Reverb pub/sub. | 7.x. HA (Sentinel) at scale. |
| 5 | **Reverb / WebSocket** | Real-time open-command state push (`private-user.{id}`); polling `/v1/commands/{id}` is the contractual fallback (R-ARCH-12). | Optimisation, not a launch blocker. 2 nodes behind a sticky LB at scale. |
| 6 | **Monitoring & Backups** | Metrics, logs, alerting, DB + Traccar backups. | See §4. |

## 2. Topology

```
Internet
  ├── UMKa devices ──(Wialon IPS/Combine, persistent TCP)──► Traccar  ──REST/forward──► Laravel backend
  ├── Mobile apps  ──(HTTPS /v1, WSS Reverb)──────────────► LB ─► Laravel backend ──► MariaDB / Redis
  └── Admin panel  ──(HTTPS /admin/v1)─────────────────────►            backend ──REST──► Traccar (commands)
                                              SMS provider ◄──(fallback open)── backend
```

- Backend → Traccar over a **private network** (REST); Traccar → backend forward webhook over the private
  network with a shared secret. Devices reach Traccar's inbound port over the public internet (firewalled
  to the device port + the operator IP ranges where possible).
- TLS terminates at the LB/nginx for `/v1` and `/admin/v1`; Reverb over WSS.

## 3. Traccar: same server vs dedicated server

**A) Same server as Laravel**
- ✅ Pros: cheapest; one box to operate; fine at small scale (≤ ~500 devices) with container resource limits.
- ❌ Cons: JVM (Traccar) + PHP-FPM + MariaDB + Redis + Reverb contend for CPU/RAM/IO; Traccar's DB writes
  compete with the app DB; a Traccar incident (it's an internet-facing inbound service) shares the blast
  radius with the backend; independent scaling/patching is harder.

**B) Dedicated server (recommended)**
- ✅ Pros: isolates the internet-facing device-ingest surface (security blast-radius); independent scaling,
  patching, and restart; Traccar JVM tuning without affecting PHP; Traccar keeps its own DB locally.
- ❌ Cons: one more host to provision/monitor; a small private-network hop for commands.

**Final recommendation:**
- **100–500 devices:** co-location is acceptable **if** Traccar runs in its own container with CPU/RAM
  limits and **its own database** (separate MariaDB instance/container, never the app schema). Prefer
  dedicating if budget allows.
- **≥ 1,000 devices:** **dedicate Traccar (and its database) to a separate host — required.**
- **Always:** Traccar uses a **separate database** from the app MariaDB, regardless of co-location, because
  its schema, write pattern, and retention differ.

## 4. Monitoring & backups (all tiers)

- **Metrics/alerting:** node + service metrics (CPU/RAM/disk/IO), MariaDB (connections, slow queries,
  replication lag), Redis (memory, evictions), Horizon (queue depth, failed jobs, wait time), Traccar
  (active sessions, command success rate), backend (open p95, `failed/expired` rate, 5xx). Prometheus +
  Grafana (self-host) or a hosted APM. Alert on: open failure-rate spike, queue backlog, device mass-offline,
  Traccar session drop, replication lag, disk > 80%.
- **Backups:** MariaDB app DB — nightly full + binlog/PITR, 30-day retention, **off-site**, monthly
  restore test. Traccar DB — nightly. Redis — RDB/AOF snapshot (state is reconstructible, lower criticality).
  Config/secrets — version-controlled (secrets in a vault, not git). JWT signing keys — backed up securely.
- **Logging:** centralised (audit_log is in-DB per spec; app/Traccar/nginx logs shipped to a log store).

## 5. Scalability considerations

- **Backend:** stateless → scale horizontally behind the LB; Horizon workers scale by queue depth.
- **Traccar:** vertical first (it's efficient for stationary low-frequency devices); a single instance
  comfortably handles 5,000 such devices. Beyond ~10k or for HA, run multiple Traccar instances (device
  sharding) — out of scope for current tiers.
- **MariaDB:** primary + read replica at ≥1k; `open_commands` is monthly-partitioned with 24-mo retention +
  archival (DB Arch §6.1) — keeps the hot set bounded. Vertical scale + replica covers all four tiers.
- **Redis:** single node ≤500; Sentinel HA ≥1k (cooldown + queue + Reverb depend on it).
- **Reverb:** 2 nodes + sticky LB at ≥1k; clients fall back to 1 s polling (R-ARCH-12) so Reverb is never a
  hard dependency.

---

*Numeric CPU/RAM/storage per tier + the IT-department provisioning message are in `SERVER_SIZING_GUIDE.md`.*
