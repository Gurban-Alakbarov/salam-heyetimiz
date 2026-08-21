import { type FormEvent, useState } from 'react'
import { Check, Copy, ExternalLink, Loader2, Link as LinkIcon } from 'lucide-react'
import { useCreateVisitorLink } from '@/api/visitorLinks'
import { ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { useToast } from '@/components/ui/toast'
import type { CreateVisitorLinkResponse, VisitorAccessType, VisitorPurpose } from '@/types/api'
import { ACCESS_TYPE_OPTIONS, DURATION_OPTIONS, PURPOSE_OPTIONS } from './labels'

export function CreateVisitorLinkDialog({
  deviceId,
  deviceLabel,
  open,
  onOpenChange,
}: {
  deviceId: number
  deviceLabel?: string
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const { toast } = useToast()
  const create = useCreateVisitorLink(deviceId)

  const [visitorName, setVisitorName] = useState('')
  const [purpose, setPurpose] = useState<'' | VisitorPurpose>('')
  const [accessType, setAccessType] = useState<VisitorAccessType>('one_time')
  const [duration, setDuration] = useState(30)
  const [created, setCreated] = useState<CreateVisitorLinkResponse | null>(null)

  const reset = () => {
    setVisitorName('')
    setPurpose('')
    setAccessType('one_time')
    setDuration(30)
    setCreated(null)
    create.reset()
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    create.mutate(
      {
        access_type: accessType,
        duration_minutes: accessType === 'time_limited' ? duration : undefined,
        visitor_name: visitorName.trim() || undefined,
        purpose: purpose || undefined,
      },
      {
        onSuccess: (res) => setCreated(res),
        onError: (err) =>
          toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
      },
    )
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (create.isPending) return
        onOpenChange(next)
        if (!next) reset()
      }}
    >
      <DialogContent>
        {created ? (
          <CreatedView response={created} onDone={() => onOpenChange(false)} />
        ) : (
          <form onSubmit={submit}>
            <DialogHeader>
              <DialogTitle>Qonaq linki yarat</DialogTitle>
              <DialogDescription>
                {deviceLabel ? `${deviceLabel} üçün paylaşıla bilən giriş linki.` : 'Paylaşıla bilən giriş linki.'}
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <Label htmlFor="vl-name">Qonağın adı (istəyə bağlı)</Label>
                <Input id="vl-name" value={visitorName} onChange={(e) => setVisitorName(e.target.value)} maxLength={120} placeholder="məs. Kuryer" />
              </div>

              <div className="space-y-2">
                <Label htmlFor="vl-purpose">Məqsəd (istəyə bağlı)</Label>
                <Select id="vl-purpose" value={purpose} onChange={(e) => setPurpose(e.target.value as '' | VisitorPurpose)}>
                  <option value="">Seçilməyib</option>
                  {PURPOSE_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="vl-access">Giriş növü</Label>
                <Select id="vl-access" value={accessType} onChange={(e) => setAccessType(e.target.value as VisitorAccessType)}>
                  {ACCESS_TYPE_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </div>

              {accessType === 'time_limited' && (
                <div className="space-y-2">
                  <Label htmlFor="vl-duration">Müddət</Label>
                  <Select id="vl-duration" value={duration} onChange={(e) => setDuration(Number(e.target.value))}>
                    {DURATION_OPTIONS.map((o) => (
                      <option key={o.value} value={o.value}>
                        {o.label}
                      </option>
                    ))}
                  </Select>
                </div>
              )}
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={create.isPending}>
                İmtina
              </Button>
              <Button type="submit" disabled={create.isPending}>
                {create.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <LinkIcon className="h-4 w-4" />}
                Link yarat
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  )
}

function CreatedView({ response, onDone }: { response: CreateVisitorLinkResponse; onDone: () => void }) {
  const { toast } = useToast()
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(response.url)
      setCopied(true)
      toast({ variant: 'success', title: 'Link kopyalandı' })
      setTimeout(() => setCopied(false), 2000)
    } catch {
      toast({ variant: 'destructive', title: 'Kopyalanmadı' })
    }
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>Link hazırdır</DialogTitle>
        <DialogDescription>
          Bu link yalnız indi göstərilir — sonra bərpa oluna bilməz. Paylaşmaq üçün kopyalayın.
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-3 py-4">
        <div className="break-all rounded-md border bg-muted/40 p-3 font-mono text-sm">{response.url}</div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" onClick={copy}>
            {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
            Linki kopyala
          </Button>
          <Button type="button" variant="outline" asChild>
            <a href={response.url} target="_blank" rel="noopener noreferrer">
              <ExternalLink className="h-4 w-4" />
              Linki aç
            </a>
          </Button>
        </div>
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onDone}>
          Bağla
        </Button>
      </DialogFooter>
    </>
  )
}
