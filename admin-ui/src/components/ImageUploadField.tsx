import { type DragEvent, useRef, useState } from 'react'
import { ImageOff, Loader2, Trash2, Upload } from 'lucide-react'
import { useDeleteDeviceImage, useUploadDeviceImage } from '@/api/devices'
import { ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { useToast } from '@/components/ui/toast'

const ACCEPT_MIME = ['image/jpeg', 'image/png', 'image/webp']
const ACCEPT_ATTR = '.jpg,.jpeg,.png,.webp'
const MAX_BYTES = 5 * 1024 * 1024 // 5 MB

interface Props {
  deviceId: number
  currentUrl: string | null
}

/**
 * Barrier-photo uploader: drag & drop or "Choose file", instant preview, then a multipart upload to
 * the device image endpoint. Accepts JPG/JPEG/PNG/WEBP up to 5 MB. Replaces the "image URL" text field.
 */
export function ImageUploadField({ deviceId, currentUrl }: Props) {
  const upload = useUploadDeviceImage(deviceId)
  const remove = useDeleteDeviceImage(deviceId)
  const { toast } = useToast()
  const inputRef = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<string | null>(currentUrl)
  const [dragOver, setDragOver] = useState(false)
  const busy = upload.isPending || remove.isPending

  const pick = (file: File | undefined | null) => {
    if (!file) return
    if (!ACCEPT_MIME.includes(file.type)) {
      toast({ variant: 'destructive', title: 'Yanlış format', description: 'Yalnız JPG, JPEG, PNG və ya WEBP.' })
      return
    }
    if (file.size > MAX_BYTES) {
      toast({ variant: 'destructive', title: 'Şəkil çox böyükdür', description: 'Maksimum 5 MB.' })
      return
    }
    void send(file)
  }

  const send = async (file: File) => {
    const localUrl = URL.createObjectURL(file)
    setPreview(localUrl) // instant preview
    try {
      const dev = await upload.mutateAsync(file)
      setPreview(dev.image_url)
      toast({ variant: 'success', title: 'Şəkil yükləndi' })
    } catch (err) {
      setPreview(currentUrl)
      const msg = err instanceof ApiError ? err.message : 'Yükləmə uğursuz oldu.'
      toast({ variant: 'destructive', title: 'Xəta', description: msg })
    } finally {
      URL.revokeObjectURL(localUrl)
    }
  }

  const clear = async () => {
    try {
      await remove.mutateAsync()
      setPreview(null)
      toast({ variant: 'success', title: 'Şəkil silindi' })
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Silinmə uğursuz oldu.'
      toast({ variant: 'destructive', title: 'Xəta', description: msg })
    }
  }

  const onDrop = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    setDragOver(false)
    if (busy) return
    pick(e.dataTransfer.files?.[0])
  }

  return (
    <div className="space-y-2">
      <div
        role="button"
        tabIndex={0}
        onClick={() => !busy && inputRef.current?.click()}
        onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && !busy && inputRef.current?.click()}
        onDragOver={(e) => {
          e.preventDefault()
          if (!busy) setDragOver(true)
        }}
        onDragLeave={() => setDragOver(false)}
        onDrop={onDrop}
        className={`relative flex min-h-40 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-4 text-center transition ${
          dragOver ? 'border-primary bg-primary/5' : 'border-input hover:border-primary/60'
        }`}
      >
        {preview ? (
          <img src={preview} alt="Şlaqbaum" className="max-h-40 w-auto rounded-md object-contain" />
        ) : (
          <>
            <Upload className="h-7 w-7 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">
              Şəkli bura sürüşdürün və ya <span className="text-primary underline">seçin</span>
            </p>
            <p className="text-xs text-muted-foreground">JPG, PNG, WEBP — maksimum 5 MB</p>
          </>
        )}
        {busy && (
          <div className="absolute inset-0 flex items-center justify-center rounded-lg bg-background/70">
            <Loader2 className="h-6 w-6 animate-spin text-primary" />
          </div>
        )}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept={ACCEPT_ATTR}
        className="hidden"
        onChange={(e) => {
          pick(e.target.files?.[0])
          e.target.value = '' // allow re-selecting the same file
        }}
      />

      <div className="flex gap-2">
        <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => inputRef.current?.click()}>
          <Upload className="h-4 w-4" />
          Şəkil yüklə
        </Button>
        {preview && (
          <Button type="button" variant="outline" size="sm" disabled={busy} onClick={clear}>
            {remove.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
            Sil
          </Button>
        )}
        {!preview && (
          <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <ImageOff className="h-3.5 w-3.5" /> Şəkil yoxdur
          </span>
        )}
      </div>
    </div>
  )
}
