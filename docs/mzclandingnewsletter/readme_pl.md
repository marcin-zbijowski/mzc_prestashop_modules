# MZC Landing Newsletter — Dokumentacja

## Wersja 1.0.0

## Autor: Marcin Zbijowski Consulting

## Kompatybilność: PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Spis treści

1. Przegląd
2. Wymagania
3. Instalacja
4. Konfiguracja
5. Presety CSS
6. Dokumentacja klas CSS
7. Zgodność z RODO
8. Google Tag Manager i analityka
9. Konfiguracja Multi-Store
10. Zarządzanie subskrybentami
11. Konfiguracja SEO
12. Interakcja z trybem konserwacji
13. Pominięcie dla administratorów
14. Ograniczanie częstotliwości
15. Funkcje bezpieczeństwa
16. Tłumaczenia
17. Rozwiązywanie problemów
18. Dezinstalacja
19. Wsparcie

---

## 1. Przegląd

MZC Landing Newsletter dodaje tryb strony docelowej (Landing Page Mode) do Twojego sklepu PrestaShop. Po włączeniu wszyscy odwiedzający widzą markową stronę z logo sklepu, niestandardową wiadomością i formularzem zapisu do newslettera. Administratorzy i adresy IP na białej liście omijają stronę docelową i mają normalny dostęp do sklepu.

Funkcja ta jest niezależna od wbudowanego trybu konserwacji PrestaShop. Używaj jej gdy sklep nie jest jeszcze gotowy, podczas migracji, konfiguracji katalogu, rebrandingu lub w dowolnym momencie, gdy chcesz zbierać adresy e-mail subskrybentów przed uruchomieniem.

Subskrybenci są zapisywani w natywnej tabeli newslettera PrestaShop (ps_emailsubscription), więc automatycznie pojawiają się w istniejących narzędziach do newslettera bez konieczności synchronizacji czy eksportu.

---

## 2. Wymagania

- PrestaShop 8.0.0 lub nowszy (kompatybilny do wersji 9.x)
- PHP 8.1 lub nowszy
- Moduł ps_emailsubscription zainstalowany (dostarczany domyślnie z PrestaShop)
- Moduł psgdpr zainstalowany i skonfigurowany (opcjonalnie, dla checkboxa zgody RODO)

---

## 3. Instalacja

### Z PrestaShop Addons

1. Pobierz plik ZIP modułu z konta Addons
2. Przejdź do panelu administracyjnego PrestaShop
3. Nawiguj do Moduły > Menedżer modułów
4. Kliknij Załaduj moduł
5. Wybierz plik ZIP i poczekaj na zakończenie instalacji
6. Kliknij Konfiguruj aby skonfigurować moduł

### Instalacja ręczna

1. Rozpakuj plik ZIP
2. Prześlij folder mzclandingnewsletter do katalogu modules w PrestaShop przez FTP
3. Przejdź do Moduły > Menedżer modułów w panelu administracyjnym
4. Wyszukaj MZC Landing Newsletter
5. Kliknij Zainstaluj, następnie Konfiguruj

---

## 4. Konfiguracja

Nawiguj do Moduły > Menedżer modułów, znajdź MZC Landing Newsletter i kliknij Konfiguruj.

### Włączenie trybu strony docelowej

Przełącz opcję Włącz stronę docelową na Tak aby aktywować stronę dla wszystkich odwiedzających. Przełącz na Nie aby dezaktywować i pokazać normalny sklep.

### Wiadomość na stronie docelowej

Wprowadź wiadomość wyświetlaną na stronie docelowej. To pole obsługuje:

- Edycję tekstu sformatowanego (pogrubienie, kursywa, linki, formatowanie)
- Treść wielojęzyczną — użyj selektora języka aby wprowadzić różne wiadomości dla każdego języka
- Treść HTML — dla zaawansowanego formatowania

Domyślna wiadomość: Nadchodzimy! Nasz sklep jest w budowie. Zapisz się do newslettera, aby otrzymać powiadomienie o uruchomieniu.

### Niestandardowy CSS

Wprowadź niestandardowe reguły CSS aby zmienić wygląd strony docelowej. Pozostaw puste aby użyć domyślnych stylów. Zobacz Rozdział 6 dla pełnej listy dostępnych klas CSS.

---

## 5. Presety CSS

Trzy wbudowane presety są dostępne w panelu Presety CSS na stronie konfiguracji. Kliknij Załaduj preset aby wypełnić pole Niestandardowy CSS stylami presetu.

