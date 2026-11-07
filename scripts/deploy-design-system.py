"""Publish the public design-system files to one fixed directory over verified FTPS."""

import argparse
import ftplib
import hashlib
import io
import os
from pathlib import Path
import re
import ssl
import sys
import urllib.request
import uuid


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'design-system'
PARENT = '/public_html/svetdede'
REMOTE = PARENT + '/design-system'
THEME = PARENT + '/wp-content/themes/dedenik'
SITE = 'https://svetdede.dedenik.cz/design-system/'
MARKER = 'Dedeník Design System'
PUBLIC_FILES = (
    'styles.css', 'app.js', 'tokens.css', 'tokens.json', 'system.json',
    'README.md', 'CHANGELOG.md', 'index.html',
)
SOURCE_ONLY_FILES = {'index.template.html'}


def source_files(source=SOURCE):
    """Accept only the reviewed, flat public payload and its non-public template."""
    source = Path(source)
    if source.is_symlink() or not source.is_dir():
        raise ValueError('The design-system source must be a real directory')
    allowed = set(PUBLIC_FILES) | SOURCE_ONLY_FILES
    for path in source.iterdir():
        if path.is_symlink() or not path.is_file() or path.name not in allowed:
            raise ValueError('Unexpected design-system source entry')
    files = [source / name for name in PUBLIC_FILES]
    if not all(path.is_file() for path in files):
        raise ValueError('The complete public design-system payload is required')
    for path in files:
        path.read_text(encoding='utf-8-sig')
    if MARKER not in (source / 'index.html').read_text(encoding='utf-8-sig'):
        raise ValueError('The local entry page lacks the design-system marker')
    return files


def required_env(name):
    value = os.environ.get(name, '')
    if not value:
        raise ValueError('Missing GitHub secret: ' + name)
    return value


def listed_names(ftp):
    return {name.rstrip('/').rsplit('/', 1)[-1] for name in ftp.nlst()}


def verify_target(ftp):
    """Read the existing site identity and entry page before any remote write."""
    ftp.cwd(THEME)
    installed = io.BytesIO()
    ftp.retrbinary('RETR style.css', installed.write)
    if not re.search(r'^Theme Name:\s*Dedeník\s*$', installed.getvalue().decode('utf-8-sig'), re.M):
        raise ValueError('The target does not contain the existing Dedenik theme')
    ftp.cwd(PARENT)
    exists = 'design-system' in listed_names(ftp)
    if exists:
        ftp.cwd(REMOTE)
        if 'index.html' in listed_names(ftp):
            page = io.BytesIO()
            ftp.retrbinary('RETR index.html', page.write)
            if MARKER not in page.getvalue().decode('utf-8-sig'):
                raise ValueError('An unrelated page already occupies the design-system target')
    return exists


def verify_public_files(files, run, context):
    for path in files:
        request = urllib.request.Request(
            SITE + path.name + '?verify=' + run,
            headers={'Cache-Control': 'no-cache'},
        )
        with urllib.request.urlopen(request, timeout=30, context=context) as response:
            actual = hashlib.sha256(response.read()).digest()
        expected = hashlib.sha256(path.read_bytes()).digest()
        if actual != expected:
            raise RuntimeError('The public design-system asset differs from its source')


def deploy(apply=False, source=SOURCE):
    files = source_files(source)
    host = required_env('SVETDEDE_FTP_HOST')
    if not re.fullmatch(r'[A-Za-z0-9.-]+', host):
        raise ValueError('FTP host must be a hostname without a scheme or path')
    user = required_env('SVETDEDE_FTP_USER')
    password = required_env('SVETDEDE_FTP_PASSWORD')
    port = int(os.environ.get('SVETDEDE_FTP_PORT', '21'))
    if not 1 <= port <= 65535:
        raise ValueError('Invalid FTP port')
    context = ssl.create_default_context()
    run = uuid.uuid4().hex[:12]
    with ftplib.FTP_TLS(context=context, timeout=30) as ftp:
        ftp.connect(host, port)
        ftp.login(user, password)
        ftp.prot_p()
        exists = verify_target(ftp)
        print(f'FTPS verified; fixed target {REMOTE}; {len(files)} public files.', flush=True)
        if not apply:
            print('Dry run complete. No remote files were changed.')
            return
        if not exists:
            ftp.cwd(PARENT)
            ftp.mkd('design-system')
        ftp.cwd(REMOTE)
        # PUBLIC_FILES keeps the entry page last, after its dependencies.
        for path in files:
            temporary = '.deploy-' + run + '-' + path.name
            with path.open('rb') as stream:
                ftp.storbinary('STOR ' + temporary, stream)
            ftp.rename(temporary, path.name)
        print('Design-system files uploaded. No server files were deleted.', flush=True)
    verify_public_files(files, run, context)
    print(f'Verified all {len(files)} public files by SHA-256 over HTTPS: {SITE}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--apply', action='store_true', help='Upload only the public design-system files')
    mode.add_argument('--dry-run', action='store_true', help='Verify FTPS without writing (default)')
    args = parser.parse_args()
    try:
        deploy(apply=args.apply)
    except Exception as error:
        # FTP and HTTP exception messages can contain credentials or private responses.
        print('Design-system deployment failed (' + type(error).__name__ + '). Check the source payload, site identity, TLS and FTP access.', file=sys.stderr)
        sys.exit(1)
