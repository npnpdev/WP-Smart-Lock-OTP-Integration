# WP Smart‑Lock OTP Integration

[English](#english-version) | [Polski](#wersja-polska)

---

## English Version

### Project Description

**WP Smart‑Lock OTP Integration** is a lightweight PHP script (embedded in the BitIntegration plugin) for WordPress + WooCommerce + Amelia Booking. It automates one‑time password (OTP) generation for smart‑lock access—ideal for gyms, studios, or any facility using Tuya or TTLock smart locks. Built on top of existing booking plugins and Google Calendar integration, the system provides end-to-end reservation automation. Upon payment, it:

1. Matches WooCommerce order ↔ Amelia booking
2. Generates a secure, single‑use code
3. Sends the code via email to the customer

### Key Features

* **Zero‑touch OTP**
  – Hooks into WooCommerce webhooks (order paid)
  – Matches to the latest Amelia booking
* **Multi‑lock support**
  – Tuya & TTLock APIs out of the box
  – Easily extendable to other providers
* **Email delivery**
  – Sends OTP to customer + configurable admin copy
* **Config‑driven**
  – All secrets & IDs live in `wp-config.php` or env file
  – Example file included: `variables.example.conf`

### Installation

1. Copy `orderBefore.php` and `orderPaid.php` into your BitIntegration (or custom) plugin folder.
2. Place `variables.example.conf` at project root, rename to `variables.conf` (or set these constants in your `wp-config.php`).
3. Configure your WooCommerce webhook endpoints for:

   * `orderBefore.php` (initial booking‑linking)
   * `orderPaid.php` (OTP generation on payment)
4. Activate BitIntegration plugin in WP Admin.

*No extra PHP versions or extensions required beyond a typical WordPress + WooCommerce + Amelia setup.*

### Configuration

In `variables.example.conf` (or in `wp-config.php`) define:

```conf
# Tuya API
TUYA_CLIENT_ID="your-tuya-client-id"
TUYA_SECRET="your-tuya-secret"
TUYA_DEVICE_ID="your-tuya-device-id"

# TTLock API
TTLOCK_CLIENT_ID="your-ttlock-client-id"
TTLOCK_SECRET="your-ttlock-secret"
TTLOCK_USERNAME="your-ttlock-username"
TTLOCK_PASSWORD="your-ttlock-password"
TTLOCK_LOCK_ID="your-ttlock-lock-id"

# WooCommerce API (status updates)
CONSUMER_KEY="your-woocommerce-consumer-key"
CONSUMER_SECRET="your-woocommerce-consumer-secret"

# Admin email copy
MAIL_COPY_ADDRESS="copy@example.com"
```

### Project Structure

```text
├── orderBefore.php             # match booking ↔ order via Amelia + WP_Posts
├── orderPaid.php               # OTP generation & email on payment
├── variables.example.conf      # example config for all constants
└── docs/
    └── screenshots/            # booking screen, order screen, sample email
```

## Wersja polska

### Opis projektu

**WP Smart‑Lock OTP Integration** to lekki skrypt PHP (we wtyczce BitIntegration) dla WordPress + WooCommerce + Amelia Booking. Automatyzuje generowanie jednorazowych kodów (OTP) do inteligentnych zamków (Tuya, TTLock), wykorzystywane w siłowniach, studiach treningowych czy innych obiektach wykorzystujących inteligentne klamki. Cały system oparty o pozostałe wtyczki zapewnia kompleksową automatyzację procesu rezerwacji obiektu, łącznie z integracją kalendarza Google. Po opłaceniu:

1. Łączy zamówienie WooCommerce z rezerwacją Amelia
2. Generuje bezpieczny kod jednorazowy
3. Wysyła go e‑mailem do klienta

### Kluczowe funkcje

* **Automatyczna generacja OTP**
  – Hooki WooCommerce (webhook „order paid”)
  – Dopasowanie do ostatniej rezerwacji Amelia
* **Obsługa wielu dostawców**
  – API Tuya & TTLock
  – Łatwa rozbudowa o kolejne zamki
* **Wysyłka e‑mail**
  – OTP do klienta + kopia na adres admina
* **Konfiguracja w pliku**
  – Wszystkie klucze i ID w `wp-config.php` lub pliku env
  – Przykład w `variables.example.conf`

### Instalacja

1. Skopiuj `orderBefore.php` i `orderPaid.php` do folderu wtyczki BitIntegration (lub własnej).
2. Zmień nazwę `variables.example.conf` → `variables.conf` (lub ustaw stałe w `wp-config.php`).
3. Skonfiguruj webhook WooCommerce dla:

   * `orderBefore.php` (powiązanie rezerwacji)
   * `orderPaid.php` (generacja OTP)
4. Aktywuj wtyczkę w panelu WordPress.

*Brak dodatkowych wymagań poza standardowym WP + WooCommerce + Amelia.*

### Konfiguracja

W `variables.example.conf` (lub w `wp-config.php`) ustaw:

```conf
# Tuya API
TUYA_CLIENT_ID="your-tuya-client-id"
TUYA_SECRET="your-tuya-secret"
TUYA_DEVICE_ID="your-tuya-device-id"

# TTLock API
TTLOCK_CLIENT_ID="your-ttlock-client-id"
TTLOCK_SECRET="your-ttlock-secret"
TTLOCK_USERNAME="your-ttlock-username"
TTLOCK_PASSWORD="your-ttlock-password"
TTLOCK_LOCK_ID="your-ttlock-lock-id"

# WooCommerce API
CONSUMER_KEY="your-woocommerce-consumer-key"
CONSUMER_SECRET="your-woocommerce-consumer-secret"

# Kopia e-mail
MAIL_COPY_ADDRESS="copy@example.com"
```

### Struktura projektu

```text
├── orderBefore.php
├── orderPaid.php
├── variables.example.conf
└── docs/
    └── screenshots/
```

## Autor / Author

Igor Tomkowicz
📧 [npnpdev@gmail.com](mailto:npnpdev@gmail.com)
GitHub: [npnpdev](https://github.com/npnpdev)
LinkedIn: [igor-tomkowicz](https://www.linkedin.com/in/igor-tomkowicz-a5760b358/)

---

## Licencja / License

MIT License © Igor Tomkowicz. See [LICENSE](LICENSE) for details.
