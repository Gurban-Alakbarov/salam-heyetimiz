# iOS release runbook — Salam Sesame (for Claude Code on the Mac)

You are Claude Code running on the rented Mac (Mac in Cloud, user `user297721`). Another Claude session on the
owner's Windows PC prepared everything else; your job is ONLY the Mac part below. Talk to the user in
**Azerbaijani**, briefly. Work step by step, report each step's result, and stop and ask when something fails.

Already done elsewhere, do not redo:
- App Store Connect app "Salam Sesame": App ID 6828067682, bundle `az.salam.sezam`, metadata, privacy, pricing,
  review info.
- Code for iOS: commit `007a396` and later.

The Windows session attaches the build and submits after you finish.

## Facts
- Repo: this Git repository (`mobile/` is the Flutter app; iOS project in `mobile/ios`).
- Team ID `2654395QYQ`. Bundle ID `az.salam.sezam`. Version 1.0, build number 1.
- App Store Connect API key: Key ID `N5BQL355YG`, Issuer `daf412c4-7da1-4295-9e35-19533b48bc70`.
  - The user puts the `.p8` at `~/salam-keys/AuthKey_N5BQL355YG.p8`.
- Firebase iOS config: the user puts it at `~/salam-keys/GoogleService-Info.plist`. It is gitignored, never commit it.
- Rules:
  - Never commit or print secrets.
  - Never use or ask for the Mac password.
  - This Mac is shared with other customers, so run `chmod 600` on the key files.

## Step 0 — checks
Run each check and report the result:
- `sw_vers`
- `xcodebuild -version`: Xcode **26 or newer** is required for App Store uploads. If it is older, stop and tell the user.
- `flutter --version`: the project uses Flutter **3.44.x** (Dart 3.12). If Flutter is missing or older, upgrade it with `flutter upgrade` or install it, then re-check.
- `pod --version`, `python3 --version`, `git --version`

## Step 1 — get the code
```bash
cd ~/Documents/salam-heyetimiz      # the old clone; if it is not this repo or is broken, re-clone into ~/salam
git remote -v
git status
git fetch origin
git checkout main
git pull --ff-only
git log --oneline -1
```
If the working tree has local changes, show them to the user before doing anything. Never discard them silently.

## Step 2 — secrets in place
```bash
ls -la ~/salam-keys/        # expect AuthKey_N5BQL355YG.p8 and GoogleService-Info.plist
chmod 700 ~/salam-keys
chmod 600 ~/salam-keys/*
cp ~/salam-keys/GoogleService-Info.plist mobile/ios/Runner/GoogleService-Info.plist
grep -A1 BUNDLE_ID mobile/ios/Runner/GoogleService-Info.plist   # must be az.salam.sezam
```
The Xcode project already references `Runner/GoogleService-Info.plist`; only the file has to be there.

## Step 3 — signing (once) and build + upload
The team has no registered devices, so Xcode's automatic signing cannot make a development profile. The build
therefore uses manual **App Store** signing.

First run the one-time signing setup:
```bash
pip3 install --user cryptography
python3 mobile/ios/scripts/setup_signing.py
security find-identity -v -p codesigning | grep "Apple Distribution"   # must list one identity
```
The setup creates an "Apple Distribution" certificate (new key, kept in `~/salam-keys/dist-key.pem`) and the
"Salam Sesame App Store" profile, through the API key. It imports the key and certificate into the login keychain
and installs the profile. Do not delete or revoke the existing "ALKN GROUP MMC" distribution certificate; it belongs
to another app.

If macOS shows a keychain dialog during the build ("codesign wants to access key"), the USER types the Mac login
password there and clicks **Always Allow**. Never ask for or type that password yourself.

Then build and upload:
```bash
chmod +x mobile/ios/scripts/release_ipa.sh
mobile/ios/scripts/release_ipa.sh 1
```
The script does the whole build:
- `flutter clean`, `pub get` (plugins come through Swift Package Manager; `pod install` runs only if a Podfile exists);
- `flutter build ios --no-codesign` with the prod entrypoint;
- `xcodebuild archive` with manual "Apple Distribution" signing and the "Salam Sesame App Store" profile;
- `-exportArchive` with `destination=upload`, which uploads to App Store Connect.

Expect it to take 10–25 minutes. On failure, show the last ~40 lines and diagnose. Typical causes:
- signing or profile: re-run setup_signing.py, check `security find-identity`;
- CocoaPods versions: try `cd mobile/ios && pod repo update && pod install`;
- the deployment target.

A failed upload of an existing build number needs `release_ipa.sh 2`.

When it succeeds, tell the user: **"Build 1 yükləndi"**. The Windows session takes over from there.

## Step 4 — iPhone screenshots (6.9", 1320×2868)
1. Boot an iPhone 6.9" simulator:
   ```bash
   xcrun simctl list devices available | grep -i "Pro Max"
   xcrun simctl boot "<an iPhone Pro Max with a 6.9-inch screen>"
   open -a Simulator
   ```
2. Run the app on it from `mobile/`:
   ```bash
   flutter run -d <simulator-id> -t lib/main_prod.dart
   ```
   Debug mode is fine. The debug banner is off.
3. **The user signs in, not you.** Login is `review@sezam.salam.az`. The code is given to the user separately, never type it yourself.
4. The user opens each screen. On each one, capture it with:
   ```bash
   xcrun simctl io booted screenshot ~/salam-shots/N.png
   ```
   Capture these 4 screens:
   - Cihazlar (device list)
   - Qapı (device detail)
   - Dəvət et / Qonaq linki
   - Profil
5. Check the size:
   ```bash
   sips -g pixelWidth -g pixelHeight ~/salam-shots/*.png
   ```
   It must be 1320×2868 (or 1290×2796).
6. Upload to the en-US and ru listings:
   ```bash
   pip3 install --user cryptography
   python3 mobile/ios/scripts/upload_screenshots.py ~/salam-shots/1.png ~/salam-shots/2.png ~/salam-shots/3.png ~/salam-shots/4.png
   ```
7. Tell the user: **"Ekran şəkilləri yükləndi"**.

## Step 5 — clean up
After the user confirms the app was submitted:
```bash
rm -rf ~/salam-keys mobile/ios/Runner/GoogleService-Info.plist
```
Do NOT commit anything from this Mac unless the user asks.
