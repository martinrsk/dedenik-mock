# Obnova historie komentářů

Nouzová verze 0.6.23 standardně vypíná odkazy na historii diskutujícího a vrací 503 s Retry-After pro přímé požadavky s disk_komentar, disk_profil, diskutujici nebo disk_strana. Běžné komentáře a odpovědi pod články tím nejsou vypnuté. Kontrola proběhne při načtení šablony; WordPress a pluginy se stále načítají. Již běžící procesy tím nejsou ukončeny. Automatické budování indexu historie je během odstávky pozastavené; explicitní dávkový indexer funguje nadále.

## Databáze

1. Spustit `wordpress/schema-audit.sql` v phpMyAdmin samostatně nad testem i produkcí. Uložit všechny čtyři výsledky pro každé prostředí. Dotazy pouze čtou metadata.
2. Porovnat strukturu a indexy s přihlédnutím k prefixům, verzi WP a pluginům. Neimportovat testovací obsah ani celou DB do produkce. Nesjednocovat automaticky nesouvisející tabulky starých pluginů.
3. Pro nový index historie ověřit `<prefix>dedenik_reader_comments`: InnoDB, comment_id bigint unsigned PRIMARY KEY, profile char(64) ascii/ascii_bin NOT NULL, comment_date datetime NOT NULL, index profile_date(profile,comment_date,comment_id). Současné CREATE TABLE IF NOT EXISTS neopravuje existující nesprávnou strukturu. Konkrétní ALTER připravit až podle auditu.
4. V každém prostředí zvlášť ověřit stav kurzoru a existenci platného lokálního klíče (nezveřejňovat jeho hodnotu). Klíče, kurzory ani obsah indexu nekopírovat mezi databázemi. `ready` bez ověření struktury a pokrytí nestačí.
5. Po doplnění schématu indexovat vlastní komentáře prostředí v malých, obnovitelných dávkách pod zámkem. Stávající scripts/build-search-index.py a index-worker.php povolují pouze staging; na produkci je bez úpravy a ověření cíle nepoužívat.
6. Ověřit pokrytí, viditelnost komentářů, stránkování a plán SQL pomocí EXPLAIN. Teprve pak povolit archivy nastavením DEDENIK_READER_ARCHIVES_PAUSED=false před načtením šablony a ověřit zátěž.

Výsledky živého auditu ani migrace zatím nejsou součástí této změny. Záloha produkční DB je podle zadavatele připravená.
