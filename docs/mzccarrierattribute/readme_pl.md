# MZC Carrier Attribute — Dokumentacja

## Wersja 1.0.0

## Autor: Marcin Zbijowski Consulting

## Kompatybilność: PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Spis treści

1. Przegląd
2. Wymagania
3. Instalacja
4. Jak to działa
5. Konfiguracja
6. Przykłady
7. Zachowanie koszyka
8. Aktualizacje przewoźników
9. Konfiguracja Multi-Store
10. Wydajność
11. Tłumaczenia
12. Rozwiązywanie problemów
13. Dezinstalacja
14. Wsparcie

---

## 1. Przegląd

MZC Carrier Attribute pozwala ograniczyć dostępnych przewoźników przy kasie na podstawie wartości atrybutów produktów (np. rozmiarów, materiałów, wag).

Moduł wykorzystuje model białej listy: dla każdej wartości atrybutu wybierasz, którzy przewoźnicy są dozwoleni. Wartości atrybutów bez przypisanych przewoźników nie nakładają żadnych ograniczeń — wszyscy przewoźnicy pozostają dostępni. Tylko wartości atrybutów z jawnie przypisanymi przewoźnikami ograniczają opcje wysyłki.

Gdy klient dodaje do koszyka produkty z ograniczonymi wartościami atrybutów, moduł automatycznie usuwa niedozwolonych przewoźników z opcji dostawy przy kasie. Jeśli w koszyku znajduje się wiele ograniczonych wartości atrybutów, dostępni będą tylko przewoźnicy dozwoleni przez WSZYSTKIE z nich.

Produkty proste bez kombinacji nigdy nie są objęte działaniem tego modułu.

---

## 2. Wymagania

- PrestaShop 8.0.0 lub nowszy (kompatybilny do wersji 9.x)
- PHP 8.1 lub nowszy
- Co najmniej jeden aktywny przewoźnik skonfigurowany
- Co najmniej jedna grupa atrybutów z wartościami (np. Rozmiar: S, M, L, XL)

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
2. Prześlij folder mzccarrierattribute do katalogu modules w PrestaShop przez FTP
3. Przejdź do Moduły > Menedżer modułów w panelu administracyjnym
4. Wyszukaj MZC Carrier Attribute
5. Kliknij Zainstaluj, następnie Konfiguruj

Po instalacji link do modułu pojawia się również w lewym menu panelu administracyjnego w sekcji **Wysyłka** > **Atrybuty przewoźników**, zapewniając szybki dostęp do strony konfiguracji.

---

## 4. Jak to działa

### Model białej listy

Moduł działa na prostej zasadzie białej listy:

1. Każdej wartości atrybutu produktu (np. „XXL" w grupie „Rozmiar") można przypisać jednego lub więcej dozwolonych przewoźników
2. Jeśli wartość atrybutu nie ma przypisanych przewoźników, nie nakłada żadnych ograniczeń — wszyscy przewoźnicy pozostają dostępni
3. Jeśli wartość atrybutu ma przypisanych przewoźników, tylko ci przewoźnicy są dozwoleni gdy produkt z tym atrybutem jest w koszyku
4. Gdy w koszyku znajduje się wiele ograniczonych wartości atrybutów, moduł oblicza część wspólną — dostępni są tylko przewoźnicy obecni na WSZYSTKICH listach dozwolonych

### Przykładowa logika

Rozważmy następujące reguły:

- Rozmiar „XXL" → dozwoleni przewoźnicy: Przewoźnik A, Przewoźnik B
- Rozmiar „S" → brak przypisanych przewoźników (brak ograniczeń)
- Materiał „Szkło" → dozwoleni przewoźnicy: Przewoźnik B, Przewoźnik C

Scenariusze:

