# Vizuální kontrola homepage – 24. 10. 2026

Porovnáno v prohlížeči: původní HTML mock a skutečný lokální WordPress s obnovenou databází. Změny jsou lokální, nebyly nasazeny na svetdede.dedenik.cz.

## Rozdělení bloků

1. Hero a Chatník.
2. Čtyři nejnovější články v jedné desktopové řadě, odkaz na další stránku u nadpisu.
3. Kompaktní pruh Ze Světa Dede.
4. Témata vlevo (čtyři sloupce), autoři a Objevte Dedeník vpravo; poměr 1,45 : 1.
5. Samostatný řádek hledání vlevo a archivního článku vpravo.
6. Původní komunitní widgety přístupné pod rozbalovacím řádkem, potom krajina a patička.

## Naměřené výšky při viewportu 1440 px

| Blok | Mock | WordPress |
| --- | ---: | ---: |
| Nejnovější články | 505 px | 548 px |
| Ze Světa Dede | 299 px | 299 px |
| Témata + autoři | 594 px | 517 px |
| Hledání + archiv | 348 px | 356 px |

Shoda výšky Světa Dede neznamená shodu obsahu: lokálně je nakonfigurována pouze karta knih. Mock má tři karty. Delší skutečné nadpisy článků zvětšují první blok; nejsou ořezané. Témata a autoři jsou nižší zejména kvůli dvěma autorům a menšímu počtu dostupných nástrojů.

## Zbývající rozdíly a jejich příčiny

- Podcast a Dedivadlo: chybí přiřazení cílových stránek/URL pro homepage karty v nastavení šablony. Layout i obrázky pro tři karty jsou připravené; nejde o omezení WordPressu.
- Autoři: publikující účty jsou Dede a Finrod Felagund. Matylda, EvaB a další jména z mocku potřebují vlastní vazbu na články; nelze je vydávat za existující WP autorské archivy.
- Témata: mock sdružuje obsah do redakčních témat, WP nyní ukazuje existující rubriky podle počtu příspěvků. Shodné názvy a pořadí vyžadují mapování témat na rubriky, nikoli změnu CSS.
- Náhodný článek a oblíbené: homepage nyní nemá jejich funkční implementaci. Mock používá ukázková data a lokální interakce. Odkazy bez funkce nejsou přidané.
- Archiv: zobrazuje skutečný veřejný nechráněný článek starší než rok, nikoli ukázkový příběh z mocku. Nadpis proto neslibuje přesné výročí.
- AI: lokální kopie má AI vypnutou a ukazuje běžné hledání. Při zapnuté AI se vykreslí existující AI formulář. Jeho skutečný provoz v této kontrole nebyl testován.
- Původní widgety: zachované pod rozbalovacím řádkem; mock je neobsahuje.
- Hlavička, hero, obsah článků a patička se stále liší obsahem a dílčí typografií. Není deklarována pixelová shoda celé stránky.

## Ověření

- Desktop 1440 px, mobil 390 px: vizuální kontrola; na mobilu témata ve dvou sloupcích, hlavní bloky pod sebou, bez horizontálního přetékání.
- Všech 13 obrázků rubrik na mobilu načteno.
- Odkaz z nástrojů na hledání vede na samostatný blok.
- První dvě stránky mají po čtyřech příspěvcích a žádnou duplicitu: 37077, 37062, 37058, 37056 / 37053, 37050, 37030, 37042.
- 18 offline behaviorálních kontrol prošlo. Kontrola PHP syntaxe upravených souborů prošla.
- Screenshoty jsou mimo repozitář v analysis/preview: blocks-mock-desktop.jpg, blocks-wp-1440.jpg, blocks-desktop-detail.jpg, blocks-mobile-topics.jpg.
