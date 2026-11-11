# Práce na Dedeníku

## Pracovní kopie

Společný repozitář je [martinrsk/dedenik-mock](https://github.com/martinrsk/dedenik-mock). Na Petrově počítači se pracuje v `C:\Projekty\Dedenik\production` na větvi `test`. Na jiném počítači si klon vytvoř ve zvolené složce.

```sh
git clone --branch test https://github.com/martinrsk/dedenik-mock.git
cd dedenik-mock
```

V existujícím klonu zkontroluj `git status -sb` a `git remote get-url origin`. Po uložení případné rozpracované práce aktualizuj místní `test` pomocí `git fetch origin`, `git switch test` a `git merge --ff-only origin/test`.

## Úpravy a vydání

Upravuj šablonu v `wp-content/themes/dedenik/`. Změny otestuj a pošli na `test` se zachováním historie větve. Po úspěšných kontrolách nasadí workflow změněnou šablonu na [Svět Dede](https://svetdede.dedenik.cz/), pokud je proměnná repozitáře `DEDENIK_TEST_AUTO_DEPLOY=true`.

Počkej na úspěšné nasazení a ověř změnu na dotčené stránce Světa Dede. Zaznamenej URL, výsledek a úplné SHA nasazeného commitu. Požádej zadavatele o výslovné schválení právě tohoto SHA. Jakýkoli nový commit na `test` před sloučením vyžaduje nové ověření a schválení.

Po schválení otevři PR výhradně ze stejného repozitáře z `test` do `main`. Do popisu vlož URL, výsledek ověření a samostatný řádek `Approved-Theme-Commit: <úplné SHA>`. Po úspěšných kontrolách použij merge commit a podmínku `gh pr merge --merge --match-head-commit <schválené SHA>`. Do `main` přímo neposílej změny. Po sloučení spusť produkční workflow pro `main` s číslem tohoto PR podle [AUTODEPLOY.md](wordpress/AUTODEPLOY.md). Zkontroluj výsledek Actions i dotčenou stránku na [Dedeníku](https://dedenik.cz/).

Nasazení v tomto postupu zahrnuje provozní soubory šablony. Databáze, obsah, média, WordPress jádro, pluginy a `wp-config.php` patří mimo tento postup.

## Přístup k produkci

Dodaný `.env1` patří do kořene místního klonu vedle tohoto souboru. Obsahuje `DEDENIK_FTP_HOST`, `DEDENIK_FTP_USER` a `DEDENIK_FTP_PASSWORD`. Před prací ověř pomocí `git check-ignore -v -- .env1`, že ho Git ignoruje. Hodnoty uchovávej pouze lokálně a v secrets prostředí `dedenik-production`. Při změně přístupu aktualizuj secrets v tomto prostředí a spusť produkční test. Běžné vydání čte údaje přímo z GitHubu.

Produkční FTPS používá port `21` a cestu `/public_html/www/new/wp-content/themes/dedenik`. Tuto instalaci obsluhuje veřejná doména `https://dedenik.cz/`. Proměnná repozitáře `DEDENIK_PRODUCTION_DEPLOY=true` povoluje ručně spuštěné vydání. V příkazech použij pro `CISLO_PR` číslo právě sloučeného PR z `test` do `main`.

```sh
gh workflow run svetdede.yml --repo martinrsk/dedenik-mock --ref main -f dry_run=true
gh workflow run svetdede.yml --repo martinrsk/dedenik-mock --ref main -f dry_run=false -f release_pr=CISLO_PR
```

První příkaz ověří přihlášení a shodu souboru `style.css` přes FTPS a HTTPS. Druhý spouštěj pro schválené vydání. V jeho výsledku ověř úspěch obou kroků `Verify approved production commit` a `FTPS for dedenik.cz`, poté zkontroluj dotčenou stránku. Obsah obou vzdálených větví po `git fetch origin` porovnáš příkazem `git diff --exit-code origin/main origin/test` (návratový kód 0 znamená shodu).

## Předání po vydání 11. listopadu 2026

Na produkci je šablona `0.6.20`. U náhledů rubrik vedle článku používá poměr 2,65 : 1 a načítá širší zdrojový obrázek tam, kde ho knihovna médií nabízí. Oprava platí i pro položky načtené přes „Zobrazit další“. Ověřený commit na `test` byl `caee5d786bc1e28d569abbb5685c70a39b8749d3`; na produkci se nasadil merge commit `b5f76deaac36a55eb41813e11ec01c0bf4399649`. Workflow přeneslo 63 souborů a ověřilo veřejný styl i titulní stránku. [PR #23](https://github.com/martinrsk/dedenik-mock/pull/23) · [Produkční běh](https://github.com/martinrsk/dedenik-mock/actions/runs/43033346996)

Na článku „BTW – MartinR: Můžu za to já!“ měl náhled při šířce okna 1440 px rozměry 196 × 74 px a při 390 px rozměry 280 × 106 px. V obou případech prohlížeč načetl logo 768 × 290 px. Tlačítko „Zobrazit další“ rozšířilo seznam z pěti na deset položek a nový náhled zachoval stejný poměr. Na uvedeném testovacím článku widget při vydání nebyl aktivní. [Produkční článek](https://dedenik.cz/2026/11/09/btw-martinr-muzu-za-to-ja/) · [Testovací článek](https://svetdede.dedenik.cz/2026/10/30/chatnik-aneb-dejme-tema-do-diskuze-221/)
