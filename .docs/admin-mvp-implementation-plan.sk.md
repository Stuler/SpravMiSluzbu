# Implementačný plán: prevádzková administrácia MVP

Stav: implementované v pracovnom strome. Izolované integračné testy a kontroly syntaxe prešli na PHP 8.3.7. Overenie v Docker PHP 8.2, MySQL/MariaDB a nasadenie do dohodnutého testovacieho prostredia zostávajú otvorené. Podrobnosti a poradie migrácií: [admin-mvp.md](admin-mvp.md).

## Cieľ

Dokončiť administráciu tak, aby administrátor dokázal vybaviť bežnú podporu zákazníkov a poskytovateľov aj problémové dopyty bez priameho zásahu do databázy.

Použiť existujúci modulárny monolit: Nette, Latte, Nette Forms a Doctrine. Obchodnú logiku umiestniť do doménových služieb a fasád; presentery ponechať tenké.

## Poradie implementácie

| Etapa | Rozsah | Výsledok |
| --- | --- | --- |
| 1. Poskytovatelia | Zoznam, filtre, detail, úpravy, blokovanie, služby a regióny | Administrátor vie udržiavať databázu firiem použiteľnú pre výber |
| 2. Zákazníci | Zoznam, detail, kontakty, stav účtu, história dopytov | Administrátor vie riešiť zákaznícku podporu |
| 3. Dopyty a prevádzkový prehľad | Filtre, prepojenia, prehľad problémov | Administrátor rýchlo nájde dopyty vyžadujúce zásah |
| 4. Kategórie služieb | Dokončenie a oprava existujúcej správy | Katalóg zostane konzistentný pre formuláre aj AI |
| 5. Overenie a nasadenie | Testy, migrácie, kontrola v testovacom prostredí | Administrácia bude pripravená na používanie |

## 1. Správa poskytovateľov

- [x] Zoznam so stránkovaním a vyhľadávaním podľa firmy, IČO a e-mailu.
- [x] Filtre podľa stavu účtu, prijímania dopytov, kategórie a regiónu.
- [x] Detail s firemnými údajmi, kontaktmi, službami, regiónmi a históriou oslovení.
- [x] Úprava profilu, opisu služieb, kategórií a regiónov.
- [x] Pozastavenie prijímania dopytov, blokovanie a odblokovanie účtu.
- [x] Opätovné odoslanie aktivačného odkazu.

Overenie e-mailu musí zostať oddelené od administratívneho blokovania. Odblokovanie nesmie obísť potrebné overenie účtu.

### Akceptačné kritériá

- Blokovaná firma sa nezaradí do nového výberu poskytovateľov.
- Blokovanej firme sa neodošlú čakajúce pozvánky.
- Blokovaná firma nemá prístup k chráneným obrazovkám ani cez existujúce prihlásenie.
- Zmena služieb a regiónov sa prejaví pri ďalšom výbere poskytovateľov.
- História existujúcich oslovení a odpovedí zostane zachovaná.

## 2. Správa zákazníkov

- [x] Zoznam so stránkovaním, vyhľadávaním a filtrom stavu účtu.
- [x] Detail zákazníka s históriou dopytov a odkazmi na ich administráciu.
- [x] Úprava mena, blokovanie a odblokovanie.
- [x] Odoslanie aktivačného odkazu alebo odkazu na obnovu hesla.

Administrátor nebude nastavovať ani zobrazovať heslá. Zmena prihlasovacieho e-mailu nebude súčasťou tejto etapy; vyžaduje samostatný postup overenia novej adresy.

Dopyty hostí zostanú dostupné cez agendu dopytov. Nebudú sa automaticky spájať so zákazníkom podľa rovnakého e-mailu.

### Akceptačné kritériá

- Blokovanie sa prejaví aj pri existujúcom prihlásení zákazníka.
- Odblokovanie neaktivuje účet, ktorého e-mail nebol overený.
- Administrátor vie z detailu zákazníka otvoriť jeho dopyty.
- Úpravy účtu nemenia historické kontaktné údaje uložené pri dopytoch.

## 3. Dopyty a prevádzkový prehľad

- [x] Rozšíriť existujúcu administráciu dopytov o vyhľadávanie a stránkovanie.
- [x] Doplniť filtre podľa stavu, lokality a dátumu.
- [x] Prepojiť detail dopytu s detailom zákazníka a oslovených poskytovateľov.
- [x] Doplniť úvodný prevádzkový prehľad administrácie.

Prehľad zobrazí predovšetkým:

- dopyty čakajúce na kontrolu,
- dopyty bez vhodných poskytovateľov,
- nepotvrdené odoslania e-mailov,
- dopyty čakajúce na reakcie firiem.

