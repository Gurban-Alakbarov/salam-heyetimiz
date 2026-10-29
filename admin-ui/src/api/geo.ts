import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface GeoPlace { name: string; address: string; lat: number; lng: number; kind: string | null }
export interface GeoAddress {
  address: string | null
  street: string | null
  house: string | null
  district: string | null
  city: string | null
  postal: string | null
}
export interface GeoTiles { url: string | null; attribution: string; max_zoom: number }

/** GoMap tile URL (GUID is server-side config; null = not configured → OSM fallback). */
export function useGeoTiles() {
  return useQuery({
    queryKey: ['geo-tiles'],
    queryFn: async () => (await api.get<{ data: GeoTiles }>('/admin/v1/geo/tiles', { params: { lang: 'az' } })).data.data,
    staleTime: Infinity,
  })
}

/** Places / addresses matching the text (GoMap through our server). */
export async function geoSearch(q: string): Promise<GeoPlace[]> {
  return (await api.get<{ data: GeoPlace[] }>('/admin/v1/geo/search', { params: { q, lang: 'az' } })).data.data
}

/** The address at a pin (with house number when GoMap knows it). */
export async function geoReverse(lat: number, lng: number): Promise<GeoAddress> {
  return (await api.get<{ data: GeoAddress }>('/admin/v1/geo/reverse', { params: { lat, lng, lang: 'az' } })).data.data
}
