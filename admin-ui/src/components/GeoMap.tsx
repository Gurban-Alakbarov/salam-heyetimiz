import { useEffect, useRef, useState } from 'react'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { Crosshair, Loader2, MapPin, Search } from 'lucide-react'
import { type GeoPlace, geoReverse, geoSearch, useGeoTiles } from '@/api/geo'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useDebouncedValue } from '@/lib/useDebouncedValue'

/** Baku — where the map opens when nothing is picked yet. */
const DEFAULT_CENTER: L.LatLngExpression = [40.4093, 49.8671]
const OSM_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

/** A pin drawn with CSS/SVG (no marker image assets for the bundler to lose). */
const PIN = L.divIcon({
  className: '',
  html: '<svg width="34" height="44" viewBox="0 0 24 32" fill="#dc2626" stroke="white" stroke-width="1.5"><path d="M12 1C6 1 1.5 5.5 1.5 11.2 1.5 19 12 31 12 31s10.5-12 10.5-19.8C22.5 5.5 18 1 12 1z"/><circle cx="12" cy="11" r="3.8" fill="white" stroke="none"/></svg>',
  iconSize: [34, 44],
  iconAnchor: [17, 43],
})

function useLeaflet(el: React.RefObject<HTMLDivElement | null>, center: L.LatLngExpression, zoom: number) {
  const tiles = useGeoTiles()
  // state (not a ref) so the component re-renders once the map exists and its effects can hook in
  const [map, setMap] = useState<L.Map | null>(null)
  useEffect(() => {
    if (!el.current || tiles.isLoading) return
    const m = L.map(el.current, { center, zoom, scrollWheelZoom: true, attributionControl: true })
    L.tileLayer(tiles.data?.url ?? OSM_URL, {
      maxZoom: tiles.data?.max_zoom ?? 19,
      attribution: tiles.data?.url ? tiles.data.attribution : '© OpenStreetMap',
    }).addTo(m)
    setMap(m)
    return () => { m.remove(); setMap(null) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tiles.isLoading])
  return map
}

/** Read-only map with a pin (complex / application pages). */
export function GeoMapView({ latitude, longitude, height = 260 }: { latitude?: number | null; longitude?: number | null; height?: number }) {
  const el = useRef<HTMLDivElement>(null)
  const has = latitude != null && longitude != null
  const map = useLeaflet(el, has ? [latitude, longitude] : DEFAULT_CENTER, has ? 16 : 11)
  useEffect(() => {
    if (!map || !has) return
    const marker = L.marker([latitude, longitude], { icon: PIN }).addTo(map)
    map.setView([latitude, longitude], 16)
    return () => { marker.remove() }
  }, [map, has, latitude, longitude])

  if (!has) {
    return (
      <div className="flex items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground" style={{ height }}>
        <MapPin className="mr-2 h-4 w-4" /> Koordinat qeyd olunmayıb.
      </div>
    )
  }
  return <div ref={el} className="z-0 overflow-hidden rounded-md border" style={{ height }} />
}

export interface PickedLocation {
  lat: number
  lng: number
  /** full address from the pin (with house number when known); null when GoMap does not know it */
  address: string | null
}

/**
 * Location picker (GoMap.az): search an address → pick → the pin lands there; drag the pin or click the map to move
 * it; "Mənim yerim" uses the browser's GPS. Every time the pin stops, the address under it is looked up and passed
 * to onChange, so the form can fill its address field (the user may still edit it).
 */
export function GeoMapPicker({ value, onChange, height = 300 }: {
  value: { lat: number; lng: number } | null
  onChange: (picked: PickedLocation) => void
  height?: number
}) {
  const el = useRef<HTMLDivElement>(null)
  const map = useLeaflet(el, value ? [value.lat, value.lng] : DEFAULT_CENTER, value ? 16 : 11)
  const mapRef = useRef<L.Map | null>(null)
  mapRef.current = map
  const marker = useRef<L.Marker | null>(null)
  const onChangeRef = useRef(onChange)
  onChangeRef.current = onChange
  const [q, setQ] = useState('')
  const term = useDebouncedValue(q.trim(), 350)
  const [results, setResults] = useState<GeoPlace[]>([])
  const [searching, setSearching] = useState(false)
  const [searchError, setSearchError] = useState<string | null>(null)
  const [locating, setLocating] = useState(false)
  const [address, setAddress] = useState<string | null>(null)

  // pin moved (drag / click / search / GPS) → look up the address and report the new point
  const place = (lat: number, lng: number, knownAddress?: string) => {
    const m = mapRef.current
    if (!m) return
    if (!marker.current) {
      marker.current = L.marker([lat, lng], { icon: PIN, draggable: true }).addTo(m)
      marker.current.on('dragend', () => {
        const p = marker.current!.getLatLng()
        place(p.lat, p.lng)
      })
    } else {
      marker.current.setLatLng([lat, lng])
    }
    const r = (n: number) => Math.round(n * 1e7) / 1e7
    onChangeRef.current({ lat: r(lat), lng: r(lng), address: knownAddress ?? null })
    if (knownAddress) { setAddress(knownAddress); return }
    geoReverse(lat, lng)
      .then((a) => { setAddress(a.address); if (a.address) onChangeRef.current({ lat: r(lat), lng: r(lng), address: a.address }) })
      .catch(() => setAddress(null))
  }

  // map ready: show the current value, clicking the map moves the pin
  useEffect(() => {
    if (!map) return
    marker.current = null
    if (value) {
      marker.current = L.marker([value.lat, value.lng], { icon: PIN, draggable: true }).addTo(map)
      marker.current.on('dragend', () => { const p = marker.current!.getLatLng(); place(p.lat, p.lng) })
    }
    const click = (e: L.LeafletMouseEvent) => place(e.latlng.lat, e.latlng.lng)
    map.on('click', click)
    return () => { map.off('click', click) }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map])

  useEffect(() => {
    if (term.length < 2) { setResults([]); setSearchError(null); return }
    let alive = true
    setSearching(true)
    geoSearch(term)
      .then((r) => { if (alive) { setResults(r); setSearchError(r.length === 0 ? 'Heç nə tapılmadı' : null) } })
      .catch(() => { if (alive) { setResults([]); setSearchError('Axtarış müvəqqəti işləmir — pini xəritədə özünüz qoyun') } })
      .finally(() => { if (alive) setSearching(false) })
    return () => { alive = false }
  }, [term])

  const pick = (p: GeoPlace) => {
    setQ(''); setResults([])
    map?.setView([p.lat, p.lng], 17)
    place(p.lat, p.lng)
  }

  const gps = () => {
    if (!navigator.geolocation) return
    setLocating(true)
    navigator.geolocation.getCurrentPosition(
      (pos) => { setLocating(false); mapRef.current?.setView([pos.coords.latitude, pos.coords.longitude], 17); place(pos.coords.latitude, pos.coords.longitude) },
      () => { setLocating(false); setSearchError('Yeriniz alınmadı — brauzerdə məkan icazəsini yoxlayın') },
      { enableHighAccuracy: true, timeout: 15000 },
    )
  }

  return (
    <div className="space-y-2">
      <div className="relative flex gap-2">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
          <Input className="pl-8" placeholder="Ünvan və ya yer axtar…" value={q} onChange={(e) => setQ(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); if (results[0]) pick(results[0]) } }} />
          {searching && <Loader2 className="absolute right-2.5 top-2.5 h-4 w-4 animate-spin text-muted-foreground" />}
          {results.length > 0 && (
            <div className="absolute left-0 right-0 z-[1000] mt-1 max-h-64 overflow-y-auto rounded-md border bg-card p-1 shadow-lg">
              {results.map((p, i) => (
                <button key={`${p.lat},${p.lng},${i}`} type="button" onClick={() => pick(p)}
                  className="flex w-full flex-col items-start rounded px-2 py-1.5 text-left text-sm hover:bg-muted">
                  <span className="font-medium">{p.name}</span>
                  {p.address && p.address !== p.name && <span className="text-xs text-muted-foreground">{p.address}</span>}
                </button>
              ))}
            </div>
          )}
        </div>
        <Button type="button" variant="outline" onClick={gps} disabled={locating} title="Mənim yerim (GPS)">
          {locating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Crosshair className="h-4 w-4" />} Mənim yerim
        </Button>
      </div>
      {searchError && <p className="text-xs text-muted-foreground">{searchError}</p>}
      <div ref={el} className="z-0 overflow-hidden rounded-md border" style={{ height }} />
      <p className="text-xs text-muted-foreground">
        {value
          ? <>📍 {value.lat.toFixed(6)}, {value.lng.toFixed(6)}{address ? ` — ${address}` : ''} · pini sürüşdürə və ya xəritəyə basa bilərsiniz</>
          : 'Ünvanı axtarın, xəritəyə basın və ya «Mənim yerim» seçin.'}
      </p>
    </div>
  )
}