- Koszyk zawiera produkt w rozmiarze S → wszyscy przewoźnicy dostępni (brak ograniczeń)
- Koszyk zawiera produkt w rozmiarze XXL → dostępni tylko Przewoźnik A i B
- Koszyk zawiera produkt w rozmiarze XXL i z materiałem Szkło → dostępny tylko Przewoźnik B (część wspólna {A, B} i {B, C})
- Koszyk zawiera produkt w rozmiarze XXL i inny w rozmiarze S → dostępni tylko Przewoźnik A i B (tylko XXL nakłada ograniczenia)

### Używane hooki

Moduł wykorzystuje trzy hooki PrestaShop:

- **actionFilterDeliveryOptionList** — główny hook filtrujący. Usuwa niedozwolonych przewoźników z opcji dostawy prezentowanych przy kasie.
- **actionCarrierUpdate** — unieważnia wewnętrzny cache gdy przewoźnik zostanie zmodyfikowany w panelu administracyjnym, zapewniając że reguły zawsze odnoszą się do aktualnych danych przewoźnika.
- **actionPresentCart** — dodaje adnotację do etykiety sumy częściowej wysyłki w podsumowaniu koszyka gdy ograniczenia są aktywne, aby klient widział informację o ograniczeniu niektórych metod wysyłki przez atrybuty produktów.

---

## 5. Konfiguracja

Nawiguj do Moduły > Menedżer modułów, znajdź MZC Carrier Attribute i kliknij Konfiguruj. Alternatywnie, użyj skrótu **Wysyłka** > **Atrybuty przewoźników** w lewym menu.

### Panel reguł

Strona konfiguracji pokazuje wszystkie grupy atrybutów i ich wartości w zwijanych panelach. Dla każdej wartości atrybutu wiersz checkboxów wyświetla wszystkich aktywnych przewoźników. Zaznacz przewoźników, których chcesz dozwolić dla danej wartości atrybutu.

### Filtrowanie

Użyj przycisku Pokaż tylko skonfigurowane atrybuty aby przefiltrować widok do tych wartości atrybutów, które mają co najmniej jednego przypisanego przewoźnika. Jest to przydatne gdy masz wiele atrybutów ale tylko kilka reguł.

Kliknij Pokaż wszystkie atrybuty aby wrócić do pełnego widoku.

### Zapisywanie

Kliknij Zapisz reguły na dole strony aby zapisać wszystkie reguły naraz. Moduł zapisuje wszystkie reguły w jednej transakcji dla spójności.

Komunikat sukcesu potwierdza zapis. Jeśli nie znaleziono atrybutów, wyświetlany jest komunikat błędu.

---

## 6. Przykłady

### Przypadek 1: Przedmioty ponadgabarytowe

Sprzedajesz meble i niektóre przedmioty są wysyłane wyłącznie przez przewoźników towarowych.

1. Utwórz grupę atrybutów „Klasa wysyłki" z wartościami jak „Standardowa" i „Tylko towar"
2. Przypisz „Tylko towar" → Twoi przewoźnicy towarowi
3. Zostaw „Standardowa" bez wybranych przewoźników (wszyscy dozwoleni)
4. Zastosuj „Tylko towar" do kombinacji produktów ponadgabarytowych

Rezultat: Produkty ponadgabarytowe pokazują przy kasie tylko przewoźników towarowych.

### Przypadek 2: Ograniczenia regionalne

Sprzedajesz produkty, które mogą być wysyłane tylko przez określonych przewoźników ze względu na ograniczenia materiałowe.

1. Użyj istniejącego atrybutu jak „Materiał" z wartościami takimi jak „Bateria litowa", „Łatwopalny", „Standardowy"
2. Przypisz „Bateria litowa" → przewoźnicy certyfikowani do materiałów niebezpiecznych
3. Przypisz „Łatwopalny" → przewoźnicy certyfikowani do towarów łatwopalnych
4. Zostaw „Standardowy" bez wybranych przewoźników

Rezultat: Produkty z ograniczonymi materiałami pokazują tylko certyfikowanych przewoźników.

### Przypadek 3: Wysyłka oparta na rozmiarze

Sprzedajesz odzież, a rozmiary XXL wymagają konkretnego przewoźnika z większym opakowaniem.

