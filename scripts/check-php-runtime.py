"""Read minimal staging PHP runtime metadata through an ephemeral authenticated probe."""
import ftplib
import hashlib
import io
import json
import os
import secrets
import ssl
import time
import urllib.request


def main():
    context = ssl.create_default_context()
    token = secrets.token_urlsafe(48)
    worker = 'runtime-check-' + secrets.token_hex(16) + '.php'
    source = '''<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || time() > EXPIRES ||
    !hash_equals('TOKENHASH', hash('sha256', $_SERVER['HTTP_X_DEDENIK_RUNTIME_TOKEN'] ?? ''))) {
    http_response_code(404); exit;
}
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['php_version' => PHP_VERSION, 'php_version_id' => PHP_VERSION_ID,
    'sapi' => PHP_SAPI, 'os_family' => PHP_OS_FAMILY]);
'''.replace('EXPIRES', str(int(time.time()) + 300)).replace('TOKENHASH', hashlib.sha256(token.encode()).hexdigest())
    with ftplib.FTP_TLS(context=context, timeout=30) as ftp:
        ftp.connect(os.environ['SVETDEDE_FTP_HOST'], int(os.environ.get('SVETDEDE_FTP_PORT', '21')))
        ftp.login(os.environ['SVETDEDE_FTP_USER'], os.environ['SVETDEDE_FTP_PASSWORD'])
        ftp.prot_p()
        ftp.cwd('/public_html/svetdede')
        try:
            ftp.storbinary('STOR ' + worker, io.BytesIO(source.encode()))
            request = urllib.request.Request('https://svetdede.dedenik.cz/' + worker,
                data=b'', headers={'X-Dedenik-Runtime-Token': token})
            with urllib.request.urlopen(request, context=context, timeout=30) as response:
                result = json.load(response)
            print('Staging PHP runtime:', json.dumps(result), flush=True)
        finally:
            ftp.delete(worker)
            print('Temporary runtime probe removed.', flush=True)


if __name__ == '__main__':
    main()