### Modern Dark (Nowoczesny ciemny)

Fioletowy gradient tła z efektem glassmorphism na karcie. Przycisk subskrypcji w gradiencie fioletowo-niebieskim, przezroczyste pola wprowadzania i odwrócone logo dla ciemnych teł. Najlepiej pasuje do marek technologicznych, gamingowych lub nowoczesnego stylu życia.

### Modern Light (Nowoczesny jasny)

Ciepły gradient od brzoskwiniowego do białego z dużą zaokrągloną kartą i głębokimi cieniami. Pomarańczowy gradient na przycisku subskrypcji z elegancką typografią i spacjowaniem liter. Najlepiej pasuje do marek modowych, kosmetycznych lub lifestyle.

### Soft Gray (Delikatna szarość)

Płaskie jasnoszare tło z delikatną obramowaną kartą. Stonowane szare tony w całości z dyskretnym ciemnym przyciskiem subskrypcji. Minimalistyczny i elegancki. Najlepiej pasuje do marek profesjonalnych, B2B lub minimalistycznych.

Każdy preset można używać bezpośrednio lub modyfikować dalej w polu Niestandardowy CSS po załadowaniu.

Ważne: Załadowanie presetu zastępuje istniejący niestandardowy CSS. Jeśli masz własne style, skopiuj je przed załadowaniem presetu.

---

## 6. Dokumentacja klas CSS

Następujące klasy CSS są dostępne do personalizacji. Kompletna tabela referencyjna z opisami jest wyświetlana w panelu Dokumentacja klas CSS na stronie konfiguracji.

### Klasy układu

- .mzc-landing-container — zewnętrzny wrapper, pokrywa cały viewport, kontroluje kolor tła lub gradient
- .mzc-landing-content — wyśrodkowana karta lub pole treści, kontroluje max-width, padding, tło, border-radius i cień

### Logo

- .mzc-landing-logo — wrapper div logo sklepu
- .mzc-landing-logo img — sam obraz logo, kontroluje maksymalną wysokość i szerokość

### Wiadomość

- .mzc-landing-message — wrapper div dla nagłówka i tekstu akapitu
- .mzc-landing-message h1 — główny nagłówek
- .mzc-landing-message h2 — alternatywny styl nagłówka
- .mzc-landing-message h3 — alternatywny styl nagłówka
- .mzc-landing-message p — tekst akapitu pod nagłówkiem

### Formularz

- .mzc-landing-form-wrapper — wrapper dla całego obszaru formularza
- .mzc-form-group — kontener wiersza z polem i przyciskiem
- .mzc-form-input — pole wprowadzania e-mail
- .mzc-form-button — przycisk subskrypcji

### Feedback i RODO

- .mzc-form-feedback — obszar komunikatu sukcesu lub błędu pod formularzem
- .mzc-gdpr-consent — wrapper dla checkboxa zgody RODO i etykiety

### Przykład

```css
.mzc-landing-container {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.mzc-landing-content {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.mzc-form-button {
  background: #764ba2;
  color: #ffffff;
}

.mzc-form-button:hover {
  background: #667eea;
}
```

---

## 7. Zgodność z RODO

Moduł integruje się z oficjalnym modułem RODO PrestaShop (psgdpr) poprzez trzy hooki.

### Checkbox zgody

Gdy psgdpr jest zainstalowany i skonfigurowany, checkbox zgody z Twoim skonfigurowanym komunikatem pojawia się pod polem e-mail na stronie docelowej. Przycisk subskrypcji jest wyłączony do momentu zaznaczenia checkboxa przez odwiedzającego. Jest to obsługiwane automatycznie przez JavaScript modułu psgdpr.

Aby skonfigurować komunikat zgody, przejdź do Moduły > Menedżer modułów > Oficjalna zgodność z RODO > Konfiguruj i ustaw komunikat zgody dla MZC Landing Newsletter.

### Usuwanie danych

Gdy przetwarzane jest żądanie usunięcia danych RODO, moduł usuwa pasujący adres e-mail z tabeli subskrypcji newslettera i czyści wszelkie powiązane rekordy ograniczania częstotliwości.

### Eksport danych

Gdy przetwarzane jest żądanie eksportu danych RODO, moduł zwraca wszystkie rekordy subskrypcji newslettera pasujące do żądanego adresu e-mail, w tym datę subskrypcji i adres IP rejestracji.

