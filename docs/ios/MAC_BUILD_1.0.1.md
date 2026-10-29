# iOS 1.0.1 (build 4) — Mac steps

For the Claude session on the Mac (Mac in Cloud, repo at `~/salam`). Work only inside `~/salam` and `~/salam-keys`.
The Mac is shared with other customers: never leave keys behind.

## 0. Update the repo

```bash
cd ~/salam && git pull --ff-only
grep '^version' mobile/pubspec.yaml      # must say 1.0.1+4
```

## 1. Keys (the user copied them from Google Drive into ~/Downloads)

```bash
mkdir -p ~/salam-keys && chmod 700 ~/salam-keys
mv ~/Downloads/AuthKey_N5BQL355YG.p8 ~/salam-keys/ && chmod 600 ~/salam-keys/AuthKey_N5BQL355YG.p8
mv ~/Downloads/GoogleService-Info.plist ~/salam/mobile/ios/Runner/GoogleService-Info.plist
```

The Runner target already references `GoogleService-Info.plist` (committed in the project file); only the file
itself is missing and it is git-ignored — never commit it.

## 2. Russian (and az/en) text for the location permission

`Info.plist` has one bilingual string. Add per-language strings so each phone shows its own language:

- `mobile/ios/Runner/az.lproj/InfoPlist.strings`
  `"NSLocationWhenInUseUsageDescription" = "Məkanınız şlaqbaumu açarkən yaxınlıqda olduğunuzu təsdiqləmək və müraciətdə ünvanınızı xəritədə seçmək üçün istifadə olunur.";`
- `mobile/ios/Runner/en.lproj/InfoPlist.strings`
  `"NSLocationWhenInUseUsageDescription" = "Your location is used to confirm you are near the gate when you open it and to pick your address on the map in an application.";`
- `mobile/ios/Runner/ru.lproj/InfoPlist.strings`
  `"NSLocationWhenInUseUsageDescription" = "Местоположение используется, чтобы подтвердить, что вы рядом со шлагбаумом при открытии, и чтобы выбрать адрес на карте в заявке.";`

Add them to the Runner target as one variant group `InfoPlist.strings` (xcodeproj gem: `new_variant_group`,
then add the three files, then add the group to the Runner resources build phase) and add `az` and `ru` to the
project's `knownRegions` (today: `en`, `Base`). Keep the Info.plist string as the fallback. Run
`flutter build ios --no-codesign -t lib/main_prod.dart` once to be sure the project still builds, then commit these
files + the pbxproj change and push.

## 3. Build and upload

```bash
cd ~/salam && mobile/ios/scripts/release_ipa.sh 4
```

Check that the "Upload Crashlytics dSYMs" build phase ran without a warning. The build appears in App Store
Connect / TestFlight after processing (10–30 min).

## 4. App Store screenshots (6.9", after the build is uploaded)

Simulator iPhone 16 Pro Max (6.9"), the 1.0.1 build, logged in with the review account — **the user types the
review code themselves** (never type it into the production app). Screens, same as Play:
1. Cihazlar (new card), 2. Qonaq dəvət et sheet, 3. Ana səhifə, 4. Profil.
Status bar 9:41, full battery/signal (`xcrun simctl status_bar booted override --time 9:41 --batteryLevel 100 --cellularBars 4 --wifiBars 3`).
Save raw shots to `~/salam-shots/` and tell the Windows side; framing + upload happen there.

## 5. Clean up (always)

```bash
rm -rf ~/salam-keys ~/salam-shots
rm -f ~/salam/mobile/ios/Runner/GoogleService-Info.plist
```

The Apple Distribution identity stays in the Mac login keychain (needed for the next build).
