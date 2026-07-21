# Bukovina Planner – serverový systém

Požiadavky: PHP 8.1+, zapisovateľný priečinok `data/`, Apache alebo nginx s blokovaním verejného prístupu do `data`.

Administrácia: `/admin/`. Pri prvom spustení sa vytvorí účet `admin@example.sk` s heslom `ZmenMa123!`; po prihlásení ho bezodkladne zmeňte v `data/users.json` pomocou PHP `password_hash`.

Klientský editor používa `?token=...`, zdieľaný režim `?share=...`. Share token nemá na API právo zápisu. Projekty sa ukladajú samostatne v `data/projects`, odoslané verzie v `data/versions`.

SMTP odosielanie e-mailov ešte vyžaduje doplnenie konkrétnych prihlasovacích údajov hostingu; odkazy sa zatiaľ kopírujú z administrácie.
