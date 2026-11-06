# iOS 1.0.2 (build 5) — Mac steps

For the Claude session on the Mac (Mac in Cloud, repo at `~/salam`). Work only inside `~/salam` and `~/salam-keys`.
The Mac is shared with other customers: never leave keys behind. Do not touch `AuthKey_6U62SMSY69.p8` or
`GoogleService-Info3.plist` in Downloads; they are not ours.

What is new in this build: AzNav opens with the route on iPhone, and no advertising identifier from Firebase Analytics.
The home-screen widget is not in this build.

## 0. Update the repo

The Mac still has two local commits that are already on GitHub in another form, so reset instead of pulling.

```bash
cd ~/salam && git fetch origin && git reset --hard origin/main
grep '^version' mobile/pubspec.yaml      # must say 1.0.2+5
```

## 1. Keys (the user copied them from Google Drive into ~/Downloads)

```bash
mkdir -p ~/salam-keys && chmod 700 ~/salam-keys
mv ~/Downloads/AuthKey_N5BQL355YG.p8 ~/salam-keys/ && chmod 600 ~/salam-keys/AuthKey_N5BQL355YG.p8
mv ~/Downloads/GoogleService-Info.plist ~/salam/mobile/ios/Runner/GoogleService-Info.plist
```

`GoogleService-Info.plist` is git-ignored: never commit it.

## 2. Build and upload

```bash
cd ~/salam && mobile/ios/scripts/release_ipa.sh 5
```

Check that the "Upload Crashlytics dSYMs" build phase ran without a warning. The build appears in App Store Connect
after processing (10–30 min). Then tell the Windows side: it attaches the build, updates the review notes and sends
the version for review through the API.

## 3. Clean up (always)

```bash
rm -rf ~/salam-keys ~/salam-shots ~/Desktop/salam-patches
rm -f ~/salam/mobile/ios/Runner/GoogleService-Info.plist
```

The Apple Distribution identity stays in the Mac login keychain (needed for the next build).

## 4. Test on a real iPhone (TestFlight, before Apple's answer)

1. Device card > directions: AzNav opens with the route.
2. Without AzNav installed: the App Store page opens.