---

## 8. Google Tag Manager i analityka

Strona docelowa renderuje trzy standardowe hooki PrestaShop, których używają moduły śledzące:

- displayHeader — renderowany wewnątrz sekcji head HTML. Używany przez Google Tag Manager, Google Analytics, Facebook Pixel i podobne moduły do wstrzykiwania skryptów śledzenia i inicjalizacji dataLayer.
- displayAfterBodyOpeningTag — renderowany natychmiast po otwierającym tagu body. Używany przez Google Tag Manager dla zapasowego iframe noscript.
- displayBeforeBodyClosingTag — renderowany przed zamykającym tagiem body. Używany przez niektóre moduły śledzące do odroczonego ładowania skryptów.

Oznacza to, że każdy moduł śledzący, który korzysta ze standardowego systemu hooków PrestaShop, będzie działał na stronie docelowej bez dodatkowej konfiguracji. Zweryfikowana kompatybilność z:

- Google Tag Manager (gtmmodule)
- PrestaShop Google Analytics (ps_googleanalytics)
- PrestaShop Marketing with Google (psxmarketingwithgoogle)

---

## 9. Konfiguracja Multi-Store

Moduł w pełni obsługuje funkcję multi-store PrestaShop.

### Konfiguracja per sklep

Gdy multi-store jest aktywny i wybierzesz konkretny sklep w selektorze kontekstu sklepu:

- Każde pole konfiguracji pokazuje checkbox nadpisania
- Zaznacz checkbox aby ustawić wartość specyficzną dla sklepu, która nadpisuje globalną domyślną
- Odznacz checkbox aby dziedziczyć wartość z konfiguracji Wszystkie sklepy

### Typowe scenariusze

- Włącz tryb landing dla nowego sklepu, zachowując inne sklepy działające: Ustaw MZC_LANDING_ENABLED na Nie na poziomie Wszystkie sklepy, następnie nadpisz na Tak dla konkretnego sklepu
- Użyj różnych wiadomości per sklep: Ustaw domyślną wiadomość na Wszystkie sklepy, następnie nadpisz wiadomościami specyficznymi dla sklepu tam, gdzie potrzeba
- Użyj różnego CSS per sklep: Każdy sklep może mieć własny styl wizualny poprzez nadpisanie pola Niestandardowy CSS

---

## 10. Zarządzanie subskrybentami

### Lista subskrybentów

Panel Subskrybenci na stronie konfiguracji pokazuje wszystkie adresy e-mail zebrane przez stronę docelową, identyfikowane przez tag źródła mzc_landing_page. Lista wyświetla:

- Adres e-mail
- Język w momencie subskrypcji
- Adres IP rejestracji
- Datę subskrypcji

Lista jest paginowana po 20 wpisów na stronę. Użyj linków nawigacji na dole aby przeglądać.

Kliknij Odśwież listę aby ponownie załadować dane subskrybentów.

### Eksport CSV

Kliknij Eksportuj CSV aby pobrać wszystkich subskrybentów strony docelowej jako plik wartości rozdzielonych przecinkami. Eksport obejmuje wszystkich subskrybentów (nie tylko bieżącą stronę), z kolumnami: e-mail, język, IP i data.

### Integracja z ps_emailsubscription

Ponieważ moduł używa natywnej tabeli newslettera PrestaShop, subskrybenci zebrani na stronie docelowej pojawiają się również w:

- Liście subskrybentów modułu ps_emailsubscription
- Każdym narzędziu eksportu newslettera, które czyta z tabeli emailsubscription
- Integracje Mailchimp, Sendinblue i inne podłączone do PrestaShop

---

## 11. Konfiguracja SEO

Strona docelowa automatycznie ładuje metadane SEO z konfiguracji sklepu dla strony index (strona główna):

- Meta title — używany jako tytuł strony HTML
- Meta description — renderowany jako tag meta description
- Meta keywords — renderowany jako tag meta keywords (jeśli skonfigurowany)

Aby skonfigurować te wartości, przejdź do Parametry sklepu > Ruch i SEO > SEO i URL, znajdź stronę oznaczoną jako index i edytuj meta title, meta description i meta keywords.

Jeśli meta title nie jest skonfigurowany, moduł używa nazwy sklepu jako wartości domyślnej.

Strona docelowa wysyła kod statusu HTTP 503 (Service Unavailable) z nagłówkiem Retry-After. Informuje to wyszukiwarki, że strona jest tymczasowo niedostępna i powinny wrócić później, zachowując istniejące pozycje w wynikach wyszukiwania.

