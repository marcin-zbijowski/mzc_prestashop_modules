# MZC Brevo (`mzcbrevo`)

Własny moduł PrestaShop 9 zastępujący oficjalną wtyczkę Brevo. Synchronizuje kontakty, zamówienia, katalog i zdarzenia koszyka przez API v3 Brevo, łącznie z danymi, których oficjalna wtyczka nie przenosi: personalizacją z `mzcproductfields` i okazją (chrzest, komunia, bierzmowanie, ślub) jako atrybutami kontaktu.

Maile transakcyjne zostają bez zmian (Brevo SMTP w PrestaShop + szablony `mzcmailcontent`).

## Jak to działa

```
hooki sklepu ──INSERT──▶ ps_mzcbrevo_queue ──cron co minutę──▶ mzcbrevo:worker ──HTTP──▶ Brevo API v3
                                                                                          │
Brevo ──webhook (wypis, wyniki batchy)──▶ controllers/front/webhook.php ◀─────────────────┘
```

- Hooki **tylko** zapisują zadanie do kolejki. Checkout nigdy nie czeka na Brevo, a awaria Brevo niczego nie gubi.
- Na stronie nie ma żadnego skryptu Brevo, wszystko działa po stronie serwera. Baner cookies i Consent Mode zostają bez zmian.
- Deduplikacja: zadanie z tym samym kluczem (`contact:123`, `order:501`, `event:cart_updated:900`), które czeka jeszcze w kolejce, jest nadpisywane najnowszym stanem. `cart_updated` idzie maksymalnie co 5 min na koszyk.
- Dane do wysyłki są czytane **w momencie wysyłki**, nie w hooku. Wysyłany jest zawsze aktualny stan.

### Strumienie i zadania

| Zdarzenie w sklepie | Hook | Zadanie → endpoint Brevo |
| --- | --- | --- |
| Rejestracja / konto w checkout | `actionCustomerAccountAdd` | `contact` → `PUT /contacts/{ext_id}`, przy 404 `POST /contacts` (`updateEnabled`) |
| Edycja konta, zgoda, zmiana e-maila | `actionObjectCustomerUpdateAfter` | `contact` (jw.) |
| Usunięcie klienta (RODO) | `actionObjectCustomerDeleteAfter` | `delete` → `DELETE /contacts/{email}` |
| Zapis w stopce (ps_emailsubscription) | `actionNewsletterRegistrationAfter` | `doi` → `POST /contacts/doubleOptinConfirmation` |
| Wypis w stopce | `actionNewsletterRegistrationAfter` | `unsubscribe` → zdjęcie z listy newsletter |
| Zmiana koszyka (klient / gość z e-mailem) | `actionCartSave` | `event` `cart_updated` → `POST /events/batch` |
| Brak zamówienia X min po zmianie | `mzcbrevo:worker --detect-abandoned` | `event` `cart_abandoned` (raz na koszyk) |
| Złożenie zamówienia | `actionValidateOrder` | `order` → `POST /orders/status/batch` (`historical: false`), `event` `order_completed`, `contact` |
| Zmiana statusu | `actionOrderStatusPostUpdate` | `order`, `event` `order_status_changed`, `contact` (przeliczenie statystyk) |
| Produkt dodany / zmieniony / usunięty | `actionObjectProduct*After` | `product` → `POST /products/batch` |
| Kategoria dodana / zmieniona / usunięta | `actionObjectCategory*After` | `category` → `POST /categories/batch` |
| Wypis w Brevo | webhook | `newsletter = 0` w PS |

Każdy strumień (kontakty, zamówienia, koszyki, katalog) ma osobny przełącznik i po instalacji jest **wyłączony**.

### Kolejka, retry, pauza

- Statusy: `pending` → `processing` → `done` / `failed` / `dead`.
- 4xx (błąd danych) → od razu `failed`, bez ponowień.
- Błąd sieci, 5xx → ponowienie po 1, 5, 15, 60 min, 6 h; szósta nieudana próba → `dead`.
- 429 → worker wstrzymany na `Retry-After` (albo 60 s), zadanie nie traci próby.
- 5xx → worker wstrzymany na 60 s.
- 401/403 (zły klucz) → worker wstrzymany na 5 min + wpis krytyczny w logach PrestaShop.
- Batch odrzucony w całości (400) jest wysyłany ponownie pozycja po pozycji, żeby jeden zły produkt nie blokował stu dobrych. Eventy: odpowiedź 207 oznacza jako `failed` tylko odrzucone zdarzenia.
- Rekordy `done` są usuwane po 30 dniach. Zakładka **Kolejka** pokazuje liczniki, listę `failed`/`dead` z błędem i wysłanym body oraz przycisk „Ponów”.
- Ostrzeżenie w BO, gdy najstarsze zadanie czeka ponad 30 min (cron nie działa).

## Model danych w Brevo

