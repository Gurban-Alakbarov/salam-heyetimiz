import { type FormEvent, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { AlertCircle, Loader2, ShieldCheck } from 'lucide-react'
import { useAuth } from '@/auth/useAuth'
import { ApiError } from '@/lib/api'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [loading, setLoading] = useState(false)

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setError(null)
    setLoading(true)
    try {
      const result = await login(email, password)
      if (result.twoFactorRequired) {
        navigate('/login/2fa', { state: { challengeToken: result.challengeToken, email } })
      } else {
        navigate('/') // Require-2FA off → logged in with email + password only
      }
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <Card className="w-full max-w-sm">
        <CardHeader className="items-center text-center">
          <div className="mb-2 flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <ShieldCheck className="h-6 w-6" />
          </div>
          <CardTitle>Admin Panel</CardTitle>
          <CardDescription>Salam Həyətimiz — idarəetmə</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4" noValidate>
            {error && error.status !== 422 && (
              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{error.message}</AlertDescription>
              </Alert>
            )}
            <div className="space-y-2">
              <Label htmlFor="email">E-poçt</Label>
              <Input
                id="email"
                type="email"
                autoComplete="username"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
              {error?.fieldError('email') && (
                <p className="text-xs text-destructive">{error.fieldError('email')}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">Parol</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
              {error?.fieldError('password') && (
                <p className="text-xs text-destructive">{error.fieldError('password')}</p>
              )}
            </div>
            <Button type="submit" className="w-full" disabled={loading || !email || !password}>
              {loading && <Loader2 className="h-4 w-4 animate-spin" />}
              Davam et
            </Button>
          </form>
          <p className="mt-4 text-center text-xs text-muted-foreground">
            Təhlükəsizlik parametrlərindən asılı olaraq iki-faktorlu doğrulama (TOTP) tələb oluna bilər.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
