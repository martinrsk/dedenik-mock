# Práce na Dedeníku

V repozitáři `PN99/dedenik-mock` začíná každá úprava na větvi odvozené z `main`. Na `main` i `production` má šablona stejnou cestu `wp-content/themes/dedenik/`. Vlastní automaticky načítané pluginy WordPressu patří do `wp-content/mu-plugins/`.

| Větev | Web | Co nasazují GitHub Actions |
| --- | --- | --- |
| `main` | [Svět Dede](https://svetdede.dedenik.cz/) | Provozní soubory šablony Dedeník |
| `production` | [Veřejný náhled Dedeníku](https://dedenik.cz/new/) | Změněné provozní soubory šablony a MU pluginů do `/public_html/www/new/` |

## 1. Připrav změnu do main

Pro první práci naklonuj repozitář. V existujícím klonu začni příkazem `git fetch origin` a vytvoř novou pracovní větev až po uložení své rozpracované práce.

```sh
git clone https://github.com/PN99/dedenik-mock.git
cd dedenik-mock
git fetch origin
git switch -c uprava/nazev-zmeny origin/main
```

Uprav soubory, spusť příslušné testy a zkontroluj rozdíly pomocí `git diff`. Po přidání vybraných souborů a vytvoření commitu odešli pracovní větev a na GitHubu otevři pull request.

```sh
git push -u origin HEAD
gh pr create --base main --head uprava/nazev-zmeny --fill
```

## 2. Ověř změnu na Světě Dede

Po sloučení pull requestu do `main` otevři v Actions běh **Check theme and deploy Svet Dede**. U změny šablony počkej na úspěšný přenos. Na dotčené stránce Světa Dede vyzkoušej požadované chování a poznamenej si URL a plné SHA ověřeného commitu na `main`. Změny určené jen pro náhled, například jeho MU plugin, otestuj před vydáním lokálně. [Svět Dede](https://svetdede.dedenik.cz/)

## 3. Vydej ověřenou main do production

Pro vydání otevři nový pull request přímo z větve **`main`** do **`production`**. V GitHubu vyber **base: production** a **compare: main**. Pull request zahrnuje všechny dosud nevydané změny z `main`. Prohlédni celý rozdíl a do popisu přidej testovanou URL, výsledek a SHA. Pokud mezitím přibyl commit na `main`, ověř novou verzi na Světě Dede.

```sh
gh pr create --base production --head main --title "Vydání do /new" --body "Ověřeno na Světě Dede: doplň URL, výsledek a SHA."
```

Po úspěšných kontrolách vyber na GitHubu **Create a merge commit**. Tím se v historii obou větví zachová stejný vydaný commit. Do `production` se změny dostávají výhradně sloučením tohoto pull requestu. Podrobnosti v Actions ověř pro výsledný merge commit. V běhu **Dedeník /new** zkontroluj porovnání s hostingem, přenos přes FTPS a výsledek kontroly přes HTTPS. Otevři dotčenou stránku na veřejném náhledu a znovu vyzkoušej změněnou funkci. [Dedeník /new](https://dedenik.cz/new/)

Při rozdílu mezi Gitem a souborem na hostingu nejdřív prověř ruční změnu na serveru. Po přerušení přenosu zopakuj původní běh přes **Re-run failed jobs**, aby dokončil stejnou revizi.

## Soubory na hostingu

Každá instalace má vlastní `wp-config.php`. Databázová hesla, média v `wp-content/uploads/`, databáze a zálohy zůstávají mimo Git. Přístupy k FTPS spravuje GitHub Secrets. Náhled v `/new` čte současnou produkční databázi. Nasazení jádra, běžných pluginů a změny serverového nastavení včetně konečného přepnutí hlavní domény mají samostatný postup.

## Zadání pro kolegu nebo agenta

> Přečti AGENTS.md. Z aktuální origin/main založ pracovní větev, uprav [požadavek], ověř [očekávané chování] a připrav PR do main. Po sloučení ověř [konkrétní stránku a krok] na Světě Dede. Pro vydání otevři PR z main do production a připoj testovanou URL, výsledek a SHA. Po vydání zkontroluj běh Dedeník /new a stejný krok na veřejném náhledu.
