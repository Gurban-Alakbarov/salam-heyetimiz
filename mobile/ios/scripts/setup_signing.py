"""One-time App Store signing setup on the build Mac, through the App Store Connect API (no device needed).

  python3 mobile/ios/scripts/setup_signing.py

1. Creates a new private key + CSR and an "Apple Distribution" certificate (DISTRIBUTION) for team 2654395QYQ.
2. Imports key + certificate into the login keychain (macOS-compatible PKCS#12).
3. Creates the App Store provisioning profile "Salam Sesame App Store" for az.salam.sezam and installs it.

Re-running is safe: it reuses the profile if one with that name exists and its certificate is in the keychain.
Needs `pip3 install --user cryptography` and ~/salam-keys/AuthKey_N5BQL355YG.p8. The private key never leaves
this Mac (a copy stays in ~/salam-keys/dist-key.pem, chmod 600).
"""
import base64
import json
import os
import secrets
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

from cryptography import x509
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec, rsa
from cryptography.hazmat.primitives.asymmetric.utils import decode_dss_signature
from cryptography.hazmat.primitives.serialization import pkcs12
from cryptography.x509.oid import NameOID

KEY_ID, ISSUER = 'N5BQL355YG', 'daf412c4-7da1-4295-9e35-19533b48bc70'
BUNDLE_ID_RESOURCE = '54U4DW66Q3'          # az.salam.sezam
PROFILE_NAME = 'Salam Sesame App Store'
KEYS = os.path.expanduser('~/salam-keys')
ASC_KEY = serialization.load_pem_private_key(open(f'{KEYS}/AuthKey_{KEY_ID}.p8', 'rb').read(), None)
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


def keychain_has(serial_hex):
    out = subprocess.run(['security', 'find-certificate', '-a', '-Z', '-p'], capture_output=True, text=True).stdout
    certs = out.split('-----END CERTIFICATE-----')
    for pem in certs:
        if 'BEGIN CERTIFICATE' not in pem:
            continue
        c = x509.load_pem_x509_certificate((pem[pem.index('-----BEGIN'):] + '-----END CERTIFICATE-----\n').encode())
        if format(c.serial_number, 'X').lstrip('0') == serial_hex.upper().lstrip('0'):
            return True
    return False


def install_profile(content_b64, uuid):
    os.makedirs(PROFILES_DIR, exist_ok=True)
    path = f'{PROFILES_DIR}/{uuid}.mobileprovision'
    open(path, 'wb').write(base64.b64decode(content_b64))
    print('profile installed:', path)


# Reuse an existing profile whose certificate is already in this keychain.
for p in api('GET', f'/v1/profiles?filter[name]={urllib.parse.quote(PROFILE_NAME)}&include=certificates')['data']:
    cert_ids = [c['id'] for c in p['relationships']['certificates']['data']]
    certs = [api('GET', f'/v1/certificates/{cid}')['data'] for cid in cert_ids]
    if p['attributes']['profileState'] == 'ACTIVE' and any(keychain_has(c['attributes']['serialNumber']) for c in certs):
        install_profile(p['attributes']['profileContent'], p['attributes']['uuid'])
        print('reusing profile', PROFILE_NAME)
        sys.exit(0)
    api('DELETE', f"/v1/profiles/{p['id']}")   # stale or unusable here: replace it

# New distribution key + certificate.
key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
csr = (x509.CertificateSigningRequestBuilder()
       .subject_name(x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, 'Salam Sesame Distribution'),
                                x509.NameAttribute(NameOID.EMAIL_ADDRESS, 'support@sezam.salam.az')]))
       .sign(key, hashes.SHA256()))
cert_res = api('POST', '/v1/certificates', {'data': {'type': 'certificates', 'attributes': {
    'certificateType': 'DISTRIBUTION',
    'csrContent': csr.public_bytes(serialization.Encoding.PEM).decode()}}})['data']
cert = x509.load_der_x509_certificate(base64.b64decode(cert_res['attributes']['certificateContent']))
print('certificate created:', cert_res['attributes']['name'], cert_res['id'])

os.makedirs(KEYS, mode=0o700, exist_ok=True)
key_path = f'{KEYS}/dist-key.pem'
open(key_path, 'wb').write(key.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8,
                                             serialization.NoEncryption()))
os.chmod(key_path, 0o600)

password = secrets.token_urlsafe(18).encode()
encryption = (serialization.PrivateFormat.PKCS12.encryption_builder().kdf_rounds(50000)
              .key_cert_algorithm(pkcs12.PBES.PBESv1SHA1And3KeyTripleDESCBC).hmac_hash(hashes.SHA1()).build(password))
p12_path = f'{KEYS}/dist.p12'
open(p12_path, 'wb').write(pkcs12.serialize_key_and_certificates(b'Salam Sesame Distribution', key, cert, None, encryption))
os.chmod(p12_path, 0o600)
subprocess.run(['security', 'import', p12_path, '-k', os.path.expanduser('~/Library/Keychains/login.keychain-db'),
                '-P', password.decode(), '-T', '/usr/bin/codesign', '-T', '/usr/bin/security'], check=True)
os.remove(p12_path)
print('imported into the login keychain')

prof = api('POST', '/v1/profiles', {'data': {'type': 'profiles', 'attributes': {'name': PROFILE_NAME, 'profileType': 'IOS_APP_STORE'},
    'relationships': {'bundleId': {'data': {'type': 'bundleIds', 'id': BUNDLE_ID_RESOURCE}},
                      'certificates': {'data': [{'type': 'certificates', 'id': cert_res['id']}]}}}})['data']
install_profile(prof['attributes']['profileContent'], prof['attributes']['uuid'])
print('done: sign with "Apple Distribution" + profile', PROFILE_NAME)
