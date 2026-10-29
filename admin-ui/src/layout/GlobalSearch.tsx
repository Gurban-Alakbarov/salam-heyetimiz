import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Building2, HardDrive, Loader2, ReceiptText, Search, UserRound } from 'lucide-react'
import { useGlobalSearch } from '@/api/search'
import { Input } from '@/components/ui/input'
import { formatMoney } from '@/lib/format'
import { useDebouncedValue } from '@/lib/useDebouncedValue'

interface Hit {
  key: string
  icon: typeof Search
  title: string
  sub: string
  to: string
}

/**
 * One search box for the whole panel: devices (IMEI / name / SIM), people (name / email / phone), complexes and
 * orders. A person opens the support card (their gates, subscription, last open) — the usual reason to look someone up.
 */
export function GlobalSearch() {
  const [q, setQ] = useState('')
  const [open, setOpen] = useState(false)
  const term = useDebouncedValue(q, 300)
  const { data, isFetching } = useGlobalSearch(term)
  const navigate = useNavigate()
  const box = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const close = (e: MouseEvent) => { if (box.current && !box.current.contains(e.target as Node)) setOpen(false) }
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  const hits: { group: string; items: Hit[] }[] = data ? [
    { group: 'Cihazlar', items: data.devices.map((d) => ({ key: `d${d.id}`, icon: HardDrive, title: d.label, sub: d.serial, to: `/devices/${d.id}` })) },
    { group: 'İnsanlar', items: data.people.map((p) => ({
      key: `p${p.id}`, icon: UserRound, title: p.name ?? p.email ?? p.phone ?? `#${p.id}`, sub: [p.email, p.phone].filter(Boolean).join(' · '),
      to: `/support?q=${encodeURIComponent(p.email ?? p.phone ?? '')}`,
    })) },
    { group: 'Komplekslər', items: data.complexes.map((c) => ({ key: `c${c.id}`, icon: Building2, title: c.name, sub: c.code, to: `/complexes/${c.id}` })) },
    { group: 'Sifarişlər', items: data.orders.map((o) => ({ key: `o${o.id}`, icon: ReceiptText, title: `Sifariş #${o.id}`, sub: formatMoney(o.amount_minor, o.currency), to: `/orders/${o.id}` })) },
  ].filter((g) => g.items.length > 0) : []

  const go = (to: string) => { setOpen(false); setQ(''); navigate(to) }
  const showPanel = open && term.trim().length >= 2

  return (
    <div ref={box} className="relative w-full max-w-md">
      <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
      <Input
        value={q}
        onChange={(e) => { setQ(e.target.value); setOpen(true) }}
        onFocus={() => setOpen(true)}
        onKeyDown={(e) => { if (e.key === 'Escape') setOpen(false); if (e.key === 'Enter' && hits[0]?.items[0]) go(hits[0].items[0].to) }}
        placeholder="Axtar: IMEI, telefon, e-poçt, kompleks, sifariş №…"
        className="h-9 pl-8"
        aria-label="Ümumi axtarış"
      />
      {showPanel && (
        <div className="absolute left-0 right-0 z-40 mt-1 max-h-[70vh] overflow-y-auto rounded-md border bg-card p-1 shadow-lg">
          {isFetching && hits.length === 0 ? (
            <div className="flex items-center gap-2 px-3 py-2 text-sm text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> Axtarılır…</div>
          ) : hits.length === 0 ? (
            <div className="px-3 py-2 text-sm text-muted-foreground">Heç nə tapılmadı</div>
          ) : hits.map((g) => (
            <div key={g.group} className="py-1">
              <p className="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{g.group}</p>
              {g.items.map((h) => {
                const Icon = h.icon
                return (
                  <button key={h.key} type="button" onClick={() => go(h.to)}
                    className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-muted">
                    <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                    <span className="truncate font-medium">{h.title}</span>
                    {h.sub && <span className="ml-auto truncate text-xs text-muted-foreground">{h.sub}</span>}
                  </button>
                )
              })}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
