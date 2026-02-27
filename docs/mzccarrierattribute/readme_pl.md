# MZC Carrier Attribute — Dokumentacja

## Wersja 1.1.0

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

Moduł wykorzystuje model białej listy z regułami złożonymi: tworzysz reguły łączące jeden lub więcej warunków atrybut-wartość z zestawem dozwolonych przewoźników. Reguła ma zastosowanie gdy WSZYSTKIE jej warunki pasują do kombinacji produktu. Na przykład możesz utworzyć regułę „Rozmiar 40×50 + Ramka Oprawiony → tylko Kurier", pozostawiając „Rozmiar 40×50 + Ramka Nieoprawiony" bez reguły, aby wszyscy przewoźnicy byli dostępni. Reguły z jednym warunkiem działają dokładnie jak proste mapowania atrybut → przewoźnik.

Gdy klient dodaje do koszyka produkty z ograniczonymi kombinacjami atrybutów, moduł automatycznie usuwa niedozwolonych przewoźników z opcji dostawy przy kasie. Jeśli wiele reguł pasuje do produktów w koszyku, dostępni będą tylko przewoźnicy dozwoleni przez WSZYSTKIE pasujące reguły.

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

### Model białej listy — Reguły złożone

Moduł działa na zasadzie białej listy z regułami złożonymi wielowarunkowymi:

