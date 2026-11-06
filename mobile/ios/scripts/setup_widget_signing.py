"""App Store signing for the home-screen widget, through the App Store Connect API.

  python3 mobile/ios/scripts/setup_widget_signing.py

Registers the bundle id az.salam.sezam.DoorWidget and installs the profile "Salam Sesame Door Widget App Store",
made with the Apple Distribution certificate already in this Mac's keychain (setup_signing.py made it). Re-running
is safe. Needs `pip3 install --user cryptography` and ~/salam-keys/AuthKey_N5BQL355YG.p8.

The widget and the app share data through a Keychain group, which needs no capability at Apple, so the main
profile "Salam Sesame App Store" stays as it is.
"""
import base64
import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec
from cryptography.hazmat.primitives.asymmetric.utils import decode_dss_signature

KEY_ID, ISSUER = 'N5BQL355YG', 'daf412c4-7da1-4295-9e35-19533b48bc70'
IDENTIFIER = 'az.salam.sezam.DoorWidget'
PROFILE_NAME = 'Salam Sesame Door Widget App Store'
ASC_KEY = serialization.load_pem_private_key(
    open(os.path.expanduser(f'~/salam-keys/AuthKey_{KEY_ID}.p8'), 'rb').read(), None)
PROFILES_DIR = os.path.expanduser('~/Library/MobileDevice/Provisioning Profiles')


def token():
    b64 = lambda b: base64.urlsafe_b64encode(b).rstrip(b'=')
    head = b64(json.dumps({'alg': 'ES256', 'kid': KEY_ID, 'typ': 'JWT'}).encode())
    now = int(time.time())
    body = b64(json.dumps({'iss': ISSUER, 'iat': now, 'exp': now + 900, 'aud': 'appstoreconnect-v1'}).encode())
    r, s = decode_dss_signature(ASC_KEY.sign(head + b'.' + body, ec.ECDSA(hashes.SHA256())))
    return (head + b'.' + body + b'.' + b64(r.to_bytes(32, 'big') + s.to_bytes(32, 'big'))).decode()


def api(method, path, body=None):
    req = urllib.request.Request('https://api.appstoreconnect.apple.com' + path, method=method,
                                 data=json.dumps(body).encode() if body is not None else None,
                                 headers={'Authorization': 'Bearer ' + token(), 'Content-Type': 'application/json'})
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        sys.exit(f'{method} {path} → {e.code}: {e.read().decode()[:500]}')


def keychain_serials():
    out = subprocess.run(['security', 'find-certificate', '-a', '-p'], capture_output=True, text=True).stdout
    serials = set()
    for pem in out.split('-----END CERTIFICATE-----'):
        if 'BEGIN CERTIFICATE' in pem:
            cert = x509.load_pem_x509_certificate((pem[pem.index('-----BEGIN'):] + '-----END CERTIFICATE-----\n').encode())
            serials.add(format(cert.serial_number, 'X').lstrip('0'))
    return serials


def install(profile):
    os.makedirs(PROFILES_DIR, exist_ok=True)
    path = f"{PROFILES_DIR}/{profile['attributes']['uuid']}.mobileprovision"
    open(path, 'wb').write(base64.b64decode(profile['attributes']['profileContent']))
    print('profile installed:', path)


quoted = urllib.parse.quote(IDENTIFIER)
bundle = next((b for b in api('GET', f'/v1/bundleIds?filter[identifier]={quoted}&limit=20')['data']
               if b['attributes']['identifier'] == IDENTIFIER), None)
if bundle is None:
    bundle = api('POST', '/v1/bundleIds', {'data': {'type': 'bundleIds', 'attributes': {
        'identifier': IDENTIFIER, 'name': 'Salam Sesame Door Widget', 'platform': 'IOS'}}})['data']
    print('bundle id registered:', bundle['id'])

serials = keychain_serials()
certificate = next((c for c in api('GET', '/v1/certificates?filter[certificateType]=DISTRIBUTION&limit=50')['data']
                    if c['attributes']['serialNumber'].upper().lstrip('0') in serials), None)
if certificate is None:
    sys.exit('No Apple Distribution certificate from this team in the keychain: run setup_signing.py first.')

for profile in api('GET', f'/v1/profiles?filter[name]={urllib.parse.quote(PROFILE_NAME)}')['data']:
    if profile['attributes']['profileState'] == 'ACTIVE':
        install(profile)
        print('reusing profile', PROFILE_NAME)
        sys.exit(0)
    api('DELETE', f"/v1/profiles/{profile['id']}")

profile = api('POST', '/v1/profiles', {'data': {'type': 'profiles',
    'attributes': {'name': PROFILE_NAME, 'profileType': 'IOS_APP_STORE'},
    'relationships': {'bundleId': {'data': {'type': 'bundleIds', 'id': bundle['id']}},
                      'certificates': {'data': [{'type': 'certificates', 'id': certificate['id']}]}}}})['data']
install(profile)
print('done: sign with "Apple Distribution" + profile', PROFILE_NAME)
