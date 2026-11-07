"""Publish only the original static preview to its fixed staging directory."""
import ftplib
import os
from pathlib import Path
import ssl
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
REMOTE = '/public_html/svetdede/mock'
SITE = 'https://svetdede.dedenik.cz/mock/'


def main():
    files = [ROOT / name for name in ('index.html', 'style.css', 'final.css', 'content.js', 'app.js')]
    files += sorted(p for p in (ROOT / 'assets').rglob('*') if p.is_file())
    for path in files:
        if path.is_symlink() or path.suffix.lower() not in {'.html', '.css', '.js', '.png', '.jpg', '.jpeg', '.webp', '.svg', '.woff', '.woff2'}:
            raise ValueError('Unexpected static asset')
    context = ssl.create_default_context()
    run = uuid.uuid4().hex[:12]
    with ftplib.FTP_TLS(context=context, timeout=30) as ftp:
        ftp.connect(os.environ['SVETDEDE_FTP_HOST'], int(os.environ.get('SVETDEDE_FTP_PORT', '21')))
        ftp.login(os.environ['SVETDEDE_FTP_USER'], os.environ['SVETDEDE_FTP_PASSWORD'])
        ftp.prot_p()
        ftp.cwd('/public_html/svetdede')
        try:
            ftp.cwd('mock')
        except ftplib.error_perm:
            ftp.mkd('mock')
            ftp.cwd('mock')
        # Upload dependencies first and the entry page last.
        files.sort(key=lambda p: p.name == 'index.html')
        for path in files:
            relative = path.relative_to(ROOT)
            ftp.cwd(REMOTE)
            for component in relative.parent.parts:
                try:
                    ftp.cwd(component)
                except ftplib.error_perm:
                    ftp.mkd(component)
                    ftp.cwd(component)
            temporary = '.upload-' + run + '-' + path.name
            with path.open('rb') as stream:
                ftp.storbinary('STOR ' + temporary, stream)
            ftp.rename(temporary, path.name)
    for path in files:
        url = SITE + path.relative_to(ROOT).as_posix() + '?verify=' + run
        with urllib.request.urlopen(url, context=context, timeout=30) as response:
            if response.read() != path.read_bytes():
                raise RuntimeError('Public asset differs from source')
    print('Verified original mockup and all ' + str(len(files)) + ' files at ' + SITE)


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Mock deployment failed: ' + type(error).__name__)
        raise SystemExit(1)
