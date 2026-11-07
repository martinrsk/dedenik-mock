# Dedeník / Svět Dede — design system

Verze **1.0.0**, aktualizace **1. listopadu 2026**. Veřejná adresa po nasazení: `/design-system/` na webu Svět Dede.

Tato složka je společný podklad pro designera, vývojáře i agenta. Zachycuje schválená pravidla a jejich vztah k aktuálnímu lokálnímu zdroji šablony. **Samostatné nasazení design systému nemění WordPress šablonu ani články.** Shodu se živým webem je potřeba ověřit při nasazení konkrétní změny.

## Kde začít

| Soubor | Pro koho a k čemu |
| --- | --- |
| [index.html](index.html) | Vizuální prohlížení: pravidla, barvy, typografie, poměry a komponenty. Generovaný soubor. |
| [tokens.json](tokens.json) | Strojově čitelné hodnoty. Každý token má `value`, `css`, `description`, `role` a `status`. |
| [system.json](system.json) | Strojově čitelná pravidla, použití komponent, breakpointy, přístupnost a známé odchylky. |
| [tokens.css](tokens.css) | CSS proměnné vygenerované pro dokumentaci z tokenů. Generovaný soubor. |
| [CHANGELOG.md](CHANGELOG.md) | Důvod a dopad jednotlivých změn. |

Agenti a nástroje typu Claude Design mají začít tímto README a oběma JSON soubory. Číselné hodnoty mají přebírat z tokenů, kontext použití z `system.json`. Screenshot je ilustrace konkrétního rozvržení, nikoli zdroj hodnot.

## Schválená pravidla pro obrázky

| Role | Poměr šířka : výška | Příklad plátna | Způsob zobrazení |
| --- | --- | --- | --- |
| Vodorovná grafika, logo, náhledová plocha, dlaždice rubriky | **2,65 : 1** | 1590 × 600 px | Loga a grafiky s textem celé (`contain`); dekorativní podklady mohou mít `cover`. |
| Svislá grafika | **1 : 2,65** | 600 × 1590 px | Obálky a textové grafiky celé; dekorativní ilustrace podle kompozice. |
| Fotografie v článku | **3 : 2** | 1500 × 1000 px | `cover`, případný ořez s ohledem na motiv. |

Poměr se drží na desktopu i mobilu. Platí pro obrazovou plochu, nikoli pro celou kartu s textem. Avatary, funkční ikony a široká krajina v patičce mají vlastní účel. Žádný obrázek se nesmí deformovat.

V blokovém editoru jsou ve **Obrázek → Styly** formáty `Fotografie 3 : 2`, `Grafika 2,65 : 1` a `Grafika na výšku 1 : 2,65`. Třídy jsou `is-style-dedenik-photo`, `is-style-dedenik-graphic` a `is-style-dedenik-graphic-portrait`. Staré neklasifikované obrázky uvnitř článků zůstávají v původním poměru; neprovádět hromadný ořez bez rozlišení jejich obsahu.

## Jak měnit systém

1. Hodnoty měnit v `tokens.json`, význam a použití v `system.json`. Pole `id` a `css` jsou stabilní rozhraní pro další nástroje.
2. Upravit `version` a `updated`, důvod a dopad zapsat do `CHANGELOG.md`.
3. Z kořene repozitáře `mockup` spustit:

   ```text
   python scripts/build-design-system.py
   ```

4. Prohlédnout vytvořenou stránku a ověřit mobil, klávesnici, celé texty a poměry. `index.html` ani `tokens.css` neopravovat ručně; další sestavení by změny přepsalo.
5. Změnu skutečného webu promítnout samostatně do příslušných zdrojů šablony. Aktualizace dokumentace sama tyto soubory neupravuje.
6. Nasadit požadovaný rozsah a ověřit veřejnou URL. Ostatní rozpracované změny do nasazení nepřibírat bez souvislosti s úlohou.

Při přejmenování nebo odstranění tokenu uvést migraci. Nový vizuální experiment označit jako návrh; neměnit tím bez vysvětlení schválený základ.

Texty a strukturu vizuální stránky upravujte v `design-system/index.template.html`, její vzhled v `styles.css` a chování v `app.js`. Vizuální ukázky i číselné popisky poměrů se při sestavení řídí tokeny. Při změně pravidla aktualizujte také odpovídající popis v `system.json` a tomto README.

Kontrola aktuálnosti bez zápisu: `python scripts/build-design-system.py --check`. Volitelné `--check-theme` porovná implementované tokeny s lokální šablonou; rozdíl proti starší nasazené šabloně je potřeba posoudit samostatně.

Publikaci zajišťuje workflow `.github/workflows/design-system.yml`. Změny této složky v `main` se při zapnutém `SVETDEDE_AUTO_DEPLOY` nasadí samostatně na `https://svetdede.dedenik.cz/design-system/`. Nasazuje se osm veřejných souborů, každý se po přenosu ověřuje přes HTTPS. Ruční spuštění workflow nabízí také kontrolu připojení bez nahrávání. Zdroj HTML šablony a build skripty zůstávají v Gitu.

## Vztah ke skutečné šabloně

- `wp-content/themes/dedenik/assets/css/main.css`: veřejné komponenty a responzivní pravidla.
- `wp-content/themes/dedenik/assets/css/editor.css`: vzhled editoru a tři výslovné styly obrázků.
- `wp-content/themes/dedenik/inc/setup.php`: registrace stylů a nastavení šablony.
- `wp-content/themes/dedenik/template-parts/`: komponenty karet, rubrik a Světa Dede.
- `wp-content/themes/dedenik/header.php`, `footer.php`, `single.php`: hlavička, patička a článek.

Hodnota tokenu se stavem `implemented` byla nalezena v aktuálním lokálním zdroji, nikoli automaticky na produkci. Stav `reference` označuje pojmenovanou referenční hodnotu; starší CSS ji může mít zapsanou přímo. Mezery a poloměry zatím nejsou plošně převedené na tokeny.

## Kontroly a známé hranice

Kontrolní šířky: **320, 390, 738, 960 a 1440 px**. Titulky a názvy rubrik nesmějí být uříznuté, stránka nesmí vodorovně přetékat a obrázky musí držet svůj poměr.

Naměřené kontrasty konkrétních plných barev jsou v `system.json`. Bílá na zelené má 12 : 1; bílá na červené 4,36 : 1 a na zlaté 3,21 : 1. Poslední dvě kombinace jsou evidované odchylky pro běžný drobný text, nikoli doporučení k jejich dalšímu používání. Tato dokumentace není potvrzením kompletního auditu přístupnosti webu.

Malé zdrojové náhledy budou i ve správném poměru neostré. Obálka v odlišném poměru může mít volné okraje. Tyto vlastnosti řeší kvalita a kompozice podkladů, nikoli deformace nebo slepý ořez v CSS.
