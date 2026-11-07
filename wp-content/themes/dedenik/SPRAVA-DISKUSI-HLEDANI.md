# Hledání v diskusích (0.6.0)

Kliknutím na jméno v diskusi se otevře [přehled komentářů se stejným jménem a e-mailem](SPRAVA-DISKUTUJICICH.md). Přehled používá přímo veřejné komentáře WordPressu. Vyhledávání podle tématu popsané níže používá vlastní index.

Vedle článků se indexují veřejné schválené komentáře u publikovaných článků/stránek bez hesla. Pingbacky, trackbacky, čekající, spamové a smazané komentáře se vylučují. Samostatná tabulka s prefixem WordPressu `dedenik_search_comments` obsahuje pouze ID komentáře, ID článku/rodičovského komentáře, veřejnou přezdívku a její normalizovanou podobu, vyčištěný text a datum. Má FULLTEXT nad textem a běžné indexy článku, rodiče, přezdívky a data. Nemá pole pro e-mail, IP adresu nebo ID uživatelského účtu.

## Použití

- `Kdy mluvila YGA o koních?` hledá komentáře přezdívky YGA na dané téma.
- `Co psala Matylda o psech?` obdobně filtruje diskutujícího a téma.
- `O čem jsme se bavili v diskusi v lednu 2024?` může omezit výpis daty. Bez tématu, přezdívky nebo období nemusí být dotaz dost určitý.

Dotazy o diskusi se vracejí jako stránkované konkrétní komentáře po 12, s veřejným jménem, datem, úryvkem a názvem článku. Odkaz používá nativní WordPress `get_comment_link()`, takže respektuje stránkování a vlákna a vede na kotvu konkrétního komentáře. Přezdívka označuje veřejné jméno příspěvku, nikoli ověřenou totožnost. Automatické slučování různých přezdívek nebo soukromé identifikační údaje se nepoužívají.

Gemini při cíleném dotazu do diskusí dostává pouze samotný dotaz. Převede téma na české výrazy/kmeny a rozpozná výslovně uvedené jméno a případné datum. Model nesmí zavést jméno, které v dotazu není. SQL vytvoří pouze serverový kód s připravenými parametry, nikdy model. Při stránkování se znovu používá plán dotazu, bez nového volání Googlu. Výsledky jsou chronologicky od nejnovějších; jde o nalezené doklady, nikoli vygenerované shrnutí celé debaty.

U obecných tematických dotazů se k článkům přidávají i kandidáti z komentářů. Google může k seřazení dostat nejvýše 32 aktuálně veřejných záznamů s krátkými úryvky a veřejnými jmény. Formulář na toto zpracování upozorňuje. Všechny komentáře se hromadně do Googlu neposílají. FULLTEXT a rozšíření výrazů nejsou úplný sémantický index; výsledky mohou být neúplné. Běžné české tvary slova kůň jsou vyhledávané jako celá slova, aby se nepletly s výrazy konec, kontakt či koncert.

## Indexace a moderace

První naplnění a opakovanou obnovu spustí GitHub Actions **Build staging discussion index**. Používá stejný krátkodobý autentizovaný worker jako index článků, pevný testovací cíl a dávky po 750 veřejných komentářích. Zpracovává se všechen dostupný archiv, bez API volání a bez zásahu do zdrojových komentářů. Počet řádků se porovná se zdrojem a zkontroluje se úplnost i vyloučení neveřejného obsahu; pracovní soubor se odstraní. Dokud není index hotový, hledání zůstává u článků.

Nové komentáře, úpravy, schválení, odebrání schválení, spam, koš a trvalé smazání se průběžně promítají do indexu. Zneveřejnění nebo zaheslování článku vyloučí i jeho diskusi; při opětovném zveřejnění se index doplní. Přímé importy SQL a změna šablony vyžadují obnovu. Při každém vyhledávání i zobrazení ID z cache se znovu kontroluje aktuální schválení komentáře a veřejnost jeho článku. Stará cache proto nemůže zpřístupnit nově skrytý komentář. Změny komentářů také zneplatní mezipaměť hledání.

Stav je v **Nastavení → Chytré hledání**. Lokální test nad celým archivem: `php -c local/php.ini mockup/wordpress/tests/discussion-index-integration.php C:/Projekty/Dedenik/local/site/wp-load.php --build-archive`. Nevolá Google; ověřuje i moderaci, stránkování, datumové filtry, přímé odkazy a ochranu soukromých polí.

Nativní chování odkazů: [WordPress get_comment_link](https://developer.wordpress.org/reference/functions/get_comment_link/). Moderace: [transition_comment_status](https://developer.wordpress.org/reference/hooks/transition_comment_status/).
