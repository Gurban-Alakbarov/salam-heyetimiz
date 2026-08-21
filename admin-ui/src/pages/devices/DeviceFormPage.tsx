import { type FormEvent, type ReactNode, useEffect, useState } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import { ChevronLeft, Loader2 } from 'lucide-react'
import { useCreateDevice, useDevice, useUpdateDevice } from '@/api/devices'
import { useAuth } from '@/auth/useAuth'
import { ApiError } from '@/lib/api'
import { cn } from '@/lib/utils'
import { ImageUploadField } from '@/components/ImageUploadField'
import { LoadingState } from '@/components/states'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { useToast } from '@/components/ui/toast'
import type { CreateDeviceInput, DriverType, UpdateDeviceInput } from '@/types/api'

interface FormState {
  serial: string
  device_model_id: string
  sim_phone: string
  driver_type: DriverType
  sim_operator_id: string
  sim_iccid: string
  firmware_version: string
  region_id: string
  location_label: string
  address: string
  latitude: string
  longitude: string
  geofence_enabled: boolean
  geofence_radius_m: string
}

const EMPTY: FormState = {
  serial: '',
  device_model_id: '',
  sim_phone: '',
  driver_type: 'traccar',
  sim_operator_id: '',
  sim_iccid: '',
  firmware_version: '',
  region_id: '',
  location_label: '',
  address: '',
  latitude: '',
  longitude: '',
  geofence_enabled: false,
  geofence_radius_m: '',
}

function Field({
  id,
  label,
  hint,
  error,
  children,
}: {
  id: string
  label: string
  hint?: string
  error?: string
  children: ReactNode
}) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      {children}
      {hint && !error && <p className="text-xs text-muted-foreground">{hint}</p>}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  )
}

