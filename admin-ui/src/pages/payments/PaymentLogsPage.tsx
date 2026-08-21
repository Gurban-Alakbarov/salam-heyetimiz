import { Fragment, useState } from 'react'
import { type PaymentLogEntry, usePaymentLogs } from '@/api/paymentLogs'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDateTime } from '@/lib/format'

export function PaymentLogsPage() {
  const [direction, setDirection] = useState('')
  const [signature, setSignature] = useState('')
  const [endpoint, setEndpoint] = useState('')
  const [errors, setErrors] = useState('')
  const [cursor, setCursor] = useState<string | null>(null)
  const [open, setOpen] = useState<number | null>(null)

  const { data, isLoading, isError, error, refetch } = usePaymentLogs({
    direction: direction || undefined,
    signature: signature || undefined,
    endpoint: endpoint || undefined,
    errors: errors || undefined,
    cursor,
  })

  const reset = () => setCursor(null)

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Ödəniş logları</h1>
        <p className="text-sm text-muted-foreground">BirPay çağırışları (outbound) + webhook-lar (inbound) — maskalı, şifrəli saxlanılır.</p>
      </div>

      <Card className="overflow-hidden">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Loglar</CardTitle>
          <div className="flex flex-wrap gap-2 pt-2">
            <Select value={direction} onChange={(e) => { setDirection(e.target.value); reset() }} className="w-40">
              <option value="">Bütün istiqamət</option>
              <option value="outbound">Outbound (BirPay)</option>
              <option value="inbound">Inbound (webhook)</option>
            </Select>
            <Select value={signature} onChange={(e) => { setSignature(e.target.value); reset() }} className="w-44">
              <option value="">İmza: hamısı</option>
              <option value="valid">İmza: keçərli</option>
              <option value="invalid">İmza: keçərsiz</option>
            </Select>
            <Select value={errors} onChange={(e) => { setErrors(e.target.value); reset() }} className="w-40">
              <option value="">Bütün nəticələr</option>
              <option value="1">Yalnız xətalar (≥400)</option>
            </Select>
            <Input placeholder="Endpoint axtar (/v1/payments)" value={endpoint} onChange={(e) => { setEndpoint(e.target.value); reset() }} className="w-64" />
          </div>
        </CardHeader>
        <CardContent className="px-0 pb-0">
          {isLoading ? (
            <div className="p-6"><LoadingState /></div>
          ) : isError ? (
            <div className="p-6"><ErrorState error={error} onRetry={() => refetch()} /></div>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Vaxt</TableHead>
                    <TableHead>İstiqamət</TableHead>
                    <TableHead>Endpoint</TableHead>
                    <TableHead>Metod</TableHead>
                    <TableHead>HTTP</TableHead>
                    <TableHead>İmza</TableHead>
                    <TableHead>Gecikmə</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(data?.data ?? []).map((row) => (
                    <Fragment key={row.id}>
                      <TableRow className="cursor-pointer" onClick={() => setOpen(open === row.id ? null : row.id)}>
                        <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(row.created_at)}</TableCell>
                        <TableCell><Badge variant={row.direction === 'inbound' ? 'muted' : 'default'}>{row.direction}</Badge></TableCell>
                        <TableCell className="font-mono text-xs">{row.endpoint}</TableCell>
                        <TableCell className="text-muted-foreground">{row.http_method ?? '—'}</TableCell>
                        <TableCell>{statusCell(row)}</TableCell>
                        <TableCell>{sigCell(row)}</TableCell>
                        <TableCell className="text-muted-foreground">{row.latency_ms != null ? `${row.latency_ms}ms` : '—'}</TableCell>
                      </TableRow>
                      {open === row.id && (
                        <TableRow>
                          <TableCell colSpan={7} className="bg-muted/40">
                            <div className="grid gap-3 md:grid-cols-2">
                              <Json title="Request" value={row.request} />
                              <Json title="Response / Payload" value={row.response} />
                            </div>
                            {row.order_id && <p className="mt-2 text-xs text-muted-foreground">Sifariş #{row.order_id} · IP {row.ip ?? '—'}</p>}
                          </TableCell>
                        </TableRow>
                      )}
                    </Fragment>
                  ))}
                </TableBody>
              </Table>
              {data?.page.has_more && (
                <div className="flex justify-center p-3">
                  <Button variant="outline" onClick={() => setCursor(data.page.next_cursor)}>Daha çox</Button>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function statusCell(row: PaymentLogEntry) {
  if (row.http_status == null) return <span className="text-muted-foreground">—</span>
  const ok = row.http_status < 400
  return <Badge variant={ok ? 'success' : 'destructive'}>{row.http_status}</Badge>
}

function sigCell(row: PaymentLogEntry) {
  if (row.signature_valid == null) return <span className="text-muted-foreground">—</span>
  return <Badge variant={row.signature_valid ? 'success' : 'destructive'}>{row.signature_valid ? 'keçərli' : 'keçərsiz'}</Badge>
}

function Json({ title, value }: { title: string; value: unknown }) {
  return (
    <div>
      <p className="mb-1 text-xs font-medium text-muted-foreground">{title}</p>
      <pre className="max-h-60 overflow-auto rounded bg-card p-2 font-mono text-xs">{value ? JSON.stringify(value, null, 2) : '—'}</pre>
    </div>
  )
}
