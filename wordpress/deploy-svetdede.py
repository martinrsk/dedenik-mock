"""Verify or deploy the Dedeník theme over explicit FTPS."""
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
PRODUCTION_SITE = 'https://dedenik.cz'
SOURCE = Path(__file__).resolve().parents[1] / 'wp-content/themes/dedenik'
RUNTIME_SUFFIXES = {'.php', '.css', '.js', '.json', '.png', '.jpg', '.webp'}


def source_files(source=SOURCE):
    if source.resolve() != source.absolute():
        raise ValueError('Symlinks are not allowed in the deployment source path')
    files = []
    for path in source.rglob('*'):
        if path.is_symlink():
            raise ValueError('Symlinks are not allowed in the deployment source')
        if not path.is_file():
            continue
        relative = path.relative_to(source).as_posix()
        if path.name.lower().startswith('wp-config') or path.suffix.lower() not in RUNTIME_SUFFIXES | {'.md', '.txt'}:
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


class DeploymentError(ValueError):
    """A diagnostic safe to print in Actions logs."""


def required_env(name):
    value = os.environ.get(name, '')
    if not value:
        raise DeploymentError('Missing Actions configuration: ' + name)
    return value


def production_directory():
    path = required_env('DEDENIK_FTP_THEME_PATH')
    if not re.fullmatch(r'(?:/[A-Za-z0-9_-][A-Za-z0-9_.-]*)*/wp-content/themes/dedenik', path):
        raise DeploymentError('Production FTPS path must be absolute and end in /wp-content/themes/dedenik.')
    if path == REMOTE or path.startswith('/public_html/svetdede/'):
        raise DeploymentError('The staging directory cannot be used for production.')
    return path


def public_stylesheet(site, context, nonce):
    url = f'{site}/wp-content/themes/dedenik/style.css?deploy={nonce}'
    request = urllib.request.Request(url, headers={'Cache-Control': 'no-cache'})
    with urllib.request.urlopen(request, timeout=30, context=context) as response:
        if response.geturl() != url:
            raise DeploymentError('The public stylesheet redirected away from the configured URL.')
        return response.read()


def deploy(apply=False, target='test'):
    files = source_files()
    if target == 'production':
        remote, site, prefix = production_directory(), PRODUCTION_SITE, 'DEDENIK_FTP_'
    elif target == 'test':
        remote, site, prefix = REMOTE, SITE, 'SVETDEDE_FTP_'
    else:
        raise DeploymentError('Unknown deployment target.')
    host = required_env(prefix + 'HOST')
    if not re.fullmatch(r'[A-Za-z0-9.-]+', host):
        raise ValueError('FTP host must be a hostname, without a scheme or path')
    user = required_env(prefix + 'USER')
    password = required_env(prefix + 'PASSWORD')
    port = int(os.environ.get(prefix + 'PORT', '21'))
    if not 1 <= port <= 65535:
        raise ValueError('Invalid FTP port')
    context = ssl.create_default_context()
    run = uuid.uuid4().hex[:12]
    with ftplib.FTP_TLS(context=context, timeout=30) as ftp:
        print('Connecting with verified FTPS.', flush=True)
        ftp.connect(host, port)
        # FTP_TLS negotiates TLS before sending the username or password.
        ftp.login(user, password)
        print('FTPS certificate and login verified.', flush=True)
        ftp.prot_p()
        ftp.cwd(remote)
        print(f'Opened FTPS target for {site}: {remote}', flush=True)
        installed = io.BytesIO()
        ftp.retrbinary('RETR style.css', installed.write)
        if not re.search(r'^Theme Name:\s*Dedeník\s*$', installed.getvalue().decode('utf-8-sig'), re.M):
            raise DeploymentError('The FTPS target does not contain the existing Dedeník theme.')
        if target == 'production' and installed.getvalue() != public_stylesheet(site, context, run):
            raise DeploymentError('The FTPS stylesheet differs from the public stylesheet on dedenik.cz.')
        print(f'Dedeník theme verified for {site}. {len(files)} runtime files ready to transfer.', flush=True)
        if not apply:
            print('Dry run complete. No remote files were changed.')
            return
        for path in files:
            relative = path.relative_to(SOURCE)
            parent = relative.parent.as_posix()
            ftp.cwd(remote)
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
    expected = (SOURCE / 'style.css').read_bytes()
    if public_stylesheet(site, context, run + '-verify') != expected:
        raise DeploymentError('Upload completed, but the public stylesheet differs from the uploaded file.')
    with urllib.request.urlopen(site + '/?deploy=' + run, timeout=30, context=context) as response:
        html = response.read().decode('utf-8', errors='replace')
    if 'class="site-footer"' not in html:
        raise RuntimeError('Public homepage check failed after upload')
    print('Verified the uploaded stylesheet and public homepage over HTTPS.')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--target', choices=['test', 'production'], default='test')
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--apply', action='store_true', help='Upload theme files')
    mode.add_argument('--dry-run', action='store_true', help='Verify FTPS without writing (default)')
    args = parser.parse_args()
    try:
        deploy(apply=args.apply, target=args.target)
    except DeploymentError as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
    except Exception as error:
        # Do not include exception messages that may contain FTP responses or credentials.
        code = str(error)[:3]
        detail = '; FTP status ' + code if re.fullmatch(r'[45][0-9]{2}', code) else ''
        print('Deployment failed (' + type(error).__name__ + detail + '). Check secret names, TLS hostname, FTP access and target directory.', file=sys.stderr)
        sys.exit(1)
