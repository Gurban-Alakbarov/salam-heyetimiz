# BLE Feasibility Report — UMKa 310 v2L (Phase-0)

**Date:** 2026-06-14
**Question:** should BLE remain the **primary local-open** mechanism?
**Verdict:** **No — not as the secure primary, not with the native mechanism.** BLE on the UMKa 310 is an
**iBeacon identification** facility (UUID match), not an authenticated phone→device command channel.
Recommend **Traccar as the primary for in-person opens too**, with BLE only as an optional, security-caveated, foreground convenience — or deferred.

---

## 1. What the hardware actually does (✅ from the UMKa manual)

- Bluetooth **v4.0 BLE** (Table 1.2). BLE features: BLE FLS/sensors (§2.13/§3.15/App. F), **BLE
  identification — iBeacon** (§2.22, §3.17, `BLEID` cmd #107), and configuration-over-Bluetooth (§3.20).
- BLE identification (§3.17): the tracker runs in **receiver or beacon** mode and matches identifiers by
  **UUID**. The "BLE-triggered output script" (owner-confirmed) therefore works as: *the tracker detects a
  recognised iBeacon UUID → an on-device script fires OUT0.*
- The tracker is a BLE **central reading sensors / scanning for beacons** — it is **not** designed to
  expose a writable GATT command characteristic for a consumer phone app to drive the relay.

## 2. Why BLE is not a secure primary open

1. **No authentication.** iBeacon identity is a static UUID (+ optional major/minor). Anyone advertising
   the same UUID opens the gate — **trivially clonable / replayable**. There is no challenge-response,
   no rolling credential. For an access-control product this is a security defect, not a feature.
2. **iOS background advertising is restricted.** iOS suppresses the iBeacon UUID when the app is
   backgrounded/locked; reliable iBeacon advertising needs the app **foregrounded**. "Phone in pocket,
   walk up, gate opens" is **not achievable on iOS** with this mechanism.
3. **Android is workable but uneven.** Background BLE advertising needs a foreground service and varies by
   chipset; iBeacon-format advertising is not uniformly supported.
4. **MAC randomisation.** If identification were MAC-based rather than UUID-based, modern phones randomise
   BLE MACs, breaking a MAC allow-list. UUID-based avoids this but reintroduces (1).
5. **Provisioning is device-side config.** Adding/removing an authorised iBeacon is a device configuration
   change (`BLEID` / identification tab), i.e. an outbox/provisioning operation per device — not a clean
   per-user credential the backend can issue and revoke instantly at scale.

## 3. Options considered

| Option | Security | iOS | Verdict |
|---|---|---|---|
| Phone advertises recognised iBeacon UUID → device script fires | ❌ clonable, unauthenticated | ❌ foreground-only | Not a secure primary; at best a foreground "tap to open when near" convenience with the clone risk accepted. |
| Per-user unique UUID provisioned to the device | Slightly better (revocable per user) but still cloneable while valid; device UUID-set capacity limits | ❌ foreground-only | Marginal; heavy provisioning; still unauthenticated. |
| Custom GATT command characteristic (phone writes a signed token) | Could be secure | depends | ⚠️ **Not supported by the documented UMKa BLE** (it's a sensor/identification central, not a command peripheral). Would need vendor firmware confirmation — treat as unavailable. |
| **Traccar remote command for in-person opens too** | ✅ server-authorised, audited | ✅ works | **Recommended.** The user taps in-app (online), backend authorises in real time, Traccar fires `OUTPUT0`. |

## 4. Impact on the approved architecture (must be reconciled)

The `FINAL_TRANSPORT_DECISION.md` made **BLE the primary local-open** and Traccar the remote primary. This
report **contradicts that assumption**: the UMKa cannot serve as a secure BLE lock for a phone app out of
the box. Recommended revision:

- **Primary (local + remote): Traccar** `OUTPUT0`/`cmdout.p` command (server-authorised, audited, works on
  both platforms; latency depends on the live session — measure in B1).
- **Fallback: SMS.**
- **BLE: optional, deferred** — only if a foreground-only, clone-risk-accepted "near-field tap" is desired,
  or if vendor firmware later exposes an authenticated GATT command path. Not on the MVP critical path.

This **simplifies** the build (the high-risk BLE entitlement/provisioning/reconciliation work and the
R-DOM-05 BLE exception become optional rather than primary) and removes the largest 09-B unknown (B4).

## 5. Required confirmations before any BLE work

- ⚠️ Exact UMKa "BLE identification system" doc (referenced in §2.22) — how a script binds to a detected
  iBeacon, capacity of the identifier set, update latency.
- ⚠️ Whether iOS/Android can advertise the required iBeacon format reliably for the intended UX.
- ⚠️ Product acceptance of the clone/replay risk if BLE is used at all.

---

*Recommendation: drop BLE from the MVP critical path; make Traccar the primary for in-person and remote.
This is a material change to `FINAL_TRANSPORT_DECISION.md` and needs owner sign-off (see
`PHASE0_TRANSPORT_VALIDATION.md` §Recommendations).*