export function DeviceFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const deviceId = Number(id)
  const navigate = useNavigate()
  const { toast } = useToast()
  const { hasRole } = useAuth()

  const existing = useDevice(isEdit ? deviceId : Number.NaN)
  const create = useCreateDevice()
  const update = useUpdateDevice(deviceId)
  const pending = create.isPending || update.isPending

  const [form, setForm] = useState<FormState>(EMPTY)
  const [apiError, setApiError] = useState<ApiError | null>(null)

  useEffect(() => {
    if (isEdit && existing.data) {
      const d = existing.data
      setForm({
        serial: d.serial,
        device_model_id: d.device_model ? String(d.device_model.id) : '',
        sim_phone: d.sim_phone ?? '',
        driver_type: d.driver_type,
        sim_operator_id: d.sim_operator ? String(d.sim_operator.id) : '',
        sim_iccid: '',
        firmware_version: '',
        region_id: d.region ? String(d.region.id) : '',
        location_label: d.location_label ?? '',
        address: d.address ?? '',
        latitude: d.latitude !== null ? String(d.latitude) : '',
        longitude: d.longitude !== null ? String(d.longitude) : '',
        geofence_enabled: d.geofence_enabled,
        geofence_radius_m: d.geofence_radius_m !== null ? String(d.geofence_radius_m) : '',
      })
    }
  }, [isEdit, existing.data])

  if (!hasRole('technical', 'super_admin')) {
    return <Navigate to="/devices" replace />
  }

  const set = (key: keyof FormState) => (value: string) => setForm((f) => ({ ...f, [key]: value }))

  // GEOFENCE-2 — client-side pre-checks; the backend stays the final authority.
  const radiusNum = Number(form.geofence_radius_m)
  const radiusInvalid =
    form.geofence_enabled &&
    (form.geofence_radius_m.trim() === '' || !Number.isInteger(radiusNum) || radiusNum < 1 || radiusNum > 65535)
  const coordsMissing = form.geofence_enabled && (form.latitude.trim() === '' || form.longitude.trim() === '')
  // Geofence config failures come back as a domain 422 (error.code, no `fields` map) — surface the
  // message at form level so it is never silently swallowed.
  const formLevelError =
    apiError && apiError.status === 422 && !apiError.fields ? apiError.message : null

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setApiError(null)
    try {
      if (isEdit) {
        if (radiusInvalid) return // inline error already shown; keep the request off the wire
        const payload: UpdateDeviceInput = {
          driver_type: form.driver_type,
          sim_operator_id: form.sim_operator_id ? Number(form.sim_operator_id) : null,
          sim_iccid: form.sim_iccid.trim() || null,
          firmware_version: form.firmware_version.trim() || null,
          region_id: form.region_id ? Number(form.region_id) : null,
          location_label: form.location_label.trim() || null,
          address: form.address.trim() || null,
          latitude: form.latitude ? Number(form.latitude) : null,
          longitude: form.longitude ? Number(form.longitude) : null,
          geofence_enabled: form.geofence_enabled,
          geofence_radius_m: form.geofence_enabled ? Number(form.geofence_radius_m) : null,
        }
        await update.mutateAsync(payload)
        toast({ variant: 'success', title: 'Cihaz yeniləndi' })
        navigate(`/devices/${deviceId}`)
      } else {
        const payload: CreateDeviceInput = {
          serial: form.serial.trim(),
          device_model_id: Number(form.device_model_id),
          sim_phone: form.sim_phone.trim(),
          driver_type: form.driver_type,
          ...(form.sim_operator_id ? { sim_operator_id: Number(form.sim_operator_id) } : {}),
          ...(form.sim_iccid ? { sim_iccid: form.sim_iccid.trim() } : {}),
          ...(form.firmware_version ? { firmware_version: form.firmware_version.trim() } : {}),
          ...(form.region_id ? { region_id: Number(form.region_id) } : {}),
          ...(form.location_label ? { location_label: form.location_label.trim() } : {}),
          ...(form.address ? { address: form.address.trim() } : {}),
          ...(form.latitude ? { latitude: Number(form.latitude) } : {}),
          ...(form.longitude ? { longitude: Number(form.longitude) } : {}),
        }
        const created = await create.mutateAsync(payload)
        toast({ variant: 'success', title: 'Cihaz yaradıldı' })
        navigate(`/devices/${created.id}`)
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setApiError(err)
        if (err.status !== 422) toast({ variant: 'destructive', title: 'Xəta', description: err.message })
      }
    }
  }

  if (isEdit && existing.isLoading) return <LoadingState />

  const fieldError = (name: string) => apiError?.fieldError(name)

  return (
    <div className="space-y-5">
      <Link
        to={isEdit ? `/devices/${deviceId}` : '/devices'}
        className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ChevronLeft className="h-4 w-4" />
        Geri
      </Link>

      <Card className="max-w-2xl">
        <CardHeader>
          <CardTitle>{isEdit ? 'Cihazı redaktə et' : 'Yeni cihaz'}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4" noValidate>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field id="serial" label="Serial" error={fieldError('serial')}>
                <Input
                  id="serial"
                  value={form.serial}
                  onChange={(e) => set('serial')(e.target.value)}
                  disabled={isEdit}
                  required={!isEdit}
                />
              </Field>
              <Field
                id="device_model_id"
                label="Model ID"
                hint="Cihaz modelinin ID-si"
                error={fieldError('device_model_id')}
              >
                <Input
                  id="device_model_id"
                  type="number"
                  value={form.device_model_id}
                  onChange={(e) => set('device_model_id')(e.target.value)}
                  disabled={isEdit}
                  required={!isEdit}
                />
              </Field>
              <Field id="sim_phone" label="SIM nömrəsi" error={fieldError('sim_phone')}>
                <Input
                  id="sim_phone"
                  value={form.sim_phone}
                  onChange={(e) => set('sim_phone')(e.target.value)}
                  placeholder="+994XXXXXXXXX"
                  disabled={isEdit}
                  required={!isEdit}
                />
              </Field>
              <Field id="driver_type" label="Sürücü" error={fieldError('driver_type')}>
                <Select
                  id="driver_type"
                  value={form.driver_type}
                  onChange={(e) => set('driver_type')(e.target.value)}
                >
                  <option value="traccar">Traccar</option>
                  <option value="sms">SMS</option>
                  <option value="ble">BLE</option>
                </Select>
              </Field>
              <Field id="sim_operator_id" label="Operator ID" hint="Könüllü" error={fieldError('sim_operator_id')}>
                <Input
                  id="sim_operator_id"
                  type="number"
                  value={form.sim_operator_id}
                  onChange={(e) => set('sim_operator_id')(e.target.value)}
                />
              </Field>
              <Field id="region_id" label="Region ID" hint="Könüllü" error={fieldError('region_id')}>
                <Input
                  id="region_id"
                  type="number"
                  value={form.region_id}
                  onChange={(e) => set('region_id')(e.target.value)}
                />
              </Field>
              <Field id="firmware_version" label="Firmware" hint="Könüllü" error={fieldError('firmware_version')}>
                <Input
                  id="firmware_version"
                  value={form.firmware_version}
                  onChange={(e) => set('firmware_version')(e.target.value)}
                />
              </Field>
              <Field id="sim_iccid" label="SIM ICCID" hint="Könüllü" error={fieldError('sim_iccid')}>
                <Input id="sim_iccid" value={form.sim_iccid} onChange={(e) => set('sim_iccid')(e.target.value)} />
              </Field>
              <Field id="location_label" label="Ünvan etiketi" hint="Könüllü" error={fieldError('location_label')}>
                <Input
                  id="location_label"
                  value={form.location_label}
                  onChange={(e) => set('location_label')(e.target.value)}
                />
              </Field>
              <div className="sm:col-span-2">
                <Field id="device_image" label="Şlaqbaum şəkli" hint="JPG, PNG, WEBP — maksimum 5 MB">
                  {isEdit ? (
                    <ImageUploadField deviceId={deviceId} currentUrl={existing.data?.image_url ?? null} />
                  ) : (
                    <p className="text-xs text-muted-foreground">
                      Şəkli cihaz yaradıldıqdan sonra redaktə səhifəsindən yükləyə bilərsiniz.
                    </p>
                  )}
                </Field>
              </div>
              <Field id="address" label="Ünvan" hint="Tam ünvan (könüllü)" error={fieldError('address')}>
                <Input
                  id="address"
                  value={form.address}
                  onChange={(e) => set('address')(e.target.value)}
                  placeholder="Küçə, ev, rayon"
                />
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field id="latitude" label="Enlik" error={fieldError('latitude')}>
                  <Input
                    id="latitude"
                    type="number"
                    step="any"
                    value={form.latitude}
                    onChange={(e) => set('latitude')(e.target.value)}
                  />
                </Field>
                <Field id="longitude" label="Uzunluq" error={fieldError('longitude')}>
                  <Input
                    id="longitude"
                    type="number"
                    step="any"
                    value={form.longitude}
                    onChange={(e) => set('longitude')(e.target.value)}
                  />
                </Field>
              </div>
            </div>

            {isEdit && (
              <div className="space-y-3 border-t pt-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="space-y-0.5">
                    <Label htmlFor="geofence_enabled" className="text-sm font-medium">
                      Geofence aktivdir
                    </Label>
                    <p className="text-xs text-muted-foreground">
                      Açıq olduqda istifadəçi yalnız cihaza yaxın olduqda qapını aça bilər.
                    </p>
                  </div>
                  <button
                    id="geofence_enabled"
                    type="button"
                    role="switch"
                    aria-checked={form.geofence_enabled}
                    onClick={() =>
                      setForm((f) => ({
                        ...f,
                        geofence_enabled: !f.geofence_enabled,
                        // enabling with an empty radius → seed the ~500 m default
                        geofence_radius_m:
                          !f.geofence_enabled && f.geofence_radius_m.trim() === '' ? '500' : f.geofence_radius_m,
                      }))
                    }
                    className={cn(
                      'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors',
                      form.geofence_enabled ? 'bg-primary' : 'bg-input',
                    )}
                  >
                    <span
                      className={cn(
                        'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform',
                        form.geofence_enabled ? 'translate-x-5' : 'translate-x-0.5',
                      )}
                    />
                  </button>
                </div>

                {form.geofence_enabled && (
                  <>
                    <Field
                      id="geofence_radius_m"
                      label="Açılma məsafəsi (metr)"
                      hint="1–65535 m arası. Tövsiyə olunan: ~500 m."
                      error={
                        radiusInvalid
                          ? 'Müsbət tam ədəd daxil edin (1–65535).'
                          : fieldError('geofence_radius_m')
                      }
                    >
                      <Input
                        id="geofence_radius_m"
                        type="number"
                        min={1}
                        max={65535}
                        step={1}
                        placeholder="500"
                        value={form.geofence_radius_m}
                        onChange={(e) => set('geofence_radius_m')(e.target.value)}
                      />
                    </Field>
                    {coordsMissing && (
                      <p className="text-xs text-amber-600">Əvvəlcə cihaz üçün koordinat təyin edin.</p>
                    )}
                  </>
                )}
              </div>
            )}

            {formLevelError && (
              <Alert variant="destructive">
                <AlertDescription>{formLevelError}</AlertDescription>
              </Alert>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => navigate(-1)} disabled={pending}>
                İmtina
              </Button>
              <Button type="submit" disabled={pending}>
                {pending && <Loader2 className="h-4 w-4 animate-spin" />}
                {isEdit ? 'Yadda saxla' : 'Yarat'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
