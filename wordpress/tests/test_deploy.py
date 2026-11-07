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


if __name__ == '__main__':
    unittest.main()
