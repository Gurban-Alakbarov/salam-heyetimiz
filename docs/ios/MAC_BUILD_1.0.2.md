# iOS 1.0.2 (build 5) — Mac steps

For the Claude session on the Mac (Mac in Cloud, repo at `~/salam`). Work only inside `~/salam`, `~/salam-keys` and
`~/Downloads`. The Mac is shared with other customers: never leave keys behind. Do not touch
`AuthKey_6U62SMSY69.p8` or `GoogleService-Info3.plist` in Downloads; they are not ours. Do not use any password file.

New in this build:
- The home-screen widget (WidgetKit, iOS 17+, the same design as Android). The code is written on Windows and has
  never been compiled: fixing compile errors in `mobile/ios/SalamDoorWidget/` is expected.
- AzNav opens with the route on iPhone.
- No advertising identifier from Firebase Analytics.

The Mac has no GitHub credentials. Commit locally; step 6 sends the commits back.

## 0. The repo

The user already ran `git fetch origin && git reset --hard origin/main` before starting this session. Check:

```bash
cd ~/salam
git log --oneline -1                     # must match origin/main
grep '^version' mobile/pubspec.yaml      # must say 1.0.2+5
ls mobile/ios/SalamDoorWidget            # the widget sources
```

## 1. Keys (the user copied them from Google Drive into ~/Downloads)

```bash
mkdir -p ~/salam-keys && chmod 700 ~/salam-keys
mv ~/Downloads/AuthKey_N5BQL355YG.p8 ~/salam-keys/ && chmod 600 ~/salam-keys/AuthKey_N5BQL355YG.p8
mv ~/Downloads/GoogleService-Info.plist ~/salam/mobile/ios/Runner/GoogleService-Info.plist
```

`GoogleService-Info.plist` is git-ignored: never commit it.

## 2. Add the widget target and its signing

```bash
cd ~/salam
ruby mobile/ios/scripts/add_widget_target.rb
pip3 install --user cryptography
/usr/bin/python3 mobile/ios/scripts/setup_widget_signing.py
```

Use `/usr/bin/python3`: Homebrew python is broken on this Mac. If the gem is missing: `gem install --user-install xcodeproj`.

## 3. Compile the widget

```bash
cd ~/salam/mobile
flutter pub get
flutter build ios --release --no-codesign -t lib/main_prod.dart --build-number 5
cd ios
xcodebuild -workspace Runner.xcworkspace -scheme SalamDoorWidget -configuration Release \
  -destination 'generic/platform=iOS' CODE_SIGNING_ALLOWED=NO build
```

Fix what the compiler reports, keeping the behaviour:
- Two taps open the door: the first arms the widget ("Təsdiq üçün yenə toxunun"), the second sends. An iOS widget
  cannot show a dialog, and Android asks for confirmation.
- `opened` may only come from a confirmed command, never from a bare 202 (`DoorAPI.swift`).
- The extension's deployment target stays 17.0. The app stays at 15.0: on iOS 15–16 the widget is simply not offered.
- The widget reads only the Keychain group `az.salam.sezam.shared`. In `Runner.entitlements` the app's own group stays
  first, because the first group is where the session tokens are written.

If Xcode reports a dependency cycle, "Embed App Extensions" must come before "Thin Binary" in Runner's build phases.

Commit the project file and any fixes:

```bash
cd ~/salam && git add mobile/ios && git commit -m "feat(ios): add the door widget target to the project"
```

## 4. Build and upload

```bash
cd ~/salam && mobile/ios/scripts/release_ipa.sh 5
```

Check that the "Upload Crashlytics dSYMs" build phase ran without a warning. If the archive fails on signing, the
widget target must be manual in Release with the profile "Salam Sesame Door Widget App Store". The build appears in
App Store Connect after processing (10–30 min).

## 5. Tell the Windows side

Report the build as uploaded. Windows attaches it, gives TestFlight access, and sends the version for review after the
widget passes on a real iPhone.

Test on an iPhone with iOS 17+ (TestFlight):
1. Sign in to the app and open the Cihazlar screen once, so the doors reach the widget.
2. Long-press the Home Screen, tap +, add Salam Sesame in the small and the medium size.
3. Long-press the widget, Edit Widget, choose a door.
4. Tap once: "Təsdiq üçün yenə toxunun". Wait 6 seconds: it returns to "Aç".
5. Tap twice: the door opens and the colours go red, then dark green.
6. Sign out of the app: the widget shows "Qapı seçilməyib".

## 6. Send the commits back (the Mac cannot push)

```bash
cd ~/salam && git format-patch origin/main --stdout > ~/Desktop/salam-1.0.2-widget.patch
```

The user copies this file to Google Drive, and Windows applies it with `git am`.

## 7. Clean up (always, after the patch is on Drive)

```bash
rm -rf ~/salam-keys ~/salam-shots ~/Desktop/salam-patches ~/Desktop/salam-1.0.2-widget.patch
rm -f ~/salam/mobile/ios/Runner/GoogleService-Info.plist
```

The Apple Distribution identity stays in the Mac login keychain (needed for the next build).
