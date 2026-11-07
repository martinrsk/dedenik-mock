import importlib.util
import io
from pathlib import Path
import tempfile
import unittest
from unittest.mock import MagicMock, patch
from urllib.parse import urlsplit


script = Path(__file__).resolve().parents[1] / 'deploy-design-system.py'
spec = importlib.util.spec_from_file_location('deploy_design_system', script)
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)


class DesignSystemDeployment(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(dir=Path(__file__).resolve().parent)
        self.addCleanup(self.temporary.cleanup)
        self.source = Path(self.temporary.name)
        for name in deploy.PUBLIC_FILES:
            content = deploy.MARKER if name == 'index.html' else 'public ' + name
            (self.source / name).write_text(content, encoding='utf-8')
        self.environment = patch.dict(deploy.os.environ, {
            'SVETDEDE_FTP_HOST': 'ftp.example.invalid',
            'SVETDEDE_FTP_USER': 'test',
            'SVETDEDE_FTP_PASSWORD': 'test',
            'SVETDEDE_FTP_PORT': '21',
        })
        self.environment.start()
        self.addCleanup(self.environment.stop)

    def ftp(self, exists=True, marker=True, theme=True):
        ftp = MagicMock()
        ftp.nlst.side_effect = [['design-system'], ['index.html']] if exists else [[]]
        def read(command, writer):
            if command == 'RETR style.css':
                writer(('Theme Name: Dedeník\n' if theme else 'Theme Name: Other\n').encode('utf-8'))
            elif command == 'RETR index.html':
                writer((deploy.MARKER if marker else 'Another project').encode('utf-8'))
            else:
                raise AssertionError('Unexpected remote read')
        ftp.retrbinary.side_effect = read
        return ftp

    def run_deploy(self, ftp, apply=False):
        with patch.object(deploy.ftplib, 'FTP_TLS') as factory:
            factory.return_value.__enter__.return_value = ftp
            deploy.deploy(apply=apply, source=self.source)

    def test_fixed_target_and_exact_public_allowlist(self):
        self.assertEqual(deploy.REMOTE, '/public_html/svetdede/design-system')
        self.assertEqual(deploy.SITE, 'https://svetdede.dedenik.cz/design-system/')
        self.assertEqual(set(deploy.PUBLIC_FILES), {
            'index.html', 'styles.css', 'app.js', 'tokens.css',
            'tokens.json', 'system.json', 'README.md', 'CHANGELOG.md',
        })
        self.assertEqual(deploy.PUBLIC_FILES[-1], 'index.html')

    def test_template_is_not_uploaded(self):
        (self.source / 'index.template.html').write_text('source only', encoding='utf-8')
        self.assertEqual(len(deploy.source_files(self.source)), 8)
        self.assertNotIn('index.template.html', [p.name for p in deploy.source_files(self.source)])

    def test_private_or_unexpected_files_are_rejected(self):
        for name in ['.env', 'wp-config.php', 'dump.sql', 'backup.zip', 'extra.html']:
            with self.subTest(name=name):
                extra = self.source / name
                extra.write_text('not public', encoding='utf-8')
                with self.assertRaises(ValueError):
                    deploy.source_files(self.source)
                extra.unlink()

    def test_missing_public_file_is_rejected(self):
        (self.source / 'tokens.json').unlink()
        with self.assertRaises(ValueError):
            deploy.source_files(self.source)

    def test_nested_directory_is_rejected(self):
        (self.source / 'private').mkdir()
        with self.assertRaises(ValueError):
            deploy.source_files(self.source)

    def test_symlink_is_rejected(self):
        link = self.source / 'index.template.html'
        try:
            link.symlink_to(self.source / 'index.html')
        except (OSError, NotImplementedError):
            self.skipTest('This platform cannot create symbolic links')
        with self.assertRaises(ValueError):
            deploy.source_files(self.source)

    def test_unrecognized_local_entry_page_is_rejected(self):
        (self.source / 'index.html').write_text('Another project', encoding='utf-8')
        with self.assertRaises(ValueError):
            deploy.source_files(self.source)

    def test_dry_run_makes_no_remote_writes(self):
        for exists in [True, False]:
            with self.subTest(exists=exists):
                ftp = self.ftp(exists=exists)
                with patch.object(deploy, 'verify_public_files') as verify:
                    self.run_deploy(ftp)
                for name in ['mkd', 'storbinary', 'rename', 'delete', 'rmd']:
                    getattr(ftp, name).assert_not_called()
                verify.assert_not_called()

    def test_unrecognized_remote_entry_blocks_upload(self):
        ftp = self.ftp(marker=False)
        with self.assertRaises(ValueError):
            self.run_deploy(ftp, apply=True)
        ftp.storbinary.assert_not_called()
        ftp.mkd.assert_not_called()

    def test_wrong_site_identity_blocks_upload(self):
        ftp = self.ftp(theme=False)
        with self.assertRaises(ValueError):
            self.run_deploy(ftp, apply=True)
        ftp.nlst.assert_not_called()
        ftp.storbinary.assert_not_called()
        ftp.mkd.assert_not_called()

    def test_apply_creates_only_fixed_folder_and_uploads_entry_last(self):
        ftp = self.ftp(exists=False)
        with patch.object(deploy, 'verify_public_files') as verify:
            self.run_deploy(ftp, apply=True)
        ftp.mkd.assert_called_once_with('design-system')
        self.assertEqual(ftp.storbinary.call_count, 8)
        self.assertEqual([call.args[1] for call in ftp.rename.call_args_list], list(deploy.PUBLIC_FILES))
        self.assertTrue(all(call.args[0].startswith('STOR .deploy-') for call in ftp.storbinary.call_args_list))
        ftp.delete.assert_not_called()
        ftp.rmd.assert_not_called()
        verify.assert_called_once()

    def test_existing_directory_is_not_created_again(self):
        ftp = self.ftp()
        with patch.object(deploy, 'verify_public_files'):
            self.run_deploy(ftp, apply=True)
        ftp.mkd.assert_not_called()

    def test_every_public_file_is_verified_byte_for_byte(self):
        def response(request, **kwargs):
            self.assertTrue(request.full_url.startswith(deploy.SITE))
            name = Path(urlsplit(request.full_url).path).name
            return io.BytesIO((self.source / name).read_bytes())
        with patch.object(deploy.urllib.request, 'urlopen', side_effect=response) as request:
            deploy.verify_public_files(deploy.source_files(self.source), 'test', object())
        self.assertEqual(request.call_count, 8)

    def test_public_hash_mismatch_fails_verification(self):
        with patch.object(deploy.urllib.request, 'urlopen', return_value=io.BytesIO(b'wrong asset')):
            with self.assertRaises(RuntimeError):
                deploy.verify_public_files(deploy.source_files(self.source), 'test', object())


if __name__ == '__main__':
    unittest.main()
