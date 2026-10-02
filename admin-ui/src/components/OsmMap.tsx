import { ExternalLink, MapPin } from 'lucide-react'

/**
 * OpenStreetMap location preview without a map library (B11): the official OSM embed iframe + an
 * "open on the map" link. Coordinates are the only source of truth; missing ones show an empty state.
 */
export function OsmMap({ latitude, longitude, height = 260 }: { latitude?: number | null; longitude?: number | null; height?: number }) {
  const valid = typeof latitude === 'number' && typeof longitude === 'number' && Number.isFinite(latitude) && Number.isFinite(longitude)
  if (!valid) {
    return (
      <div className="flex flex-col items-center justify-center gap-1 rounded-md border border-dashed py-8 text-center text-sm text-muted-foreground">
        <MapPin className="h-5 w-5" />
        Koordinat qeyd olunmayıb.
      </div>
    )
  }

  const d = 0.004 // ~400 m around the pin
  const bbox = [longitude - d, latitude - d, longitude + d, latitude + d].map((n) => n.toFixed(6)).join(',')
  const embed = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${latitude.toFixed(7)},${longitude.toFixed(7)}`
  const open = `https://www.openstreetmap.org/?mlat=${latitude.toFixed(7)}&mlon=${longitude.toFixed(7)}#map=17/${latitude.toFixed(7)}/${longitude.toFixed(7)}`

  return (
    <div className="space-y-2">
      <iframe
        title="OpenStreetMap"
        src={embed}
        className="w-full rounded-md border"
        style={{ height }}
        loading="lazy"
        referrerPolicy="no-referrer"
      />
      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span>{latitude.toFixed(6)}, {longitude.toFixed(6)} · © OpenStreetMap</span>
        <a href={open} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 font-medium text-primary hover:underline">
          Xəritədə aç <ExternalLink className="h-3 w-3" />
        </a>
      </div>
    </div>
  )
}