---

## 12. Interakcja z trybem konserwacji

Ważne: Wyłącz wbudowany tryb konserwacji PrestaShop gdy używasz trybu strony docelowej.

Tryb konserwacji PrestaShop (Parametry sklepu > Ogólne > Konserwacja) i tryb strony docelowej tego modułu to niezależne funkcje. Jeśli oba są włączone jednocześnie, tryb konserwacji PrestaShop ma priorytet, ponieważ wykonuje się wcześniej w cyklu życia żądania, przed uruchomieniem hooka tego modułu.

Aby uzyskać dostęp do ustawień konserwacji, przejdź do Parametry sklepu > Ogólne > Konserwacja w panelu administracyjnym i ustaw Włącz sklep na Tak.

Zalecany przepływ pracy:

1. Wyłącz tryb konserwacji PrestaShop (ustaw Włącz sklep na Tak)
2. Włącz tryb strony docelowej MZC Landing Newsletter
3. Pracuj nad sklepem — masz do niego dostęp przez swój adres IP z białej listy
4. Gdy będziesz gotowy do uruchomienia, wyłącz tryb strony docelowej
5. Twój sklep jest natychmiast dostępny dla wszystkich odwiedzających

---

## 13. Pominięcie dla administratorów

Gdy tryb strony docelowej jest włączony, następujący użytkownicy mają normalny dostęp do sklepu:

### Biała lista IP

Każdy adres IP wymieniony w Parametry sklepu > Ogólne > Konserwacja > IP konserwacji omija stronę docelową. Dodaj tam swój adres IP aby pracować nad sklepem, podczas gdy odwiedzający widzą stronę docelową. Wiele adresów IP można oddzielić przecinkami. Obsługiwana jest notacja CIDR (np. 192.168.1.0/24).

### Zalogowani administratorzy

Jeśli ustawienie PS_MAINTENANCE_ALLOW_ADMINS jest włączone, każdy użytkownik z aktywną sesją panelu administracyjnego automatycznie omija stronę docelową. Moduł odczytuje ciasteczko administratora PrestaShop aby wykryć zalogowanych administratorów.

---

## 14. Ograniczanie częstotliwości

Aby zapobiec spamowi i nadużyciom, endpoint subskrypcji wymusza limit 3 prób na adres IP w oknie 10-minutowym.

Gdy limit zostanie przekroczony, odwiedzający widzi komunikat z prośbą o ponowną próbę później. Licznik limitu resetuje się automatycznie po 10 minutach.

Dane ograniczania częstotliwości (adresy IP i znaczniki czasu) są przechowywane w dedykowanej tabeli bazy danych i automatycznie czyszczone. Wygasłe wpisy bieżącego IP są usuwane przy każdym żądaniu, z 1% probabilistycznym globalnym czyszczeniem aby zapobiec rozrostowi tabeli.

---

## 15. Funkcje bezpieczeństwa

### Ochrona CSRF

Formularz subskrypcji zawiera token CSRF rotujący czasowo, który zmienia się co godzinę. Zarówno tokeny z bieżącej, jak i poprzedniej godziny są akceptowane podczas walidacji, aby zapobiec odrzuceniom na granicy godziny.

### Ochrona XSS

Niestandardowy CSS wprowadzony w panelu administracyjnym jest sanityzowany przed zapisem. Tagi HTML są usuwane, a sekwencje przerwania tagu style są neutralizowane aby zapobiec wstrzyknięciu skryptów.

### Content Security Policy

Strona docelowa wysyła nagłówek Content-Security-Policy, który ogranicza źródła skryptów do self i inline (wymagane dla modułów śledzących) oraz pozwala na style z self, inline i źródeł HTTPS (wymagane dla czcionek webowych).

### Walidacja e-mail

Adresy e-mail są walidowane za pomocą wbudowanej metody Validate::isEmail() PrestaShop przed jakąkolwiek operacją na bazie danych.

---

## 16. Tłumaczenia

Moduł zawiera kompletne tłumaczenia dla 5 języków:

- Angielski (en)
- Polski (pl)
- Francuski (fr)
- Hiszpański (es)
- Włoski (it)

Każdy plik tłumaczeń obejmuje wszystkie 87 łańcuchów tekstowych w klasie modułu, kontrolerze subskrypcji i szablonie strony docelowej.

