# Nasazení šablony Dedeník

Workflow **Check and deploy Dedeník theme** v `.github/workflows/svetdede.yml` kontroluje syntaxi PHP, chování šablony a AI vyhledávání, hranice nasazení a sestavení instalačního ZIPu. Po přesunu šablony v repozitáři je jediným zdrojem přenosu `wp-content/themes/dedenik/`, dříve `wordpress/dedenik/`.

| Větev | Web | Environment | Cílový adresář FTPS |
| --- | --- | --- | --- |
| `test` | [Svět Dede](https://svetdede.dedenik.cz/) | `svetdede` | `/public_html/svetdede/wp-content/themes/dedenik` |
| `main` | [Dedeník](https://dedenik.cz/) | `dedenik-production` | `DEDENIK_FTP_THEME_PATH` podle kořene domény v administraci hostingu |

## Přechod a ověření

1. Ve [Settings → Secrets and variables → Actions](https://github.com/martinrsk/dedenik-mock/settings/secrets/actions) ověř názvy `SVETDEDE_FTP_HOST`, `SVETDEDE_FTP_USER` a `SVETDEDE_FTP_PASSWORD` u repozitáře nebo prostředí `svetdede`. Výchozí port explicitního FTPS je 21. Odlišný port nastav v `SVETDEDE_FTP_PORT`. Použij hostname odpovídající certifikátu. Původní ověření Světa Dede používalo `dw132.nameserver.sk`.
2. V [Actions](https://github.com/martinrsk/dedenik-mock/actions/workflows/svetdede.yml) ručně spusť workflow z `test` s `dry_run=true`. Log má potvrdit TLS, přihlášení, adresář, identitu šablony a počet připravených souborů. Ruční spuštění z `test` dovoluje výhradně režim čtení. Proměnná repozitáře `DEDENIK_TEST_AUTO_DEPLOY=true` povolí zápis až při pozdějším pushi se změnou ve zdrojovém adresáři šablony po úspěšných kontrolách. Založení větve, force push, přidání samotné proměnné a změny dokumentace nebo workflow přenos nespustí.
3. V administraci hostingu ověř kořen obsluhující URL `https://dedenik.cz/` a absolutní cestu k existující šabloně tak, jak ji vidí vyhrazený produkční FTPS účet. Historické nastavení `/public_html/www/new/` popisuje původní instalaci `/new/` a samo o sobě adresář pro hlavní doménu nepotvrzuje.
4. V [Settings → Environments](https://github.com/martinrsk/dedenik-mock/settings/environments) vytvoř `dedenik-production` a před přidáním údajů omez deployment branches na jedinou větev `main`, bez povolených tagů. Použij pravidla ochrany `main` pro změny workflow. Výhradně do tohoto prostředí ulož secrets `DEDENIK_FTP_HOST`, `DEDENIK_FTP_USER` a `DEDENIK_FTP_PASSWORD`. Proměnná prostředí `DEDENIK_FTP_THEME_PATH` musí obsahovat ověřenou absolutní cestu končící `/wp-content/themes/dedenik`. Volitelný `DEDENIK_FTP_PORT` má výchozí hodnotu 21.
5. Produkční ověření spouštěj až po schváleném začlenění workflow do `main`. Ručně vyber větev `main` a `dry_run=true`. Workflow vyžaduje shodu FTPS kopie `style.css` se souborem obsluhovaným přímo z `https://dedenik.cz/`. Shoda doplňuje ověření kořene domény v hostingu. Po ověření a schválení prvního zápisu nastav proměnnou repozitáře `DEDENIK_PRODUCTION_DEPLOY=true`.
6. Při přepnutí cesty vydávání deaktivuj dřívější workflow **Dedeník /new** pro větev `production` v nastavení Actions. Do začlenění nové konfigurace má kopie `svetdede.yml` na `main` stále původní chování. Do té doby ji z `main` nespouštěj s vypnutým `dry_run`.

## Produkční vydání

Postup schválení a sloučení konkrétního commitu je v [AGENTS.md](../AGENTS.md). Po sloučení ručně spusť **Check and deploy Dedeník theme** z aktuální `main` s `dry_run=false` a číslem sloučeného PR v `release_pr`. Workflow ověří úspěšné kontroly, zdroj `test` ze stejného repozitáře, cílovou větev `main`, SHA výsledného sloučení, schválené SHA v popisu PR a shodu stromu šablony mezi schváleným a výsledným commitem. Před přenosem znovu ověří, že výsledný commit je stále na špičce `main`. Samotný push do `main` ani otevření PR produkční zápis nespustí.

FTPS vyžaduje důvěryhodný TLS certifikát i šifrovaný datový kanál. `dry_run` používá jen čtení. Ostrý přenos nahrává provozní soubory šablony do dočasných jmen a přejmenovává je po jednotlivých souborech. Při chybě se běh zastaví a část šablony může být aktualizovaná. Po úspěchu ověří veřejný stylesheet a úvodní stránku přes HTTPS. Staré soubory zůstávají na serveru. Přístupové údaje uchovávej v GitHub Secrets.
