# Autodeploy šablony na Svět Dede

GitHub Actions workflow **Check theme and deploy Svet Dede** kontroluje PHP, testy a vytvoření ZIPu. Při zapnutém autodeployi po pushi do `main` přenese pouze runtime soubory šablony přes FTPS s kontrolou certifikátu. Nakonec ověří verzi CSS a dostupnost homepage přes HTTPS.

Pevný cíl je `/public_html/svetdede/wp-content/themes/dedenik`. Skript nepřijímá alternativní vzdálenou cestu, nepřipojuje se k databázi a nevyměňuje WordPress jádro, uploads ani wp-config.php. Žádné soubory na serveru nemaže. Před prvním přenosem ověří, že v cíli už je šablona Dedeník. Staré soubory, které budou z Gitu později odstraněné, se tímto způsobem automaticky nemažou.

## První zapnutí

V repozitáři **PN99/dedenik-mock** otevřete **Settings → Secrets and variables → Actions**. Přidejte tyto repository secrets:

| Secret | Hodnota |
| --- | --- |
| `SVETDEDE_FTP_HOST` | Hostname FTP serveru z Webglobe, bez `ftp://`, portu a cesty |
| `SVETDEDE_FTP_USER` | Uživatelské jméno stávajícího FTP účtu |
| `SVETDEDE_FTP_PASSWORD` | Heslo FTP účtu, nikoli heslo databáze nebo wp-adminu |

Volitelná repository variable `SVETDEDE_FTP_PORT` určuje port; výchozí je 21 pro explicitní FTPS. Použitý FTP účet musí vidět cestu `/public_html/svetdede/wp-content/themes/dedenik`, jako účet na dosavadním screenshotu Total Commanderu. Pokud má účet jiný kořen, je třeba cestu nejprve ověřit a skript upravit; nezapínejte nasazení naslepo.

1. V **Actions → Check theme and deploy Svet Dede → Run workflow** vyberte `main` a ponechte **dry_run** zapnutý. Tento běh ověří připojení a existující šablonu, nic nenahraje.
2. Po úspěchu spusťte jednou stejné workflow s **dry_run** vypnutým. To provede první nasazení a ověření webu.
3. Teprve potom přidejte repository variable **`SVETDEDE_AUTO_DEPLOY`** s hodnotou **`true`**. Další změny `wordpress/**` v `main` se nasadí automaticky. Chybějící proměnná nebo hodnota jiná než `true` automatické nasazení vypíná.

Není potřeba ukládat DB heslo ani přihlašovací údaje WordPressu. FTP heslo nedávejte do souborů, commitů ani do textu workflow. GitHub environment `svetdede` eviduje cíl nasazení. Kontroly běží i pro pull requesty, nasazení jen z `main`. Souběžné běhy se řadí za sebe.

## Certifikát FTP a selhání

FTP server musí podporovat explicitní TLS. Skript nikdy nepřejde na nešifrované FTP a nevypíná ověřování certifikátu. Když alias `ftp.dedenik.cz` neodpovídá certifikátu, použijte platný serverový hostname uvedený Webglobe. Logy záměrně nevypisují FTP přihlašovací údaje ani celé odpovědi serveru.

Při zprovoznění 20. října 2026 byl pro tento hosting ověřen **`dw132.nameserver.sk`**: DNS vede na stejný server jako `ftp.dedenik.cz`, ale tento název odpovídá jeho TLS certifikátu. Secret `SVETDEDE_FTP_HOST` byl upraven na ověřený název. Přihlášení stávajícím FTP účtem a čtení cílové šablony prošlo dry runem.

FTP nasazení není atomická výměna celé šablony. Soubory se nejprve nahrají do dočasného souboru a potom přejmenují, závislosti před vstupními soubory. Při chybě se běh zastaví, část souborů může být už aktualizovaná. Pro návrat vraťte příslušný commit v Gitu a znovu spusťte deployment. Nepoužívejte force push na sdílenou `main`.

Dokumentace: [GitHub deployment workflow](https://docs.github.com/en/actions/how-tos/deploy/configure-and-manage-deployments/control-deployments), [GitHub secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets).