### Dodawanie lub edycja tłumaczeń

Aby przetłumaczyć moduł na dodatkowe języki lub zmodyfikować istniejące tłumaczenia:

1. Przejdź do Międzynarodowe > Tłumaczenia w panelu administracyjnym
2. Wybierz Tłumaczenia zainstalowanych modułów z listy rozwijanej Typ
3. Wybierz język docelowy
4. Znajdź MZC Landing Newsletter na liście modułów
5. Wprowadź tłumaczenia i kliknij Zapisz

PrestaShop automatycznie zapisuje plik tłumaczenia do modules/mzclandingnewsletter/translations/.

---

## 17. Rozwiązywanie problemów

### Strona docelowa się nie wyświetla

- Sprawdź czy MZC_LANDING_ENABLED jest ustawione na Tak w konfiguracji modułu
- Sprawdź czy tryb konserwacji PrestaShop jest wyłączony (Parametry sklepu > Ogólne > Konserwacja > Włącz sklep = Tak)
- Sprawdź czy Twój adres IP nie jest na białej liście IP konserwacji
- Wyczyść cache PrestaShop (Zaawansowane parametry > Wydajność > Wyczyść cache)

### Checkbox RODO się nie wyświetla

- Sprawdź czy moduł psgdpr jest zainstalowany i włączony
- Przejdź do konfiguracji psgdpr i upewnij się, że komunikat zgody jest skonfigurowany dla MZC Landing Newsletter
- Wyczyść cache PrestaShop i przeładuj stronę docelową

### Czcionki nie ładują się poprawnie

- Zwykle występuje gdy czcionki motywu (np. Google Fonts) są ładowane przez hook displayHeader. Moduł renderuje ten hook, więc czcionki powinny się ładować. Jeśli nie, wyczyść cache przeglądarki za pomocą Ctrl+Shift+R (Cmd+Shift+R na Mac)
- Sprawdź konsolę narzędzi deweloperskich przeglądarki pod kątem błędów Content Security Policy

### Skrypty śledzenia nie działają

- Sprawdź czy Twój moduł śledzący używa standardowych hooków PrestaShop (displayHeader, displayAfterBodyOpeningTag lub displayBeforeBodyClosingTag)
- Sprawdź konsolę narzędzi deweloperskich przeglądarki pod kątem błędów JavaScript
- Niektóre moduły śledzące mogą wymagać konkretnego kontekstu strony, który nie jest dostępny na stronie docelowej

### Przycisk subskrypcji nie działa

- Sprawdź konsolę narzędzi deweloperskich przeglądarki pod kątem błędów JavaScript
- Sprawdź czy checkbox zgody psgdpr jest zaznaczony (jeśli RODO jest włączone)
- Sprawdź czy ograniczanie częstotliwości zostało aktywowane (max 3 na IP na 10 minut)

### Subskrybenci nie pojawiają się na liście

- Kliknij Odśwież listę na stronie konfiguracji
- Sprawdź czy przeglądasz właściwy kontekst sklepu w konfiguracji multi-store
- Sprawdź tabelę bazy danych ps_emailsubscription pod kątem wpisów z http_referer = mzc_landing_page

---

## 18. Dezinstalacja

1. Przejdź do Moduły > Menedżer modułów
2. Znajdź MZC Landing Newsletter
3. Kliknij strzałkę rozwijaną i wybierz Odinstaluj
4. Potwierdź dezinstalację

Moduł:

- Usunie wszystkie wartości konfiguracji (MZC_LANDING_ENABLED, MZC_LANDING_MESSAGE, MZC_LANDING_CSS)
- Usunie tabelę ograniczania częstotliwości (mzc_landing_ratelimit)
- Wyrejestruje wszystkie hooki

Subskrybenci newslettera w tabeli emailsubscription NIE SĄ usuwani podczas dezinstalacji, ponieważ są współdzieleni z modułem ps_emailsubscription.

---

## 19. Wsparcie

W przypadku wsparcia, zgłoszeń błędów lub propozycji funkcji, skontaktuj się z nami przez system wiadomości PrestaShop Addons na stronie produktu modułu.

Podczas zgłaszania problemu prosimy o podanie:

- Wersji PrestaShop
- Wersji PHP
- Nazwy i wersji motywu
- Listy innych zainstalowanych modułów
- Wyniku konsoli narzędzi deweloperskich przeglądarki (jeśli dotyczy)
- Kroków do odtworzenia problemu
