"""Deploy runtime theme files to one fixed test directory using verified FTPS."""
import argparse
import ftplib
import io
import os
from pathlib import Path
import re
import ssl
import sys
import urllib.request
import uuid

REMOTE = '/public_html/svetdede/wp-content/themes/dedenik'
SITE = 'https://svetdede.dedenik.cz'
SOURCE = Path(__file__).resolve().parents[1] / 'wp-content/themes/dedenik'
RUNTIME_SUFFIXES = {'.php', '.css', '.js', '.json', '.png', '.jpg', '.webp'}


def source_files(source=SOURCE):
    files = []
    for path in source.rglob('*'):
        if path.is_symlink():
            raise ValueError('Symlinks are not allowed in the deployment source')
        if not path.is_file():
            continue
        relative = path.relative_to(source).as_posix()
        if path.name.lower() == 'wp-config.php' or path.suffix.lower() not in RUNTIME_SUFFIXES | {'.md', '.txt'}:
            raise ValueError('Unexpected file in the theme: ' + relative)
        if not re.fullmatch(r'[A-Za-z0-9_./-]+', relative):
            raise ValueError('Unsupported theme filename')
        if path.suffix.lower() in RUNTIME_SUFFIXES:
            files.append(path)
    if not (source / 'style.css').is_file() or not (source / 'functions.php').is_file():
        raise ValueError('A complete WordPress theme is required')
    # New helper dependencies first, then the bootstrap, then templates.
    def order(path):
        relative = path.relative_to(source).as_posix()
        if relative.startswith('assets/'):
            return (0, relative)
        if relative in {'inc/world.php', 'inc/footer.php'}:
            return (1, relative)
        if relative.startswith('inc/'):
            return (2, relative)
        if relative == 'functions.php':
            return (3, relative)
        if relative == 'style.css':
            return (5, relative)
        return (4, relative)
    return sorted(files, key=order)


def required_env(name):
    value = os.environ.get(name, '')
    if not value:
        raise ValueError('Missing GitHub secret: ' + name)
    return value


def deploy(apply=False):
    files = source_files()
    host = required_env('SVETDEDE_FTP_HOST')
    if not re.fullmatch(r'[A-Za-z0-9.-]+', host):
        raise ValueError('FTP host must be a hostname, without a scheme or path')
    user = required_env('SVETDEDE_FTP_USER')
    password = required_env('SVETDEDE_FTP_PASSWORD')
    port = int(os.environ.get('SVETDEDE_FTP_PORT', '21'))
    if not 1 <= port <= 65535:
        raise ValueError('Invalid FTP port')
    context = ssl.create_default_context()
    with ftplib.FTP_TLS(context=context, timeout=30) as ftp:
        print('Connecting with verified FTPS.', flush=True)
        ftp.connect(host, port)
        # FTP_TLS negotiates TLS before sending the username or password.
        ftp.login(user, password)
        print('FTP login accepted.', flush=True)
        ftp.prot_p()
        ftp.cwd(REMOTE)
        print('Test theme directory opened.', flush=True)
        installed = io.BytesIO()
        ftp.retrbinary('RETR style.css', installed.write)
        if not re.search(r'^Theme Name:\s*Dedeník\s*$', installed.getvalue().decode('utf-8-sig'), re.M):
            raise ValueError('The target is not the existing Dedenik theme; refusing deployment')
        print(f'FTPS verified; fixed target {REMOTE}; {len(files)} runtime files.')
        if not apply:
            print('Dry run complete. No remote files were changed.')
            return
        run = uuid.uuid4().hex[:12]
        for path in files:
            relative = path.relative_to(SOURCE)
            parent = relative.parent.as_posix()
            ftp.cwd(REMOTE)
            if parent != '.':
                for component in relative.parent.parts:
                    try:
                        ftp.cwd(component)
                    except ftplib.error_perm:
                        ftp.mkd(component)
                        ftp.cwd(component)
            temporary = f'.deploy-{run}-{path.name}'
            with path.open('rb') as stream:
                ftp.storbinary('STOR ' + temporary, stream)
            ftp.rename(temporary, path.name)
        print('Runtime files uploaded. No server files were deleted.')
    expected = re.search(r'^Version:\s*(\S+)', (SOURCE / 'style.css').read_text(encoding='utf-8'), re.M).group(1)
    request = urllib.request.Request(SITE + '/wp-content/themes/dedenik/style.css?deploy=' + run, headers={'Cache-Control': 'no-cache'})
    with urllib.request.urlopen(request, timeout=30, context=context) as response:
        stylesheet = response.read().decode('utf-8-sig')
    actual = re.search(r'^Version:\s*(\S+)', stylesheet, re.M)
    if not actual or actual.group(1) != expected:
        raise RuntimeError('Deployment finished, but public theme version could not be verified')
    with urllib.request.urlopen(SITE + '/?deploy=' + run, timeout=30, context=context) as response:
        html = response.read().decode('utf-8', errors='replace')
    if 'class="site-footer"' not in html:
        raise RuntimeError('Public homepage check failed after upload')
    print('Verified public theme version ' + expected + ' and homepage over HTTPS.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--apply', action='store_true', help='Upload theme files')
    mode.add_argument('--dry-run', action='store_true', help='Verify FTPS without writing (default)')
    args = parser.parse_args()
    try:
        deploy(apply=args.apply)
    except Exception as error:
        # Do not include exception messages that may contain FTP responses or credentials.
        code = str(error)[:3]
        detail = '; FTP status ' + code if re.fullmatch(r'[45][0-9]{2}', code) else ''
        print('Deployment failed (' + type(error).__name__ + detail + '). Check secret names, TLS hostname, FTP access and target directory.', file=sys.stderr)
        sys.exit(1)
