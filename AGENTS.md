# Práce na Dedeníku

Repozitář je `martinrsk/dedenik-mock`. Začni příkazem `git fetch origin` a pracuj na větvi `test`. Existující historii `test` zachovej. Upravuj aktuální šablonu v `wp-content/themes/dedenik/`. Změny otestuj a pošli na `test`, kde kontrola a zapnuté automatické nasazení obsluhují [Svět Dede](https://svetdede.dedenik.cz/).

Počkej na úspěšné nasazení a ověř změnu na dotčené stránce Světa Dede. Zaznamenej URL, výsledek a úplné SHA nasazeného commitu. Požádej zadavatele o výslovné schválení právě tohoto SHA. Jakýkoli nový commit na `test` před sloučením vyžaduje nové ověření a schválení.

Po schválení otevři PR výhradně ze stejného repozitáře z `test` do `main`. Do popisu vlož URL, výsledek ověření a samostatný řádek `Approved-Theme-Commit: <úplné SHA>`. Po úspěšných kontrolách použij merge commit a podmínku `gh pr merge --merge --match-head-commit <schválené SHA>`. Do `main` přímo neposílej změny. Po sloučení spusť produkční workflow pro `main` s číslem tohoto PR podle [AUTODEPLOY.md](wordpress/AUTODEPLOY.md). Zkontroluj výsledek Actions i dotčenou stránku na [Dedeníku](https://dedenik.cz/).

Nasazení v tomto postupu zahrnuje provozní soubory šablony. Databáze, obsah, média, WordPress jádro, pluginy a `wp-config.php` patří mimo tento postup.