1. Każda reguła składa się z jednego lub więcej warunków atrybut-wartość oraz zestawu dozwolonych przewoźników
2. Reguła ma zastosowanie gdy WSZYSTKIE jej warunki pasują do atrybutów kombinacji produktu
3. Reguły z jednym warunkiem (jeden atrybut-wartość → przewoźnicy) działają dokładnie jak poprzedni model — wsteczna kompatybilność
4. Reguły złożone (2+ warunki) pozwalają na precyzyjną kontrolę nad konkretnymi kombinacjami atrybutów (np. „Rozmiar 40×50 + Ramka Oprawiony → tylko Kurier")
5. Jeśli żadna reguła nie pasuje do kombinacji produktu, nie nakłada się żadne ograniczenie — wszyscy przewoźnicy pozostają dostępni
6. Gdy wiele reguł pasuje do produktów w koszyku, moduł oblicza część wspólną — dostępni są tylko przewoźnicy obecni na WSZYSTKICH listach dozwolonych pasujących reguł

### Przykładowa logika

Rozważmy następujące reguły złożone:

- Reguła 1: Rozmiar „40×50" + Ramka „Oprawiony" → dozwoleni przewoźnicy: tylko Kurier
- Reguła 2: Rozmiar „50×70" + Ramka „Oprawiony" → dozwoleni przewoźnicy: tylko Kurier
- Brak reguły dla kombinacji nieoprawionych → wszyscy przewoźnicy dostępni

Scenariusze:

- Koszyk zawiera plakat 40×50 Nieoprawiony → żadna reguła nie pasuje → wszyscy przewoźnicy dostępni
- Koszyk zawiera plakat 40×50 Oprawiony → Reguła 1 pasuje → dostępny tylko Kurier
- Koszyk zawiera plakat 50×70 Oprawiony → Reguła 2 pasuje → dostępny tylko Kurier
- Koszyk zawiera plakat 40×50 Oprawiony i plakat 50×70 Oprawiony → Reguły 1 i 2 pasują → część wspólna {Kurier} i {Kurier} = tylko Kurier
- Koszyk zawiera plakat 40×50 Oprawiony i plakat 40×50 Nieoprawiony → Reguła 1 pasuje do oprawionego, brak reguły dla nieoprawionego → dostępny tylko Kurier

### Używane hooki

Moduł wykorzystuje trzy hooki PrestaShop:

- **actionFilterDeliveryOptionList** — główny hook filtrujący. Usuwa niedozwolonych przewoźników z opcji dostawy prezentowanych przy kasie.
- **actionCarrierUpdate** — unieważnia wewnętrzny cache gdy przewoźnik zostanie zmodyfikowany w panelu administracyjnym, zapewniając że reguły zawsze odnoszą się do aktualnych danych przewoźnika.
- **actionPresentCart** — dodaje adnotację do etykiety sumy częściowej wysyłki w podsumowaniu koszyka gdy ograniczenia są aktywne, aby klient widział informację o ograniczeniu niektórych metod wysyłki przez atrybuty produktów.

---

## 5. Konfiguracja

Nawiguj do Moduły > Menedżer modułów, znajdź MZC Carrier Attribute i kliknij Konfiguruj. Alternatywnie, użyj skrótu **Wysyłka** > **Atrybuty przewoźników** w lewym menu.

### Kreator reguł

Strona konfiguracji zawiera dynamiczny kreator reguł. Reguły wyświetlane są jako karty, z których każda zawiera swoje warunki i dozwolonych przewoźników.

- Kliknij **Dodaj regułę** aby utworzyć nową regułę
- Każda reguła posiada jeden lub więcej selektorów warunków (grupa atrybutów → lista rozwijana wartości) oraz zestaw checkboxów przewoźników
- Kliknij **Dodaj warunek** w ramach reguły aby dodać kolejny warunek atrybut-wartość (tworząc regułę złożoną)
- Usuń poszczególne warunki przyciskiem usuwania obok każdego warunku
- Usuń całą regułę przyciskiem usuwania na karcie reguły

### Zapisywanie

Kliknij **Zapisz reguły** na dole strony aby zapisać wszystkie reguły naraz. Moduł zapisuje wszystkie reguły w jednej transakcji bazodanowej dla spójności.

Komunikat sukcesu potwierdza zapis. Jeśli nie znaleziono atrybutów lub przewoźników, wyświetlane jest ostrzeżenie.

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

### Przypadek 4: Plakaty oprawione vs nieoprawione (Reguły złożone)

Sprzedajesz plakaty w różnych rozmiarach, zarówno oprawione jak i nieoprawione. Duże oprawione plakaty są zbyt nieporęczne dla paczkomatów i muszą być wysyłane kurierem.

1. Utwórz reguły złożone łączące warunki Rozmiar i Ramka:
   - Rozmiar „40×50" + Ramka „Oprawiony" → tylko Kurier
   - Rozmiar „50×70" + Ramka „Oprawiony" → tylko Kurier
   - Rozmiar „70×100" + Ramka „Oprawiony" → tylko Kurier
2. Pozostaw wszystkie kombinacje nieoprawione bez reguł (wszyscy przewoźnicy dozwoleni)
3. Pozostaw małe oprawione rozmiary (np. 20×30) bez reguł jeśli mieszczą się w paczkomatach

Rezultat: Duże oprawione plakaty pokazują tylko dostawę kurierską, podczas gdy nieoprawione plakaty każdego rozmiaru i małe oprawione plakaty pokazują wszystkich przewoźników łącznie z paczkomatami.

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

Moduł tworzy 3 tabele do przechowywania reguł złożonych:

- **mzc_carrier_attr_rule_group** — grupy reguł per sklep. Każda grupa reprezentuje jedną regułę złożoną.
- **mzc_carrier_attr_rule_condition** — warunki per grupa. Każdy warunek określa grupę atrybutów i wartość atrybutu.
- **mzc_carrier_attr_rule_carrier** — dozwoleni przewoźnicy per grupa. Każdy wpis łączy grupę reguł z dozwolonym przewoźnikiem.

Wszystkie tabele używają indeksowanych kolumn dla efektywnych wyszukiwań i kluczy obcych dla integralności referencyjnej.

---

## 11. Tłumaczenia

Moduł jest dostarczany z pełnymi tłumaczeniami dla 5 języków:

- Angielski (en)
- Polski (pl)
- Francuski (fr)
- Hiszpański (es)
- Włoski (it)

Każdy plik tłumaczeń obejmuje wszystkie 22 tłumaczalne ciągi w klasie modułu, szablonie formularza reguł i szablonach ostrzeżeń.

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
- Otwórz kreator reguł, aby przejrzeć wszystkie aktywne reguły i ich przypisanych przewoźników

### Reguły nie wyświetlają się na stronie konfiguracji

- Upewnij się że masz co najmniej jedną grupę atrybutów z wartościami
- Upewnij się że masz co najmniej jednego aktywnego przewoźnika
- Jeśli którykolwiek brakuje, moduł wyświetla ostrzeżenie zamiast formularza reguł

### Wolna strona konfiguracji

- Jeśli masz setki wartości atrybutów, strona konfiguracji może ładować się wolno ze względu na duży formularz

- Rozważ utworzenie dedykowanej grupy atrybutów „Klasa wysyłki" z mniejszą liczbą wartości zamiast konfigurowania reguł dla każdego atrybutu

---

## 13. Dezinstalacja

1. Przejdź do Moduły > Menedżer modułów
2. Znajdź MZC Carrier Attribute
3. Kliknij strzałkę rozwijaną i wybierz Odinstaluj
4. Potwierdź dezinstalację

Moduł:

- Usunie wszystkie tabele reguł (mzc_carrier_attr_rule_group, mzc_carrier_attr_rule_condition, mzc_carrier_attr_rule_carrier)
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
