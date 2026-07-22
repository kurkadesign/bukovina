# Bukovina Planner – serverový systém

Požiadavky: PHP 8.1+, zapisovateľný priečinok `data/`, Apache alebo nginx s blokovaním verejného prístupu do `data`.

Administrácia: `/admin/`. Pri prvom spustení sa vytvorí účet `admin@example.sk` s heslom `ZmenMa123!`; po prihlásení ho bezodkladne zmeňte v `data/users.json` pomocou PHP `password_hash`.

Klientský editor používa `?token=...`, zdieľaný režim `?share=...`. Share token nemá na API právo zápisu. Projekty sa ukladajú samostatne v `data/projects`, odoslané verzie v `data/versions`.

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
