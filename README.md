# Svadobný plánovač

Responzívna statická webová aplikácia v čistom HTML, CSS a JavaScripte. Je pripravená pre GitHub Pages.

## Funkcie

- editor pôdorysu s posunom a zoomom,
- drag-and-drop prvkov a hostí,
- automatické rozloženie miest okolo stolov,
- správa hostí, alergií, menu a účasti,
- automatické ukladanie do `localStorage`,
- import a export celého projektu ako jedného JSON súboru,
- tlač/export PDF cez dialóg prehliadača,
- rekapitulácia a export finálnej verzie.

## Spustenie

Otvorte `index.html` alebo spustite ľubovoľný statický HTTP server.

## Dôležité obmedzenie GitHub Pages

GitHub Pages je statický hosting. JavaScript v prehliadači preto nemôže zapisovať do priečinka `/data`, riešiť súbežný zápis na serveri ani bezpečne odosielať e-mail. Táto verzia ukladá každý plán lokálne a umožňuje jeho bezpečný import/export. Serverový zápis a e-mail vyžadujú externé API (napríklad serverless funkciu), ktoré sa dá neskôr pripojiť bez zmeny dátovej schémy.
