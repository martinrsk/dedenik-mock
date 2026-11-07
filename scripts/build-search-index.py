"""Build the public search index on staging with an authenticated, temporary worker."""
import ftplib
import hashlib
import io
import json
import os
from pathlib import Path
import secrets
import sys
import ssl
import time
import urllib.parse
import urllib.error
import urllib.request


def main():
    context = ssl.create_default_context()
    token = secrets.token_urlsafe(48)
    worker = 'index-maintenance-' + secrets.token_hex(16) + '.php'
    template = Path(__file__).with_name('index-worker.php').read_text()
    source = template.replace('__WORKER_EXPIRES__', str(int(time.time()) + 1800)).replace('__WORKER_TOKEN_HASH__', hashlib.sha256(token.encode()).hexdigest())
    site = 'https://svetdede.dedenik.cz/'
    prefix = 'comments_' if '--comments' in sys.argv else ''
    if '--resume' in sys.argv and not prefix:
        raise ValueError('Resume applies only to the discussion index')

    def call(action, **values):
        request = urllib.request.Request(site + worker, data=urllib.parse.urlencode(dict(action=action, **values)).encode(), headers={'X-Dedenik-Index-Token': token})
        try:
            with urllib.request.urlopen(request, context=context, timeout=90) as response:
                result = json.load(response)
        except urllib.error.HTTPError as error:
            # Worker emits only controlled diagnostics, never DB errors or credentials.
            print('Worker HTTP status:', error.code)
            raise RuntimeError('Index operation failed') from None
        if 'error' in result:
            raise RuntimeError('Index operation failed')
        return result

    with ftplib.FTP_TLS(context=context, timeout=30) as ftp:
        ftp.connect(os.environ['SVETDEDE_FTP_HOST'], int(os.environ.get('SVETDEDE_FTP_PORT', '21')))
        ftp.login(os.environ['SVETDEDE_FTP_USER'], os.environ['SVETDEDE_FTP_PASSWORD'])
        ftp.prot_p()
        ftp.cwd('/public_html/svetdede')
        try:
            ftp.storbinary('STOR ' + worker, io.BytesIO(source.encode()))
            print('Temporary authenticated worker installed.', flush=True)
            if '--status' in sys.argv:
                print('Discussion progress:', json.dumps(call('comments_status')), flush=True)
                return
            print(call(prefix + 'install'), flush=True)
            cursor = call('comments_status')['resume_cursor'] if '--resume' in sys.argv else 0
            if cursor:
                print('Resuming before first missing public comment, cursor', cursor, flush=True)
            for batch in range(1000):
                result = call(prefix + 'batch', cursor=cursor)
                cursor = result['cursor']
                if result['done']:
                    break
                if batch % 10 == 0:
                    print('Indexed through ' + ('comment' if prefix else 'post') + ' ID', cursor, flush=True)
            else:
                raise RuntimeError('Index batch limit exceeded')
            print('Coverage verified:', json.dumps(call(prefix + 'verify')), flush=True)
        finally:
            # Reconnect if a long build has expired the FTP control connection.
            try:
                ftp.delete(worker)
            except (OSError, ftplib.Error, EOFError):
                with ftplib.FTP_TLS(context=context, timeout=30) as cleanup:
                    cleanup.connect(os.environ['SVETDEDE_FTP_HOST'], int(os.environ.get('SVETDEDE_FTP_PORT', '21')))
                    cleanup.login(os.environ['SVETDEDE_FTP_USER'], os.environ['SVETDEDE_FTP_PASSWORD'])
                    cleanup.prot_p()
                    cleanup.delete('/public_html/svetdede/' + worker)
            print('Temporary worker removed.', flush=True)
            ftp.close()


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Index build failed:', type(error).__name__)
        raise SystemExit(1)