1. W grupie atrybutów „Rozmiar" przypisz „XXL" i „XXXL" → Twój przewoźnik dużych paczek
2. Zostaw wszystkie inne rozmiary bez wybranych przewoźników

Rezultat: Zamówienia zawierające przedmioty XXL lub XXXL pokazują tylko przewoźnika dużych paczek.

---

## 7. Zachowanie koszyka

### Wiele produktów

Gdy koszyk zawiera wiele produktów z ograniczonymi atrybutami, moduł oblicza część wspólną wszystkich zestawów dozwolonych przewoźników. Oznacza to:

- Jeśli Produkt A dozwala przewoźników {1, 2, 3} a Produkt B dozwala {2, 3, 4}, dostępni są tylko przewoźnicy {2, 3}
- Jeśli część wspólna jest pusta (żaden przewoźnik nie jest dozwolony przez wszystkie ograniczone produkty), wszystkie opcje dostawy są usuwane i klient nie może przejść do kasy

### Informacja w podsumowaniu wysyłki

Gdy ograniczenia przewoźników są aktywne dla bieżącego koszyka, moduł dopisuje tekst „(niektóre metody ograniczone przez atrybuty produktów)" do etykiety sumy częściowej wysyłki w podsumowaniu koszyka. Zapewnia to przejrzystość dla klienta dlaczego niektóre metody wysyłki mogą być niedostępne.

### Koszyk mieszany

Jeśli koszyk zawiera zarówno ograniczone jak i nieograniczone produkty, stosowane są tylko reguły ograniczonych produktów. Produkty nieograniczone (produkty proste lub te z nieskonfigurowanymi atrybutami) nie wpływają na dostępność przewoźników.

---

## 8. Aktualizacje przewoźników

Gdy modyfikujesz przewoźnika w panelu administracyjnym PrestaShop (zmiana nazwy, stref, cennika), moduł automatycznie unieważnia swój wewnętrzny cache przy następnym załadowaniu strony. Gwarantuje to, że reguły zawsze odnoszą się do aktualnych danych przewoźnika.

PrestaShop tworzy nowy rekord przewoźnika z nowym ID gdy edytujesz przewoźnika, ale zachowuje jego referencyjne ID. Moduł używa referencyjnych ID, więc reguły pozostają ważne nawet po edycji przewoźnika.

Jeśli całkowicie usuniesz przewoźnika, jego reguły stają się nieaktywne, ale nie są automatycznie usuwane z konfiguracji. Moduł prawidłowo obsługuje odwołania do usuniętych przewoźników, pomijając je podczas filtrowania.

---

## 9. Konfiguracja Multi-Store

Moduł obsługuje funkcję multi-store PrestaShop. Każdy sklep może mieć niezależnie skonfigurowane reguły przewoźnik-atrybut.

Reguły są przechowywane per-sklep w tabeli Configuration, więc przełączanie między kontekstami sklepów w panelu administracyjnym pokazuje i zapisuje reguły dla wybranego sklepu.

---

## 10. Wydajność

### Strategia cache

Moduł wykorzystuje dwupoziomową strategię cache:

1. **Cache konfiguracji** — reguły są serializowane jako JSON w tabeli ps_configuration dla szybkiego odczytu. Eliminuje to konieczność odpytywania tabeli reguł przy każdym załadowaniu strony.
2. **Cache pamięci per-request** — po załadowaniu reguły i mapowania referencji przewoźników są cache'owane we właściwościach statycznych na czas trwania żądania.

Cache jest automatycznie unieważniany gdy:

- Reguły są zapisywane przez stronę konfiguracji
- Przewoźnik jest aktualizowany w panelu administracyjnym

### Baza danych

Moduł tworzy jedną tabelę (mzc_carrier_attr_rule) z indeksowanymi kolumnami dla efektywnych wyszukiwań. Tabela używa złożonego unikalnego klucza aby zapobiec duplikatom reguł.

---

## 11. Tłumaczenia

