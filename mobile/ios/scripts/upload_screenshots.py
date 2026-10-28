"""Upload iPhone screenshots to Salam Sesame's App Store version 1.0 (en-US and ru) through the API.

  python3 mobile/ios/scripts/upload_screenshots.py <png> [<png> ...]

Screenshots: iPhone 6.9" (1320×2868, portrait), uploaded in the given order, replacing any already there.
Needs `pip3 install --user cryptography` and ~/salam-keys/AuthKey_N5BQL355YG.p8.
"""
import base64
import hashlib
import json
import os
import sys
import time
import urllib.error
import urllib.request

from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec
from cryptography.hazmat.primitives.asymmetric.utils import decode_dss_signature

KEY_ID, ISSUER = 'N5BQL355YG', 'daf412c4-7da1-4295-9e35-19533b48bc70'
VERSION = '424d7f4a-87c0-47b1-9014-1626e3715c9a'   # Salam Sesame iOS 1.0
DISPLAY_TYPE = 'APP_IPHONE_67'                       # the 6.7"/6.9" iPhone set
KEY = serialization.load_pem_private_key(
    open(os.path.expanduser(os.environ.get('ASC_KEY_PATH', f'~/salam-keys/AuthKey_{KEY_ID}.p8')), 'rb').read(), None)


def token():
    b64 = lambda b: base64.urlsafe_b64encode(b).rstrip(b'=')
    head = b64(json.dumps({'alg': 'ES256', 'kid': KEY_ID, 'typ': 'JWT'}).encode())
    now = int(time.time())
    body = b64(json.dumps({'iss': ISSUER, 'iat': now, 'exp': now + 900, 'aud': 'appstoreconnect-v1'}).encode())
    r, s = decode_dss_signature(KEY.sign(head + b'.' + body, ec.ECDSA(hashes.SHA256())))
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
        sys.exit(f'{method} {path} → {e.code}: {e.read().decode()[:400]}')


def screenshot_set(localization_id):
    sets = api('GET', f'/v1/appStoreVersionLocalizations/{localization_id}/appScreenshotSets')['data']
    for s in sets:
        if s['attributes']['screenshotDisplayType'] == DISPLAY_TYPE:
            for old in api('GET', f"/v1/appScreenshotSets/{s['id']}/appScreenshots")['data']:
                api('DELETE', f"/v1/appScreenshots/{old['id']}")
            return s['id']
    return api('POST', '/v1/appScreenshotSets', {'data': {'type': 'appScreenshotSets',
        'attributes': {'screenshotDisplayType': DISPLAY_TYPE},
        'relationships': {'appStoreVersionLocalization': {'data': {'type': 'appStoreVersionLocalizations', 'id': localization_id}}}}})['data']['id']


def upload(set_id, path):
    data = open(path, 'rb').read()
    shot = api('POST', '/v1/appScreenshots', {'data': {'type': 'appScreenshots',
        'attributes': {'fileName': os.path.basename(path), 'fileSize': len(data)},
        'relationships': {'appScreenshotSet': {'data': {'type': 'appScreenshotSets', 'id': set_id}}}}})['data']
    for op in shot['attributes']['uploadOperations']:
        chunk = data[op['offset']:op['offset'] + op['length']]
        req = urllib.request.Request(op['url'], data=chunk, method=op['method'],
                                     headers={h['name']: h['value'] for h in op['requestHeaders']})
        urllib.request.urlopen(req, timeout=120).read()
    api('PATCH', f"/v1/appScreenshots/{shot['id']}", {'data': {'type': 'appScreenshots', 'id': shot['id'],
        'attributes': {'uploaded': True, 'sourceFileChecksum': hashlib.md5(data).hexdigest()}}})
    print('  uploaded', os.path.basename(path))


if __name__ == '__main__':
    files = sys.argv[1:]
    if not files:
        sys.exit(__doc__)
    for loc in api('GET', f'/v1/appStoreVersions/{VERSION}/appStoreVersionLocalizations')['data']:
        print(loc['attributes']['locale'])
        set_id = screenshot_set(loc['id'])
        for f in files:
            upload(set_id, f)
    print('Done. Apple processes the images for a few minutes before they show in App Store Connect.')
