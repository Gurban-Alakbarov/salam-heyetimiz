#!/usr/bin/env bash
# Salam Mobile — CI pipeline (Constitution §15 / RELEASE_PLAN.md).
# Mirrors the GitHub Actions / local pre-push gate.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> flutter pub get"
flutter pub get

echo "==> gen-l10n"
flutter gen-l10n

echo "==> format (check only)"
dart format --output=none --set-exit-if-changed lib test

echo "==> analyze"
flutter analyze

echo "==> test"
flutter test

echo "==> build apk (debug)"
flutter build apk --debug

echo "==> build web"
flutter build web

echo "All CI steps passed."
