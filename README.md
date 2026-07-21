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

## Definovanie objektov a rohov

Objekty spodnej lišty sú definované v poli `TYPES` v `js/state.js`. Posledný objekt obsahuje
viditeľnosť ikony a textu a nezávislý tvar rohov. Dostupné hodnoty `shape` sú `sharp`,
`rounded`, `circle`, `chair` a `none`.

Farbu Font Awesome ikony možno nastaviť v rovnakom objekte cez `iconColor`, napríklad
`iconColor: '#789b7e'` alebo `iconColor: 'var(--plant-color)'`. Jednotná veľkosť ikon na
ploche sa nastavuje premennou `--object-icon-size` v súbore `style.css`.

Napríklad reproduktor s ostrými rohmi možno pridať bez použitia typu DJ:

```js
['speaker','Reproduktor','fa_speaker',80,110,0,{icon:true,text:true,shape:'sharp'}]
```

Veľkosť oblých rohov sa nastavuje premennou `--object-radius-rounded` na začiatku
`style.css`.

## Predvolené rozloženie

Zelená bodka pri vybranom prvku ho uloží do používateľského predvoleného rozloženia.
Predvolené prvky sa po presune, otočení alebo úprave automaticky aktualizujú. Reset sály
následne načíta toto rozloženie. Údaje sa ukladajú v prehliadači pod kľúčom
`wedding-planner-default-items-v1`.

Počiatočný stav editovateľného režimu možno nastaviť v `js/state.js`:

```js
export const nastaveniePlochy = {
  editovatelnyRezim: false
};
```

## Dôležité obmedzenie GitHub Pages

GitHub Pages je statický hosting. JavaScript v prehliadači preto nemôže zapisovať do priečinka `/data`, riešiť súbežný zápis na serveri ani bezpečne odosielať e-mail. Táto verzia ukladá každý plán lokálne a umožňuje jeho bezpečný import/export. Serverový zápis a e-mail vyžadujú externé API (napríklad serverless funkciu), ktoré sa dá neskôr pripojiť bez zmeny dátovej schémy.
