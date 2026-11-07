# Přehled komentářů diskutujícího

Kliknutím na jméno pod článkem se otevře seznam veřejných komentářů se stejným jménem a e-mailem jako u vybraného příspěvku. Když Jana e-mail nevyplnila, zobrazí se příspěvky od Jany s prázdným e-mailem. Komentáře od Jany s vyplněnou adresou patří do samostatného seznamu. Dva lidé se stejným jménem a prázdným e-mailem budou ve společném seznamu.

Velikost písmen při porovnání nerozhoduje. Diakritika i znaky jako `%` nebo podtržítko jsou součástí jména.

Odkaz obsahuje jen veřejné ID výchozího komentáře, například `/?disk_komentar=123`. E-mail se porovnává uvnitř databáze. V adrese stránky ani ve výpisu se nezobrazuje. Přehled funguje i na náhledu v `/new` s databázovým účtem pro čtení. Původní sdílené odkazy `?diskutujici=Jana` si zachovávají svůj význam a používají původní index přezdívek.

Na každé stránce je až 20 komentářů od nejnovějších, s datem, názvem článku, úryvkem a odkazem na příspěvek v diskusi. Zahrnují se schválené běžné komentáře u veřejných článků a stránek bez hesla. Pokud výchozí komentář přestane být veřejný, jeho odkaz na přehled vrátí HTTP 404.
