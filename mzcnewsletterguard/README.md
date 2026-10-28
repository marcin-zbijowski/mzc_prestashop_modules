# MZC Newsletter Guard

Chroni **zapis do newslettera** (`ps_emailsubscription`) i **formularz kontaktowy**
(`contactform`) przed botami. Nie dotyka koszyka, zamówień, logowania ani rejestracji.

## Warstwy (od najtańszej)

1. **Pole-pułapka i token czasowy** — tylko newsletter. Token to podpisany znacznik czasu;
   odrzuca wysłanie szybsze niż `MZCNG_MIN_SECONDS` oraz żądania bez JavaScriptu.
2. **Filtr adresów** — wyrażenia regularne, po jednym w linii (domyślnie jednorazowe skrzynki).
3. **Cloudflare Turnstile** — newsletter i kontakt. Rozstrzyga, czy po drugiej stronie jest człowiek.
4. **Limity** — zapisów na IP i globalnie na godzinę (tylko newsletter).

## Turnstile

1. W panelu Cloudflare: *Turnstile → Add widget*. Domeny: `artedeum.pl`, `arte.zbijowski.dev`
   (i `localhost` do testów). Tryb *Managed*.
2. Klucze podaj **w zmiennych środowiskowych** `TURNSTILE_SITE_KEY` i `TURNSTILE_SECRET_KEY`
   (w Dokploy: zakładka Environment; `docker-compose.yml` przekazuje je do kontenera sklepu).
   Wtedy klucz tajny nie trafia do bazy ani do jej kopii. Alternatywa: pola w panelu modułu.
   Zmienne mają pierwszeństwo, ale tylko gdy ustawione są **obie**.
3. Widżet jest niewidoczny, dopóki Cloudflare nie wymaga interakcji (można zmienić na „zawsze
   widoczny”). Ma własne miejsce poza układem formularza: pod formularzem w newsletterze (wersja
   kompaktowa, motyw jasny lub ciemny według tła), nad przyciskiem „Wyślij” w kontakcie. Skrypt
   Cloudflare ładuje się dopiero, gdy ktoś klika w formularz newslettera albo gdy formularz
   kontaktowy jest widoczny. Jeśli ktoś wyśle formularz, zanim Cloudflare wystawi token, wysyłka
   czeka (przy wyzwaniu z kliknięciem pokazuje się widżet i formularz wysyła się po jego
   zaliczeniu).

Zachowanie przy awarii: **brak tokenu = odrzucenie** (tak wygląda bot bez JavaScriptu);
**brak odpowiedzi Cloudflare, 5xx albo zły klucz tajny = przepuszczenie** (formularza nie wolno
zablokować przez cudzą awarię ani naszą pomyłkę). Zły klucz tajny widać w panelu jako czerwony
komunikat i w statystykach jako `ts_misconfig`.

Klucze testowe Cloudflare (publiczne, do testów lokalnych): witryna `1x00000000000000000000AA`,
tajny `1x0000000000000000000000000000000AA` (zawsze przechodzi); tajny
`2x0000000000000000000000000000000AA` (zawsze odrzuca).

## Adres IP

Limity liczą po `REMOTE_ADDR`, bez czytania `CF-Connecting-IP` ani `X-Forwarded-For`: za
Cloudflare i Traefikiem ustawia go `mod_remoteip` (`docker/apache/remoteip.conf` w repozytorium
motywu), ale tylko dla żądań od zaufanego proxy. Bez tej konfiguracji wszyscy odwiedzający mają
adres Traefika i limit na IP działa jak limit globalny.

## Formularz kontaktowy

`contactform` nie ma hooka walidacji. Moduł sprawdza Turnstile w `actionFrontControllerInitAfter`
na stronie kontaktu: przy niepowodzeniu usuwa `submitMessage` z żądania (formularz tylko się
pokazuje, nic nie jest wysyłane) i dopisuje komunikat do błędów strony.

## Wdrożenie

Moduł jest poza repozytorium motywu: skopiować do `modules/` kontenera (`chown www-data`).
Nowa instalacja: `prestashop:module install mzcnewsletterguard`. Aktualizacja z 1.0.0:
`prestashop:module upgrade mzcnewsletterguard` (rejestruje hook formularza kontaktowego,
dopisuje ustawienia; Turnstile pozostaje wyłączony, dopóki nie podasz kluczy).
