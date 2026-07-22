# Bukovina Planner – serverový systém

Požiadavky: PHP 8.1+, zapisovateľný priečinok `data/`, Apache alebo nginx s blokovaním verejného prístupu do `data`.

## Prvé spustenie

Po nahratí súborov otvorte:

```text
https://tvoja-domena.sk/install/
```

Inštalačný sprievodca:

- overí PHP 8.1 alebo novšie,
- overí zapisovateľnosť `data`, `data/projects` a `data/versions`,
- upozorní, ak web nepoužíva HTTPS,
- vytvorí prvý administrátorský účet s vlastným e-mailom a heslom,
- vytvorí `data/install.lock` a po dokončení sa automaticky uzamkne.

Systém už nepoužíva verejne známe predvolené prihlasovacie údaje. Ak administrácia nenájde žiadny účet, automaticky presmeruje na inštalátor.

Administrácia je dostupná cez `/admin/`.

Klientský editor používa `?token=...`, zdieľaný režim `?share=...`. Share token nemá na API právo zápisu. Projekty sa ukladajú samostatne v `data/projects`, odoslané verzie v `data/versions`.

## Zálohovanie a obnova

Kompletnú zálohu je možné stiahnuť cez **Administrácia → Nastavenia → Záloha systému**. Výsledkom je jeden JSON súbor obsahujúci:

- všetky aktuálne projekty,
- všetky odoslané verzie,
- administrátorské účty a hashované heslá.

SMTP prihlasovacie údaje v zálohe nie sú, pretože sa načítavajú z prostredia servera.

Pri obnove systém najprv overí formát a obsah súboru. Pred samotným importom automaticky uloží poistnú kópiu aktuálnych dát do `data/backups`. Uchováva sa posledných 10 poistných kópií. Obnova môže projekty zlúčiť s existujúcimi dátami alebo ich pred importom nahradiť. Obnovenie administrátorských účtov je samostatná voľba.

Maximálna veľkosť importovanej zálohy je 50 MB. Priečinok `data/backups` musí byť rovnako ako celý `data` neprístupný z webu.

## SMTP a e-mailové notifikácie

SMTP heslo sa nesmie zapisovať do `config.php` ani do GitHubu. Nastavte ho ako premenné prostredia PHP-FPM alebo webservera:

```text
BUKOVINA_BASE_URL=https://planovac.example.sk
BUKOVINA_MAIL_FROM=svadby@example.sk
BUKOVINA_MAIL_FROM_NAME=Svadobná sála
BUKOVINA_ORGANIZER_EMAIL=organizator@example.sk
BUKOVINA_SMTP_HOST=smtp.example.sk
BUKOVINA_SMTP_PORT=587
BUKOVINA_SMTP_USER=svadby@example.sk
BUKOVINA_SMTP_PASSWORD=bezpecne-heslo
BUKOVINA_SMTP_ENCRYPTION=tls
```

Povolené hodnoty `BUKOVINA_SMTP_ENCRYPTION` sú `tls`, `ssl` alebo prázdna hodnota pre nešifrované spojenie.

Po nastavení SMTP sa v detaile projektu aktivuje tlačidlo **Odoslať e-mailom**. Klient dostane editačný odkaz. Pri odoslaní hotového návrhu dostane organizátor upozornenie a klient potvrdenie.

Pri nginx/HestiaCP nastavte premenné v PHP-FPM pool konfigurácii alebo v bezpečnom serverovom konfiguračnom súbore mimo web rootu a následne reštartujte PHP-FPM.