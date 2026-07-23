# Bukovina Planner – serverový systém

Požiadavky: PHP 8.1+, zapisovateľný priečinok `data/`, Apache alebo nginx s blokovaním verejného prístupu do `data`.

## Prvé spustenie

Po nahratí súborov otvorte:

```text
https://tvoja-domena.sk/install/
```

Inštalačný sprievodca overí PHP, zapisovateľnosť dátových priečinkov a vytvorí prvý administrátorský účet. Po dokončení vytvorí `data/install.lock` a automaticky sa uzamkne. Administrácia je dostupná cez `/admin/`.

Klientský editor používa `?token=...`, zdieľaný režim `?share=...`. Share token nemá na API právo zápisu. Projekty sa ukladajú v `data/projects`, odoslané verzie v `data/versions`.

## E-mailové šablóny

Šablóny sa spravujú cez **Administrácia → E-mailové šablóny** alebo na:

```text
/admin/email-templates.php
```

Je možné upraviť predmet, nadpis, text správy a text tlačidla pre:

- pozvánku klientovi,
- upozornenie organizátorovi po odoslaní návrhu,
- potvrdenie klientovi,
- schválenie návrhu,
- vrátenie návrhu na dopracovanie.

Dostupné premenné:

```text
{{project_name}}
{{client_name}}
{{client_email}}
{{wedding_date}}
{{guest_count}}
{{item_count}}
{{review_note}}
```

Šablóny sú textové a systém ich pri vykreslení bezpečne escapuje. Ukladajú sa do `data/email-templates.json`. Pri chýbajúcom súbore sa automaticky použijú predvolené texty. E-mailové šablóny sú zahrnuté aj v kompletnej systémovej zálohe.

## Zálohovanie a obnova

Kompletnú zálohu je možné stiahnuť cez **Administrácia → Nastavenia → Záloha systému**. Obsahuje aktuálne projekty, odoslané verzie, e-mailové šablóny a administrátorské účty s hashovanými heslami. SMTP prihlasovacie údaje v zálohe nie sú.

## Používatelia a role

Prvý účet vytvorený inštalátorom má rolu **Správca**. Správca môže v nastaveniach pridávať ďalších správcov alebo administrátorov. Novému používateľovi systém odošle dočasné heslo e-mailom a po prvom prihlásení vynúti jeho zmenu.

Rola **Administrátor** môže vytvárať eventy a meniť ich stav. Nemôže vytvárať ani obnovovať systémové zálohy, spravovať používateľov, archivovať eventy alebo ich natrvalo odstraňovať. Tieto obmedzenia sú kontrolované aj na serveri, nie iba skrytím ovládacích prvkov.

Pred obnovou systém vytvorí poistnú kópiu v `data/backups`. Uchováva posledných 10 kópií. Maximálna veľkosť importu je 50 MB. Celý priečinok `data` musí byť neprístupný z webu.

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

Povolené hodnoty `BUKOVINA_SMTP_ENCRYPTION` sú `tls`, `ssl` alebo prázdna hodnota. Po nastavení SMTP sa aktivuje odosielanie pozvánok a automatických upozornení. Pri nginx/HestiaCP nastavte premenné v PHP-FPM pool konfigurácii alebo v bezpečnom konfiguračnom súbore mimo web rootu a reštartujte PHP-FPM.