Moduł jest dostarczany z pełnymi tłumaczeniami dla 5 języków:

- Angielski (en)
- Polski (pl)
- Francuski (fr)
- Hiszpański (es)
- Włoski (it)

Każdy plik tłumaczeń obejmuje wszystkie 20 tłumaczalnych ciągów w klasie modułu, szablonie formularza reguł i szablonach ostrzeżeń.

### Dodawanie lub edycja tłumaczeń

Aby przetłumaczyć moduł na dodatkowe języki lub zmodyfikować istniejące tłumaczenia:

1. Przejdź do Międzynarodowy > Tłumaczenia w panelu administracyjnym
2. Wybierz Tłumaczenia zainstalowanych modułów z listy rozwijanej Typ
3. Wybierz język docelowy
4. Znajdź MZC Carrier Attribute na liście modułów
5. Wprowadź tłumaczenia i kliknij Zapisz

PrestaShop automatycznie zapisze plik tłumaczeń do modules/mzccarrierattribute/translations/.

---

## 12. Rozwiązywanie problemów

### Ograniczenia przewoźników nie działają

- Sprawdź czy reguły są zapisane: przejdź do konfiguracji modułu i upewnij się że właściwi przewoźnicy są zaznaczeni dla odpowiednich wartości atrybutów
- Upewnij się że przewoźnik jest aktywny i nie został usunięty
- Wyczyść cache PrestaShop (Zaawansowane parametry > Wydajność > Wyczyść cache)
- Sprawdź czy produkt ma kombinację z ograniczoną wartością atrybutu (produkty proste nie są objęte)

### Wszyscy przewoźnicy znikają przy kasie

- Dzieje się tak gdy część wspólna wszystkich ograniczonych wartości atrybutów jest pusta (żaden przewoźnik nie jest dozwolony przez wszystkie ograniczone produkty w koszyku)
- Przejrzyj reguły aby upewnić się że co najmniej jeden przewoźnik jest wspólny dla wszystkich ograniczonych wartości atrybutów, które mogą jednocześnie znaleźć się w koszyku
- Użyj filtra „Pokaż tylko skonfigurowane atrybuty" aby przejrzeć wszystkie aktywne reguły

### Reguły nie wyświetlają się na stronie konfiguracji

- Upewnij się że masz co najmniej jedną grupę atrybutów z wartościami
- Upewnij się że masz co najmniej jednego aktywnego przewoźnika
- Jeśli którykolwiek brakuje, moduł wyświetla ostrzeżenie zamiast formularza reguł

### Wolna strona konfiguracji

- Jeśli masz setki wartości atrybutów, strona konfiguracji może ładować się wolno ze względu na duży formularz
- Użyj filtra „Pokaż tylko skonfigurowane atrybuty" aby zmniejszyć ilość wyświetlanych danych
- Rozważ utworzenie dedykowanej grupy atrybutów „Klasa wysyłki" z mniejszą liczbą wartości zamiast konfigurowania reguł dla każdego atrybutu

---

## 13. Dezinstalacja

1. Przejdź do Moduły > Menedżer modułów
2. Znajdź MZC Carrier Attribute
3. Kliknij strzałkę rozwijaną i wybierz Odinstaluj
4. Potwierdź dezinstalację

Moduł:

- Usunie tabelę reguł (mzc_carrier_attr_rule)
- Usunie wartość konfiguracji (MZC_CARRIER_ATTR_RULES)
- Wyrejestruje wszystkie hooki

Wszystkie reguły przewoźnik-atrybut zostaną trwale usunięte.

---

## 14. Wsparcie

W sprawie wsparcia, zgłoszeń błędów lub propozycji funkcji, skontaktuj się z nami przez system wiadomości PrestaShop Addons na stronie produktu modułu.

Zgłaszając problem, podaj:

- Wersję PrestaShop
- Wersję PHP
- Nazwę i wersję motywu
- Listę aktywnych przewoźników i grup atrybutów
- Kroki do odtworzenia problemu
