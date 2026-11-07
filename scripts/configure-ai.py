"""Install a server-only Gemini key on the fixed staging WordPress."""
import ftplib
import io
import json
import os
import re
import ssl
import urllib.request
import urllib.parse
import uuid


def main():
    key = os.environ['SVETDEDE_GEMINI_API_KEY']
    if not re.fullmatch(r'(?:AQ\.)?[A-Za-z0-9_-]{20,200}', key):
        raise ValueError('Invalid key format')
    # ABSPATH prevents direct HTTP access from returning or configuring anything.
    # Initialize options once; administrators can subsequently disable the feature.
    php = """<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'DEDENIK_GEMINI_API_KEY' ) ) {
    define( 'DEDENIK_GEMINI_API_KEY', %s );
}
add_action( 'after_setup_theme', function () {
    if ( ! get_option( 'dedenik_ai_server_configured', false ) ) {
        update_option( 'dedenik_ai_daily_limit', 50, false );
        update_option( 'dedenik_ai_enabled', true, false );
        update_option( 'dedenik_ai_server_configured', true, false );
    }
} );
""" % ("'" + key + "'")
    context = ssl.create_default_context()
    with ftplib.FTP_TLS(context=context, timeout=30) as ftp:
        ftp.connect(os.environ['SVETDEDE_FTP_HOST'], int(os.environ.get('SVETDEDE_FTP_PORT', '21')))
        ftp.login(os.environ['SVETDEDE_FTP_USER'], os.environ['SVETDEDE_FTP_PASSWORD'])
        ftp.prot_p()
        ftp.cwd('/public_html/svetdede/wp-content')
        try:
            ftp.cwd('mu-plugins')
        except ftplib.error_perm:
            ftp.mkd('mu-plugins')
            ftp.cwd('mu-plugins')
        # Use a PHP suffix even during transfer; never expose plaintext as a static file.
        temporary = '.ai-' + uuid.uuid4().hex + '.php'
        ftp.storbinary('STOR ' + temporary, io.BytesIO(php.encode()))
        ftp.rename(temporary, 'svetdede-ai-key.php')
    site = 'https://svetdede.dedenik.cz/'
    with urllib.request.urlopen(site, timeout=30, context=context) as response:
        html = response.read().decode('utf-8')
    if key in html or 'ai-search-form' not in html:
        raise RuntimeError('AI form verification failed')
    nonce = re.search(r'name="nonce" value="([A-Za-z0-9]+)"', html)
    if not nonce:
        raise RuntimeError('Search nonce missing')
    request = urllib.request.Request(site + 'wp-admin/admin-ajax.php', data=urllib.parse.urlencode({
        'action': 'dedenik_ai_search', 'nonce': nonce.group(1), 'query': 'Příběhy o psech a kočkách'
    }).encode())
    with urllib.request.urlopen(request, timeout=60, context=context) as response:
        result = json.load(response)
    if not result.get('success'):
        raise RuntimeError('AI search verification failed')
    print('Server key configured; public AI form verified; live search returned ' + str(len(result['data']['results'])) + ' results.')


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('AI configuration failed: ' + type(error).__name__)
        raise SystemExit(1)
