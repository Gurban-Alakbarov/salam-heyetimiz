#!/bin/bash
# Build Salam Sesame (az.salam.sezam) for the App Store and upload it to App Store Connect.
#
#   mobile/ios/scripts/release_ipa.sh [build-number]
#
# Needs (never committed): ~/salam-keys/AuthKey_N5BQL355YG.p8 (App Store Connect API key, App Manager)
# and mobile/ios/Runner/GoogleService-Info.plist (Firebase app salam-sesame, bundle az.salam.sezam).
# Signing is manual App Store signing (no registered device needed): run ios/scripts/setup_signing.py once
# first; it creates the "Apple Distribution" certificate + "Salam Sesame App Store" profile via the API key.
set -euo pipefail

cd "$(dirname "$0")/../.."   # → mobile/

TEAM_ID=2654395QYQ
KEY_ID=N5BQL355YG
ISSUER_ID=daf412c4-7da1-4295-9e35-19533b48bc70
KEY_PATH="${ASC_KEY_PATH:-$HOME/salam-keys/AuthKey_${KEY_ID}.p8}"
BUILD_NUMBER="${1:-1}"

[ -f "$KEY_PATH" ] || { echo "Missing App Store Connect API key: $KEY_PATH" >&2; exit 1; }
[ -f ios/Runner/GoogleService-Info.plist ] || { echo "Missing ios/Runner/GoogleService-Info.plist" >&2; exit 1; }

AUTH=(-allowProvisioningUpdates
      -authenticationKeyPath "$KEY_PATH"
      -authenticationKeyID "$KEY_ID"
      -authenticationKeyIssuerID "$ISSUER_ID")

flutter clean
flutter pub get
# Flutter 3.44 resolves iOS plugins with Swift Package Manager; CocoaPods only when a Podfile exists.
if [ -f ios/Podfile ]; then (cd ios && pod install --repo-update); fi

# Writes ios/Flutter/Generated.xcconfig with the prod entrypoint + version, then Xcode archives it.
flutter build ios --release --no-codesign -t lib/main_prod.dart --build-number "$BUILD_NUMBER"

xcodebuild -workspace ios/Runner.xcworkspace -scheme Runner -configuration Release \
  -destination 'generic/platform=iOS' -archivePath build/ios/Runner.xcarchive \
  DEVELOPMENT_TEAM="$TEAM_ID" CODE_SIGN_STYLE=Manual CODE_SIGN_IDENTITY="Apple Distribution"   PROVISIONING_PROFILE_SPECIFIER="Salam Sesame App Store" "${AUTH[@]}" archive

xcodebuild -exportArchive -archivePath build/ios/Runner.xcarchive \
  -exportOptionsPlist ios/ExportOptions.plist -exportPath build/ios/export "${AUTH[@]}"

echo "Build $BUILD_NUMBER uploaded to App Store Connect (it shows in TestFlight after processing)."
