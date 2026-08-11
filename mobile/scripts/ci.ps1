# Salam Mobile — CI pipeline (Windows / PowerShell).
# Mirrors scripts/ci.sh. Run from anywhere; resolves the project root itself.
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")

function Step($name, [scriptblock]$body) {
  Write-Host "==> $name" -ForegroundColor Cyan
  & $body
  if ($LASTEXITCODE -ne 0) { throw "$name failed (exit $LASTEXITCODE)" }
}

Step "flutter pub get"        { flutter pub get }
Step "gen-l10n"              { flutter gen-l10n }
Step "format (check only)"   { dart format --output=none --set-exit-if-changed lib test }
Step "analyze"               { flutter analyze }
Step "test"                  { flutter test }
Step "build apk (debug)"     { flutter build apk --debug }
Step "build web"             { flutter build web }

Write-Host "All CI steps passed." -ForegroundColor Green