### Akceptačné kritériá

- Každá položka prehľadu vedie na zodpovedajúci filtrovaný zoznam alebo detail.
- Filtre a stránkovanie fungujú spoločne a nevyžadujú načítanie všetkých záznamov.
- Zachovajú sa existujúce pravidlá: najviac tri oslovené firmy, ochrana pred duplicitnými pozvánkami a uzavretie dopytu ako konečný stav.

## 4. Dokončenie kategórií služieb

- [x] Overiť a opraviť vytváranie a editáciu koreňových kategórií.
- [x] Overiť a opraviť vytváranie a editáciu podradených kategórií.
- [x] Zabrániť zaradeniu kategórie pod seba alebo pod vlastného potomka.
- [x] Doplniť serverovú validáciu a CSRF ochranu formulárov.
- [x] Zaviesť vyradenie používaných kategórií z nového výberu so zachovaním histórie.

### Akceptačné kritériá

- Hierarchia kategórií nemôže obsahovať cyklus.
- Vyradené kategórie sa neponúkajú v nových dopytoch ani pri AI klasifikácii.
- Historické dopyty a väzby poskytovateľov zostanú čitateľné.
- Vyradenie nadradenej kategórie má explicitne definované správanie voči jej potomkom.

## Spoločné pravidlá

### Oprávnenia a formuláre

- [x] Každú administračnú operáciu chrániť serverovým overením oprávnení.
- [x] Zmeny vykonávať cez CSRF chránené POST formuláre.
- [x] Verejné formuláre nesmú umožniť prideľovanie administrátorských rolí.
- [x] Používať slovenské texty rozhrania a anglické identifikátory, komentáre a logy.

### História zásahov

- [x] Zaznamenať administrátora, čas, cieľ a druh dôležitej zmeny.
- [x] Zaznamenávať najmä blokovanie, odblokovanie, zmeny služieb a regiónov a opätovné odoslanie odkazov.
- [x] Neukladať do histórie heslá, aktivačné tokeny ani odkazy obsahujúce tokeny.
- [x] Pri účtoch uprednostniť blokovanie pred trvalým vymazaním.

Pred implementáciou stavových operácií preveriť aktuálny model aktivácie a blokovania. Ak neuchováva tieto skutočnosti oddelene, pripraviť minimálne potrebné rozšírenie modelu a migráciu.

## 5. Overenie a nasadenie

### Automatizované overenie

- [x] Neprihlásený používateľ a používateľ bez administrátorských oprávnení nemajú prístup k správe údajov.
- [x] Požiadavka bez platného CSRF tokenu nevykoná zmenu.
- [x] Blokovanie zákazníka a poskytovateľa zneplatní prístup cez existujúce prihlásenie.
- [x] Blokovaný alebo pozastavený poskytovateľ sa nevhodne nezaradí do výberu a odosielania.
- [x] Zmeny kategórií a regiónov ovplyvnia oprávnenosť poskytovateľa pre konkrétny dopyt.
- [x] Odblokovanie neobíde overenie e-mailu.
- [x] Úpravy a vyradenie údajov zachovajú históriu dopytov a oslovení.
- [x] Prebehnú kontroly syntaxe PHP a vykreslenia dotknutých administračných obrazoviek.

### Testovacie prostredie

- [x] Pripraviť potrebné migrácie v `db/Migrations` a zdokumentovať ich poradie.
- [ ] Migrácie aplikovať manuálne až v dohodnutom testovacom prostredí.
- [ ] Overiť správanie s cieľovou verziou MySQL/MariaDB a reálnym aplikačným kontajnerom.
- [ ] Overiť e-maily iba s určenými testovacími účtami.
- [ ] Prejsť celý scenár: úprava poskytovateľa → dopyt → výber → pozvánka → reakcia → kontrola administrátorom.

Automatické migrácie na testovacom ani produkčnom prostredí nie sú súčasťou plánu. Testovacie prostredie musí používať vlastnú databázu a tajné údaje.

## Mimo rozsahu tejto etapy

- Platby, predplatné a cenotvorba.
- SMS, mobilná aplikácia a živý chat.
- Vyhľadávanie alebo kontaktovanie neregistrovaných poskytovateľov.
- Kompletná prestavba rozhrania na SPA.
- Komplexné schvaľovacie procesy a rozšírená analytika.

## Podmienky dokončenia

Etapa je dokončená, keď administrátor dokáže spravovať poskytovateľov a zákazníkov, riešiť problémové dopyty a udržiavať kategórie bez priameho zásahu do databázy. Prístupové práva, stavové zmeny, zachovanie histórie a hlavný prevádzkový scenár musia byť overené; zostávajúce obmedzenia musia byť uvedené v dokumentácii.
