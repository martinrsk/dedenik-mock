import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

script = Path(__file__).resolve().parents[1] / 'deploy-svetdede.py'
spec = importlib.util.spec_from_file_location('deploy_svetdede', script)
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)


class DeploymentBoundaries(unittest.TestCase):
    def test_target_is_only_the_test_theme(self):
        self.assertEqual(deploy.REMOTE, '/public_html/svetdede/wp-content/themes/dedenik')
        self.assertEqual(deploy.SITE, 'https://svetdede.dedenik.cz')

    def test_real_payload_contains_no_config_or_data(self):
        self.assertEqual(deploy.SOURCE, Path(__file__).resolve().parents[2] / 'wp-content/themes/dedenik')
        files = deploy.source_files()
        self.assertTrue(files)
        self.assertFalse(any(p.name == 'wp-config.php' or p.suffix in {'.sql', '.gz', '.md'} for p in files))

    def test_production_requires_a_distinct_theme_directory(self):
        for path in ('', deploy.REMOTE, '/public_html/svetdede/new/wp-content/themes/dedenik',
                     '/public_html/site/../wp-content/themes/dedenik', '/public_html/site'):
            with self.subTest(path=path), patch.dict(deploy.os.environ, {'DEDENIK_FTP_THEME_PATH': path}):
                with self.assertRaises(deploy.DeploymentError):
                    deploy.production_directory()

    def test_sensitive_file_is_rejected(self):
        with tempfile.TemporaryDirectory(dir=Path(__file__).parent) as folder:
            root = Path(folder)
            for name in ['style.css', 'functions.php', 'wp-config.php']:
                (root / name).write_text('test')
            with self.assertRaises(ValueError):
                deploy.source_files(root)

    def test_dry_run_never_uploads_or_renames(self):
        with patch.dict(deploy.os.environ, {'SVETDEDE_FTP_HOST':'ftp.example.invalid', 'SVETDEDE_FTP_USER':'test', 'SVETDEDE_FTP_PASSWORD':'test'}):
            with patch.object(deploy.ftplib, 'FTP_TLS') as factory:
                ftp = factory.return_value.__enter__.return_value
                ftp.retrbinary.side_effect = lambda cmd, writer: writer('Theme Name: Dedeník\n'.encode())
                deploy.deploy(apply=False)
                ftp.storbinary.assert_not_called()
                ftp.rename.assert_not_called()
                ftp.delete.assert_not_called()
                ftp.mkd.assert_not_called()

    def test_production_dry_run_checks_public_stylesheet_without_writes(self):
        path = '/example/production/wp-content/themes/dedenik'
        css = 'Theme Name: Dedeník\n'.encode()
        settings = {'DEDENIK_FTP_THEME_PATH': path, 'DEDENIK_FTP_HOST': 'ftp.example.invalid',
                    'DEDENIK_FTP_USER': 'production-user', 'DEDENIK_FTP_PASSWORD': 'test'}
        with patch.dict(deploy.os.environ, settings), patch.object(deploy.ftplib, 'FTP_TLS') as factory, \
                patch.object(deploy, 'public_stylesheet', return_value=css) as public:
            ftp = factory.return_value.__enter__.return_value
            ftp.retrbinary.side_effect = lambda cmd, writer: writer(css)
            deploy.deploy(target='production')
            ftp.cwd.assert_called_once_with(path)
            self.assertEqual(public.call_args.args[0], 'https://dedenik.cz')
            ftp.storbinary.assert_not_called()
            ftp.rename.assert_not_called()
            ftp.delete.assert_not_called()
            ftp.mkd.assert_not_called()
            public.return_value = b'different stylesheet'
            with self.assertRaises(deploy.DeploymentError):
                deploy.deploy(apply=True, target='production')
            ftp.storbinary.assert_not_called()


if __name__ == '__main__':
    unittest.main()
