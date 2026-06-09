# Babod NetManager

PHP alapú központi kezelőfelület **Zyxel GS1200** sorozatú switchekhez (különösen **GS1200-8V3**).

## Funkciók

- Több switch központi nyilvántartása (név, IP, hely, megjegyzés)
- Automatikus API felismerés: **modern** (GS1200-8V3 / XGS, HTTPS + RSA login) és **legacy** (régebbi GS1200, `login.cgi` + JS fájlok)
- Élő port állapot és forgalmi statisztika
- VLAN és PVID szerkesztés webes felületen
- Statisztika történet SQLite adatbázisban, grafikon Chart.js-sel
- Időzített gyűjtés cron/task schedulerrel

## Követelmények

- PHP 8.1+
- `curl`, `openssl`, `pdo_sqlite` kiterjesztések
- Hálózati elérés a switchek felé (ugyanabból a LAN-ból / routinggal)

## Telepítés

1. Másold a konfigurációt:

```bash
cp config.example.php config.php
```

2. Állítsd be a `config.php`-ban:
   - `admin_password` – a webes felület jelszava
   - `encryption_key` – switch jelszavak titkosításához (hosszú, véletlen string)

3. Indítás beépített PHP szerverrel:

```bash
php -S 0.0.0.0:8080 -t public public/router.php
```

4. Nyisd meg: `http://localhost:8080`
   - Alap admin jelszó: `admin123` (változtasd meg!)

Apache/Nginx esetén a `public/` legyen a document root. Apache-nál az `.htaccess` átírja a kéréseket.

## Switch hozzáadása (GS1200-8V3)

1. **Switchek → Új switch**
2. Add meg az IP-t (pl. `192.168.1.3`) és a switch admin jelszavát
3. Hagyd bekapcsolva a **HTTPS** opciót
4. API típus: **Automatikus** (V3 esetén `modern` lesz)

> A GS1200-8V3 nem rendelkezik hivatalos API-val. Ez az alkalmazás a gyári webes felület belső HTTP végpontjait használja (közösségi projektek alapján). Firmware frissítés után ellenőrizd a működést.

## VLAN szerkesztés

- **VLAN** menüpont → válaszd ki a switch-et
- PVID: portonként az untagged (native) VLAN
- VLAN sorok: portonként `-` / `Untagged` / `Tagged`
- **Alkalmazás a switchre** elküldi a konfigurációt

Fontos szabályok:
- Portonként csak **egy** untagged VLAN lehet
- Számítógépekhez általában untagged VLAN kell
- Uplink/trunk portokon tagged VLAN-ok

## Statisztika gyűjtés cronnal

Windows Task Scheduler vagy Linux cron:

```bash
php /path/to/babod_netmanager/bin/collect.php
```

Ajánlott gyakoriság: 5–15 perc.

## Biztonság

- Ne tedd nyilvános internetre jelszó nélkül / VPN nélkül
- A switch jelszavak titkosítva tárolódnak, de a `encryption_key` védelme kritikus
- A Zyxel switch egyszerre csak **egy** aktív webes munkamenetet enged – az alkalmazás minden művelet után kijelentkezik

## Projekt struktúra

```
public/          – web belépési pont
src/Zyxel/       – switch kommunikáció
templates/       – HTML sablonok
data/            – SQLite adatbázis
bin/collect.php  – háttér gyűjtő script
```

## Ismert korlátok

- A VLAN írási végpontok nem hivatalosak; egyes firmware verzióknál finomhangolás szükséges lehet
- A modern API VLAN parancsnevei firmware-függőek (több alias próbálva)
- Egyszerre csak egy eszköz használhatja a switch webes felületét aktív munkamenettel
