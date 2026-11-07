# WordPress šablona Dedeník

Zdroj šablony je v adresáři [wp-content/themes/dedenik](../wp-content/themes/dedenik/README.md). Kořenový HTML/CSS/JS mockup zůstává samostatnou ukázkou; WordPress jej ani content.js nenačítá.

Vytvoření instalačního ZIP (standardní Python 3, bez dalších knihoven):

```powershell
python wordpress/package-theme.py
```

Instalační ZIP najdete ve složce `dist/` pod názvem `dedenik-<verze>.zip`. Číslo verze se načte z hlavičky šablony.

Ověření (vlastní dostupné PHP):

```powershell
php -n wordpress/tests/theme-behavior.php
Get-ChildItem wp-content/themes/dedenik -Recurse -Filter *.php | ForEach-Object { php -n -l $_.FullName }
```

Offline kontroly nenahrazují integrační test na izolované kopii existujícího WordPressu.