Kontakt: e-mail + `ext_id` = `ps_<id_customer>`. Zmiana e-maila w sklepie aktualizuje kontakt po `ext_id`, nie tworzy duplikatu.

| Atrybut | Typ | Źródło |
| --- | --- | --- |
| `FIRSTNAME`, `LASTNAME` | tekst | `customer` |
| `SMS` | tekst (E.164) | telefon z ostatniego adresu, **tylko przy zgodzie**. Numer, którego Brevo nie przyjmie, jest pomijany |
| `PS_CUSTOMER_ID` | liczba | `id_customer` |
| `PS_GROUP` | kategoria | domyślna grupa (wartości = id grup) |
| `PS_LANG` | tekst | iso języka |
| `ORDERS_COUNT`, `TOTAL_SPENT` | liczba | zamówienia opłacone (`orders.valid = 1`), w walucie domyślnej, po wszystkich kontach gościa z tym samym e-mailem |
| `FIRST_ORDER_DATE`, `LAST_ORDER_DATE` | data | jw. |
| `LAST_CATEGORY` | tekst | domyślna kategoria najdroższego produktu z ostatniego opłaconego zamówienia |
| `OCCASION_TYPE` | kategoria | 1 chrzest, 2 komunia, 3 bierzmowanie, 4 ślub, 5 inne |
| `OCCASION_ORDER_DATE` | data | data ostatniego zamówienia z produktem okazjonalnym |
| `NEWSLETTER_SOURCE` | tekst | `formularz` / `checkout` / `rejestracja` / `konto` |
| `MARKETING_CONSENT` | boolean | `customer.newsletter` albo aktywny zapis w `emailsubscription` |
| `CONSENT_DATE` | data | `newsletter_date_add` |

Atrybuty tworzy przycisk **Synchronizuj atrybuty** (Ustawienia → Narzędzia). Brevo po cichu pomija wartość atrybutu, który nie istnieje, więc trzeba go użyć przed pierwszą wysyłką kontaktów.

Listy: **klienci** (wszyscy, do segmentów i wykluczeń, bez marketingu) i **newsletter** (tylko ze zgodą). Kontakt bez zgody jest zdejmowany z listy newsletter.

Konto `anonymous@psgdpr.com` (psgdpr przepina na nie zamówienia zanonimizowanych klientów) nigdy nie jest kontaktem ani zdarzeniem. Jego zamówienia idą do Brevo jako przychód, bez identyfikatora kontaktu.

### Wykrywanie okazji

Z produktu, nie z tekstu personalizacji. Pierwszy trafiony krok wygrywa:

1. Ręczny wybór na karcie produktu, zakładka **Brevo** (w tym „Brak”, który nadpisuje nazwę i kategorię).
2. Mapowanie kategoria → okazja (zakładka **Mapowanie**). Najpierw kategoria domyślna, potem pozostałe.
3. Słowa w nazwie po normalizacji: `chrz` → chrzest, `komuni` → komunia, `bierzmowan` → bierzmowanie, `slub`/`wesel`/`zaslubin` → ślub.

Brak trafienia = brak okazji („inne” tylko ręcznie). Zakładka **Okazje** pokazuje wszystkie produkty z wykrytą okazją i jej źródło, do jednorazowego przejrzenia katalogu.

Pełna treść personalizacji (`Etykieta: wartość; …`) trafia tylko do `event_properties.items[].personalization` zdarzeń koszyka i zamówienia.

### Zdarzenia

`cart_updated`, `cart_abandoned`, `order_completed`, `order_status_changed`. W `event_properties`: id koszyka/zamówienia, wartość, waluta, lista pozycji (nazwa, wariant, zdjęcie `large_default`, URL, cena brutto, ilość, personalizacja) oraz dla koszyka `recover_url`.

Zdarzenia koszyka idą tylko do kontaktów ze zgodą marketingową, dopóki włączone jest „Tylko przy zgodzie marketingowej” (domyślnie tak).

### Link powrotu do koszyka

`/module/mzcbrevo/recover?id_cart=…&t=<HMAC>`. W odróżnieniu od `recover_cart` z PrestaShop **nikogo nie loguje** (mail bywa przekazywany dalej):

- zalogowany właściciel koszyka dostaje swój koszyk;
- każdy inny dostaje kopię produktów (z personalizacją) we własnym koszyku i loguje się albo kupuje jako gość.

## Wdrożenie

### 1. Instalacja

```bash
./zip_module.sh mzcbrevo
```

Zip wgrywasz w BO. Po instalacji nic nie jest wysyłane: nie ma klucza, strumienie są wyłączone.

Cron w Dokploy, co minutę, w katalogu sklepu:

```bash
php bin/console mzcbrevo:worker --limit=200 --detect-abandoned
```

Komendę uruchamiaj jako użytkownik serwera WWW (`www-data`), nie jako root. Inaczej pliki w `var/cache` dostaną właściciela root i front zacznie zwracać 500.

### 2. Konfiguracja (Ustawienia)

