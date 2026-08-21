import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { ChevronLeft, Loader2, Send } from 'lucide-react'
import { usePreviewAudience, useSendCampaign } from '@/api/campaigns'
import { PageHeader } from '@/components/PageHeader'
import { ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
import { Textarea } from '@/components/ui/textarea'
import { useToast } from '@/components/ui/toast'
import type { AudienceScope, AudienceSpec, CampaignLanguage } from '@/types/api'

const LANGS: { v: CampaignLanguage; l: string }[] = [
  { v: 'az', l: 'Azərbaycan' },
  { v: 'ru', l: 'Rus' },
  { v: 'en', l: 'İngilis' },
]
const SCOPES: { v: AudienceScope; l: string }[] = [
  { v: 'all_users', l: 'Bütün istifadəçilər' },
  { v: 'user_ids', l: 'Seçilmiş istifadəçi ID-ləri' },
  { v: 'complex', l: 'Kompleks' },
  { v: 'filter', l: 'Filtr' },
]
const ROLES = [
  { v: '', l: 'Hamısı' },
  { v: 'owner', l: 'Sahib' },
  { v: 'user', l: 'İstifadəçi' },
]
const SUB_STATUSES = [
  { v: '', l: 'Hamısı' },
  { v: 'active', l: 'Aktiv' },
  { v: 'pending_payment', l: 'Ödəniş gözləyir' },
  { v: 'expired', l: 'Bitib' },
  { v: 'cancelled', l: 'Ləğv edilib' },
  { v: 'refunded', l: 'Geri qaytarılıb' },
]

export function CreateCampaignPage() {
  const navigate = useNavigate()
  const { toast } = useToast()
  const preview = usePreviewAudience()
  const send = useSendCampaign()

  // One Idempotency-Key per compose session (LOCKED): a retry after a validation error reuses it so the
  // server never creates a duplicate campaign; leaving + re-opening the page starts a fresh key.
  const [idempotencyKey] = useState(() => crypto.randomUUID())

  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [language, setLanguage] = useState<CampaignLanguage>('az')
  const [scope, setScope] = useState<AudienceScope>('all_users')
  const [userIdsText, setUserIdsText] = useState('')
  const [complexId, setComplexId] = useState('')
  const [fq, setFq] = useState('')
  const [frole, setFrole] = useState('')
  const [fsub, setFsub] = useState('')
  const [fcomplex, setFcomplex] = useState('')
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [confirmChecked, setConfirmChecked] = useState(false)

  const parseIds = (t: string): number[] =>
    t
      .split(/[\s,]+/)
      .map((s) => Number(s))
      .filter((n) => Number.isInteger(n) && n > 0)

  const buildAudience = (): AudienceSpec => {
    if (scope === 'user_ids') return { scope, user_ids: parseIds(userIdsText) }
    if (scope === 'complex') return { scope, complex_id: complexId ? Number(complexId) : undefined }
    if (scope === 'filter') {
      return {
        scope,
        filter: {
          q: fq || undefined,
          role: frole || undefined,
          subscription_status: fsub || undefined,
          complex_id: fcomplex ? Number(fcomplex) : undefined,
        },
      }
    }
    return { scope: 'all_users' }
  }

  const sendErr = send.error instanceof ApiError ? send.error : undefined
  const canCompose = title.trim().length > 0 && body.trim().length > 0
  const recipientCount = preview.data?.recipient_count

  const onPreview = () => {
    preview.mutate(buildAudience(), {
      onError: (e) => toast({ variant: 'destructive', title: 'Xəta', description: e instanceof ApiError ? e.message : undefined }),
    })
  }

  const onOpenConfirm = () => {
    if (!canCompose) return
    // Resolve a fresh, server-side recipient count for the confirmation screen (ADMIN_SPEC).
    preview.mutate(buildAudience(), {
      onSuccess: () => {
        setConfirmChecked(false)
        setConfirmOpen(true)
      },
      onError: (e) =>
        toast({ variant: 'destructive', title: 'Auditoriya yoxlanıla bilmədi', description: e instanceof ApiError ? e.message : undefined }),
    })
  }

  const onSend = () => {
    send.mutate(
      {
        input: { type: 'system', title: title.trim(), body: body.trim(), language, audience: buildAudience(), confirmed: true },
        idempotencyKey,
      },
      {
        onSuccess: (c) => {
          toast({ variant: 'success', title: 'Bildiriş göndərildi' })
          setConfirmOpen(false)
          navigate(`/notifications/${c.id}`)
        },
        onError: (e) =>
          toast({ variant: 'destructive', title: 'Göndərilə bilmədi', description: e instanceof ApiError ? e.message : undefined }),
      },
    )
  }

  return (
    <div className="space-y-6">
      <Link to="/notifications" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ChevronLeft className="h-4 w-4" />
        Bildirişlər
      </Link>

      <PageHeader title="Yeni bildiriş" description="Sakinlərə push + tətbiqdaxili sistem bildirişi göndər" />

      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Məzmun</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="c-title">Başlıq</Label>
            <Input id="c-title" value={title} maxLength={160} onChange={(e) => setTitle(e.target.value)} />
            {sendErr?.fieldError('title') && <p className="text-xs text-destructive">{sendErr.fieldError('title')}</p>}
          </div>
          <div className="space-y-2">
            <Label htmlFor="c-body">Mətn</Label>
            <Textarea id="c-body" value={body} rows={4} onChange={(e) => setBody(e.target.value)} />
            {sendErr?.fieldError('body') && <p className="text-xs text-destructive">{sendErr.fieldError('body')}</p>}
          </div>
          <div className="space-y-2 sm:w-56">
            <Label htmlFor="c-lang">Dil</Label>
            <Select id="c-lang" value={language} onChange={(e) => setLanguage(e.target.value as CampaignLanguage)}>
              {LANGS.map((l) => (
                <option key={l.v} value={l.v}>
                  {l.l}
                </option>
              ))}
            </Select>
          </div>
          <p className="text-xs text-muted-foreground">Mətn seçilmiş dildə olduğu kimi göndərilir — avtomatik tərcümə yoxdur.</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Auditoriya</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2 sm:w-72">
            <Label htmlFor="c-scope">Kimə göndərilsin</Label>
            <Select
              id="c-scope"
              value={scope}
              onChange={(e) => {
                setScope(e.target.value as AudienceScope)
                preview.reset()
              }}
            >
              {SCOPES.map((s) => (
                <option key={s.v} value={s.v}>
                  {s.l}
                </option>
              ))}
            </Select>
          </div>

          {scope === 'user_ids' && (
            <div className="space-y-2">
              <Label htmlFor="c-uids">İstifadəçi ID-ləri (vergül və ya boşluqla)</Label>
              <Textarea id="c-uids" value={userIdsText} rows={2} placeholder="məs. 12, 34, 56" onChange={(e) => setUserIdsText(e.target.value)} />
            </div>
          )}
          {scope === 'complex' && (
            <div className="space-y-2 sm:w-56">
              <Label htmlFor="c-cx">Kompleks ID</Label>
              <Input id="c-cx" type="number" value={complexId} onChange={(e) => setComplexId(e.target.value)} />
            </div>
          )}
          {scope === 'filter' && (
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="f-q">Axtarış (ad / telefon / email)</Label>
                <Input id="f-q" value={fq} onChange={(e) => setFq(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="f-cx">Kompleks ID</Label>
                <Input id="f-cx" type="number" value={fcomplex} onChange={(e) => setFcomplex(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="f-role">Rol</Label>
                <Select id="f-role" value={frole} onChange={(e) => setFrole(e.target.value)}>
                  {ROLES.map((r) => (
                    <option key={r.v} value={r.v}>
                      {r.l}
                    </option>
                  ))}
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="f-sub">Abunə statusu</Label>
                <Select id="f-sub" value={fsub} onChange={(e) => setFsub(e.target.value)}>
                  {SUB_STATUSES.map((s) => (
                    <option key={s.v} value={s.v}>
                      {s.l}
                    </option>
                  ))}
                </Select>
              </div>
            </div>
          )}

          <div className="flex flex-wrap items-center gap-3">
            <Button variant="outline" onClick={onPreview} disabled={preview.isPending}>
              {preview.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
              Auditoriyanı yoxla
            </Button>
            {recipientCount != null && (
              <span className="text-sm text-muted-foreground">
                Alıcı sayı: <strong className="text-foreground">{recipientCount}</strong>
              </span>
            )}
          </div>
        </CardContent>
      </Card>

      <div className="flex justify-end">
        <Button onClick={onOpenConfirm} disabled={!canCompose || preview.isPending}>
          {preview.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
          Göndər
        </Button>
      </div>

      <Dialog
        open={confirmOpen}
        onOpenChange={(o) => {
          if (!send.isPending) setConfirmOpen(o)
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Bildirişi təsdiqlə</DialogTitle>
            <DialogDescription>Bu bildiriş dərhal göndəriləcək və geri qaytarıla bilməz.</DialogDescription>
          </DialogHeader>
          <div className="space-y-3 py-2 text-sm">
            <div className="grid grid-cols-3 gap-2 rounded-md bg-muted/50 p-3">
              <div>
                <div className="text-xs text-muted-foreground">Alıcı sayı</div>
                <div className="font-medium">{recipientCount ?? '—'}</div>
              </div>
              <div>
                <div className="text-xs text-muted-foreground">Dil</div>
                <div className="font-medium">{LANGS.find((l) => l.v === language)?.l}</div>
              </div>
              <div>
                <div className="text-xs text-muted-foreground">Auditoriya</div>
                <div className="font-medium">{SCOPES.find((s) => s.v === scope)?.l}</div>
              </div>
            </div>
            <div>
              <div className="text-xs text-muted-foreground">Başlıq</div>
              <div className="font-medium">{title}</div>
            </div>
            <div>
              <div className="text-xs text-muted-foreground">Mətn</div>
              <p className="whitespace-pre-wrap">{body}</p>
            </div>
            <label className="flex items-center gap-2">
              <input type="checkbox" checked={confirmChecked} onChange={(e) => setConfirmChecked(e.target.checked)} />
              Göndərişi təsdiq edirəm
            </label>
            {sendErr && <p className="rounded-md bg-destructive/10 p-2 text-destructive">{sendErr.message}</p>}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setConfirmOpen(false)} disabled={send.isPending}>
              İmtina
            </Button>
            <Button onClick={onSend} disabled={!confirmChecked || send.isPending}>
              {send.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
              Göndər
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
