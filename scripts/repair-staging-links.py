"""Repair WordPress rewrite rules only on the fixed staging site."""
import ftplib
import io
import os
import re
import ssl
import urllib.request
import uuid

BLOCK = '''# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress'''


def main():
    context = ssl.create_default_context()
    with ftplib.FTP_TLS(context=context, timeout=30) as ftp:
        ftp.connect(os.environ['SVETDEDE_FTP_HOST'], int(os.environ.get('SVETDEDE_FTP_PORT', '21')))
        ftp.login(os.environ['SVETDEDE_FTP_USER'], os.environ['SVETDEDE_FTP_PASSWORD'])
        ftp.prot_p()
        ftp.cwd('/public_html/svetdede')
        # Confirm the destination before writing routing configuration.
        if 'wp-admin' not in [name.rstrip('/').split('/')[-1] for name in ftp.nlst()]:
            raise RuntimeError('Not a WordPress root')
        original = io.BytesIO()
        try:
            ftp.retrbinary('RETR .htaccess', original.write)
        except ftplib.error_perm as error:
            if not str(error).startswith('550'):
                raise
        raw = original.getvalue()
        text = raw.decode('utf-8-sig')
        if '# BEGIN WordPress' in text:
            if text.count('# BEGIN WordPress') != 1 or text.count('# END WordPress') != 1:
                raise RuntimeError('Ambiguous rewrite blocks')
            updated = re.sub(r'# BEGIN WordPress.*?# END WordPress', lambda _: BLOCK, text, flags=re.S)
        else:
            updated = text + '\n' + BLOCK + '\n'
        if raw:
            # Apache protects .ht* files; retain a copy for rollback without logging contents.
            backup = '/public_html/svetdede/.htaccess-before-ai-' + uuid.uuid4().hex
            ftp.storbinary('STOR ' + backup, io.BytesIO(raw))
        temporary = '.htaccess-upload-' + uuid.uuid4().hex
        ftp.storbinary('STOR ' + temporary, io.BytesIO(updated.encode()))
        ftp.rename(temporary, '.htaccess')
        print('Staging rewrite block installed; existing directives retained.')
        try:
            paths = ['/', '/2020/01/03/host-dedeniku-tora-jak-se-plni-sen/',
                     '/2019/09/10/btw-kocky-a-zase-kocky-aneb-pelisek-ve-dvore-kralove/', '/mock/']
            for path in paths:
                with urllib.request.urlopen('https://svetdede.dedenik.cz' + path, timeout=30, context=context) as response:
                    if response.status != 200:
                        raise RuntimeError('HTTP verification failed')
                    print('HTTP 200: ' + path)
        except Exception:
            ftp.storbinary('STOR .htaccess', io.BytesIO(raw))
            print('Verification failed; previous routing restored.')
            raise


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Routing repair failed: ' + type(error).__name__)
        raise SystemExit(1)