1. Klucz API → Zapisz → **Test połączenia** (pokazuje konto i plan; sprawdź, czy plan obejmuje eCommerce i automatyzacje na zdarzeniach).
2. **Synchronizuj atrybuty**.
3. ID list newsletter i klienci; ID szablonu DOI (po polsku) i URL strony podziękowania.
4. W ps_emailsubscription wyłącz własny e-mail weryfikacyjny, inaczej zapis ze stopki jest potwierdzany dwa razy (BO ostrzega).
5. Webhook w Brevo: Ustawienia → Webhooki → Marketing, zdarzenie „Unsubscribed”, URL z sekcji „Adresy webhooków”.
6. Mapowanie: statusy zamówień → statusy Brevo (domyślnie: opłacone → `completed`, anulowane/błąd płatności → `cancelled`, zwrot → `refunded`, reszta → `pending`) i kategorie → okazje.
7. **Dodaj zadanie testowe** i sprawdź w zakładce Kolejka, że przeszło (bramka etapu 1).

### 3. Import historii

```bash
php bin/console mzcbrevo:import --dry-run              # zrzut JSON do var/mzcbrevo-import-<data>/
php bin/console mzcbrevo:import                        # kategorie, produkty, kontakty, zamówienia
php bin/console mzcbrevo:import --step=orders --since=2025-01-01
```

- Kategorie i produkty: batch po 100, upsert.
- Kontakty: `/v3/contacts/import`, wszyscy na listę klienci, ze zgodą (i zapisani tylko w stopce) na newsletter. Atrybuty ze statystykami i okazją. `ext_id` dopina pierwsza synchronizacja na żywo.
- Zamówienia: batch po 1000, `historical: true` (nie odpala automatyzacji), wynik każdego batcha wraca na webhook i jest widoczny w zakładce Kolejka.
- `--since=RRRR-MM-DD` zawęża import do tego, co aktualne: zamówienia złożone od tej daty oraz klientów, **których konto zmieniło się albo którzy złożyli zamówienie od tej daty**. **Każdy klient ze zgodą marketingową wchodzi zawsze**, bez względu na wiek konta (inaczej subskrybent z nieruszanym od dwóch lat kontem odpadłby z newslettera). Zapisani tylko w stopce idą zawsze. Przydatne przy planie Brevo z limitem kontaktów (darmowy: 2000): przed importem policz kontakty zapytaniem o unikalne adresy i zostaw zapas na nowe zapisy.
- Idempotentne: ponowne uruchomienie nadpisuje, nie dubluje. Na końcu komenda wypisuje liczbę zamówień i sumę kwot do porównania z panelem eCommerce Brevo.

### 4. Strumienie na żywo

Po imporcie włączaj po kolei: **kontakty → zamówienia → koszyki** (katalog razem z zamówieniami). Przed włączeniem wyłącz oficjalną wtyczkę Brevo (`sendinblue`), BO ostrzega, jeśli jest aktywna.

## Decyzje przyjęte domyślnie (do zmiany w ustawieniach)

| Kwestia z planu | Domyślnie |
| --- | --- |
| DOI dla zgody w rejestracji/checkout | wyłączony (zgoda z checkboxa idzie od razu na listę); przełącznik „Double opt-in dla zgody w koncie” |
| Porzucony koszyk tylko przy zgodzie | tak |
| Próg porzucenia | 60 min, minimalna wartość 0 |
| `OCCASION_*` | atrybuty (ostatnia okazja), nie obiekty |
| Kod rabatowy w powitaniu / 2. mailu koszyka | nie zaimplementowano (do decyzji) |

## Do sprawdzenia na koncie testowym Brevo

- Wartości statusów zamówień (API ich nie dokumentuje): przychód w panelu eCommerce vs raport PS.
- Czy `EXT_ID` w imporcie kontaktów jest przyjmowany (nieudokumentowane; jeśli nie, ext_id i tak dopisze pierwsza synchronizacja na żywo).
- Nazwa zdarzenia w payloadzie webhooka wypisu (moduł przyjmuje `unsubscribe`, `unsubscribed`, `contact_unsubscribed`).

## Rozwój i testy

```bash
cd mzcbrevo
COMPOSER_VENDOR_DIR=vendor-dev composer install    # PHPUnit + symfony/http-client tylko do testów
php vendor-dev/bin/phpunit
composer dump-autoload --no-dev -o                  # vendor/ do paczki: sam autoloader
```

Zależności deweloperskie lądują w `vendor-dev/` (ignorowane przez git i zip), więc `vendor/` modułu zawiera tylko autoloader. Klient HTTP to Symfony HttpClient z PrestaShop, bez SDK Brevo.

Testy jednostkowe: mappery (klient, zamówienie, zdarzenia, katalog), normalizacja telefonu, wykrywanie okazji, klient HTTP (MockHttpClient), worker (retry, pauza, 401/429/5xx, blokada), handlery (batch, rozbijanie odrzuconego batcha, 207, DOI).
