<?php if (!defined('ABSPATH')) { exit; } 

function generate_random_code_name($length = 10) {
    // Znaki, które mogą być używane w nazwie
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $charactersLength = strlen($characters);
    $randomString = '';

    // Generowanie losowej nazwy
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $randomString;
}

function tuya_generate_access_token() {
    $client_id = TUYA_CLIENT_ID;
    $secret = TUYA_SECRET;
    $t = round(microtime(true) * 1000); // Znacznik czasu w milisekundach
    $nonce = uniqid(); // Generowanie unikalnego nonce
    $httpMethod = 'GET'; // Zmienna na metodę HTTP
    $urlPath = '/v1.0/token?grant_type=1'; // Ścieżka URL
    $body = ''; // Dla GET-a body będzie puste

    // Tworzenie stringToSign
    $sha256 = hash('sha256', $body);
    $stringToSign = $httpMethod . "\n" . $sha256 . "\n\n" . $urlPath;

    // Generowanie podpisu (HMAC-SHA256)
    $sign = strtoupper(hash_hmac('sha256', $client_id . $t . $nonce . $stringToSign, $secret));

    // Wykonanie żądania do API Tuya
    $url = 'https://openapi.tuyaeu.com' . $urlPath;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'client_id: ' . $client_id,
        't: ' . $t,
        'sign: ' . $sign,
        'sign_method: HMAC-SHA256',
        'nonce: ' . $nonce,
    ));

    // Wykonanie żądania
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return false; // W przypadku błędu zwróć false
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code >= 400) {
        error_log('Błąd HTTP: ' . $http_code);
        return false;
    }

    $response = json_decode($result, true);
    curl_close($ch);

    // Zwróć access_token lub false, jeśli nie udało się uzyskać
    return isset($response['result']['access_token']) ? $response['result']['access_token'] : false;
}

function ttlock_generate_access_token() {
    // Upewnij się, że wszystkie potrzebne stałe są zdefiniowane
    if (!defined('TTLOCK_CLIENT_ID') || !defined('TTLOCK_SECRET') || !defined('TTLOCK_USERNAME') || !defined('TTLOCK_PASSWORD')) {
        error_log('Nie wszystkie zmienne TTLock są zdefiniowane.');
        return false;
    }

    $url = 'https://euapi.ttlock.com/oauth2/token';

    // Przygotowanie danych dla POSTa w formacie application/x-www-form-urlencoded
    $postData = http_build_query([
        'clientId'     => TTLOCK_CLIENT_ID,
        'clientSecret' => TTLOCK_SECRET,
        'username'     => TTLOCK_USERNAME,
        'password'     => TTLOCK_PASSWORD
    ]);

    $ch = curl_init();

    // Ustawienia cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    // Wykonanie żądania
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code >= 400) {
        error_log('Błąd HTTP podczas pobierania tokenu: ' . $http_code);
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // Dekodowanie odpowiedzi JSON
    $response = json_decode($result, true);

    // Zakładamy, że odpowiedź zawiera klucz
    if (isset($response['access_token'])) {
        return $response['access_token'];
    } else {
        error_log('Odpowiedź nie zawiera accessToken: ' . $result);
        return false;
    }
}

function send_code_to_db($code, $orderId) {
    global $wpdb;

    $stringCode = (string)$code;

    // Ustaw czas utworzenia
    $data_wygenerowania = current_time('mysql');

    // Oblicz datę ważności jako data_wygenerowania + 6h
    $data_waznosci = date('Y-m-d H:00:00', strtotime($data_wygenerowania) + 6 * 3600);

    // Dane do wstawienia
    $data = array(
        'Kod' => $stringCode,
        'Status' => 'aktywny',
        'data_wygenerowania' => $data_wygenerowania,
        'data_waznosci' => $data_waznosci, // Dodajemy nową kolumnę
        'powiazane_zamowienie_wc' => $orderId, 
    );

    // Wstaw nowy rekord do tabeli wp_tuya_lock_passwords
    $insert_result = $wpdb->insert(
        'wp_tuya_lock_passwords',
        $data
    );

    // Sprawdź wynik wstawienia
    if ($insert_result === false) {
        // Obsłuż błąd w przypadku, gdy wstawienie się nie powiodło
        error_log('Błąd podczas wstawiania rekordu: ' . $wpdb->last_error);
    } else {
        // Opcjonalnie: Wyświetl komunikat o sukcesie
        error_log('Nowy rekord zostal dodany pomyslnie. ID nowego rekordu: ' . $wpdb->insert_id);
    }
}

// Funkcja wysyłająca email
function send_email_with_code($code, $orderId) {
    global $wpdb;

    // Ustaw czas utworzenia
    $data_wygenerowania = current_time('mysql'); // Dodanie daty generowania w tej funkcji

    // Znalezienie customerId na podstawie orderId w tabeli wp_amelia_customer_bookings
    $customerId = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT customerId FROM wp_amelia_customer_bookings WHERE connected_order = %d",
            $orderId
        )
    );

    // Jeśli customerId zostało znalezione
    if ($customerId) {
        // Znalezienie emaila na podstawie customerId w tabeli wp_amelia_users
        $email = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT email FROM wp_amelia_users WHERE id = %d",
                $customerId
            )
        );

        // Jeśli email został znaleziony
        if ($email) {
            // Temat wiadomości
            $subject = 'Twój jednorazowy kod dostępu';

            // Oblicz datę ważności jako data_wygenerowania + 6h
            $expiry_date = date('Y-m-d H:00:00', strtotime($data_wygenerowania) + 6 * 3600);

            // Treść wiadomości
            $message = "Witaj,\n\nTwój jednorazowy kod dostępu to: " . $code . "#\nJest on ważny do: " . $expiry_date . "\n\nDziękujemy!";

            // Nagłówki emaila (ustawiamy typ MIME na text/plain)
            $headers = array('Content-Type: text/plain; charset=UTF-8');

            // Wysyłanie emaila za pomocą wp_mail()
            $mail_sent = wp_mail($email, $subject, $message, $headers);

            $subject2 = $subject . ' ' . $email;

            // Wysłanie kopii emaila
            wp_mail("example@example.com", $subject2, $message, $headers);

            // Sprawdzamy, czy wysłanie emaila się powiodło
            if (!$mail_sent) {
                error_log('Błąd podczas wysyłania maila do użytkownika: ' . $email);
                return false;
            } else {
                error_log('Kod został wysłany na email: ' . $email);
                return true;
            }
        } else {
            error_log('Nie znaleziono adresu email dla customerId: ' . $customerId);
            return false;
        }
    } else {
        error_log('Nie znaleziono customerId dla orderId: ' . $orderId);
        return false;
    }
}

function findOrder($limit = 10) {
    global $wpdb;
    
    // Pobierz aktualny czas
    $current_time = current_time('mysql');
    
    // Oblicz czas sprzed 2 godzin i 5 minut
    $time_2_hours_5_minutes_ago = date('Y-m-d H:i:s', strtotime($current_time) - (2 * 3600 + 5 * 60));

    // Pobierz najnowsze rekordy z tabeli, które mają status 'pending' i są nie starsze niż 5 minut
    $bookings = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, connected_order, created 
             FROM wp_amelia_customer_bookings 
             WHERE status = %s 
             AND created > %s
             ORDER BY created DESC
             LIMIT %d",
            'pending', $time_2_hours_5_minutes_ago, $limit
        )
    );
    
    // Sprawdzenie, czy mamy rekordy
    if (empty($bookings)) {
        error_log('Brak rekordów spełniających kryteria.');
        return null;
    }

    // Jeżeli mamy tylko jeden rekord, zwracamy go
    if (count($bookings) === 1) {
        return $bookings[0];
    }

    // W przypadku, gdy mamy więcej niż jeden rekord, musimy je przefiltrować
    $filtered_bookings = [];
    
    foreach ($bookings as $booking) {
        // Sprawdź, czy connected_order występuje w tabeli wp_comments w kolumnie comment_post_ID
        $comment_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM wp_comments WHERE comment_post_ID = %d", 
                $booking->connected_order
            )
        );
        
        // Jeżeli connected_order istnieje w wp_comments, zostawiamy ten rekord
        if ($comment_exists) {
            $filtered_bookings[] = $booking;
        }
    }
    
    // Jeżeli po filtracji mamy tylko jeden rekord, zwracamy go
    if (count($filtered_bookings) === 1) {
        return $filtered_bookings[0];
    }

    // Jeżeli jest więcej niż jeden rekord, wybieramy ten najnowszy (pierwszy w wyniku SELECTa)
    if (!empty($filtered_bookings)) {
        return $filtered_bookings[0]; // Najnowszy rekord będzie pierwszy
    }

    // Jeżeli po filtrowaniu nie ma żadnych rekordów, zwracamy null
    error_log('Nie znaleziono odpowiednich rekordów.');
    return null;
}


// Funkcja do wysyłania żądania do Tuya API
function tuya_door_lock_request() {
    $access_token = tuya_generate_access_token(); // Pobierz access_token

    if (!$access_token) {
        wp_send_json_error('Nie udało się uzyskać access_token.');
        return;
    }

    // Generowanie losowej nazwy
    $name = generate_random_code_name(); // Generuje losową nazwę

    $ch = curl_init();
    $urlPath = '/v1.1/devices/' . TUYA_DEVICE_ID . '/door-lock/offline-temp-password';
    $postData = json_encode(array(
        "effective_time" => 360,
        "invalid_time" => 360,
        "name" => $name,
        "type" => "once"
    ));

    // Wygenerowanie sha256 dla ciała POST
    $sha256 = hash('sha256', $postData);
    $client_id = TUYA_CLIENT_ID;
    $secret = TUYA_SECRET;
    $t = round(microtime(true) * 1000); // Znacznik czasu w milisekundach
    $nonce = uniqid(); // Generowanie unikalnego nonce

    // Tworzenie stringToSign dla żądania POST
    $stringToSign = "POST\n" . $sha256 . "\n\n" . $urlPath;

    // Generowanie podpisu (HMAC-SHA256) dla żądania POST
    $sign = strtoupper(hash_hmac('sha256', $client_id . $access_token . $t . $nonce . $stringToSign, $secret));

    // Ustawienia cURL
    curl_setopt($ch, CURLOPT_URL, 'https://openapi.tuyaeu.com' . $urlPath);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); // Wysyłaj JSON w treści

    // Ustawienia nagłówków
    $headers = array(
        'Client_id: ' . $client_id,
        'T: ' . $t,
        'Sign_method: HMAC-SHA256',
        'Sign: ' . $sign,
        'Nonce: ' . $nonce,
        'Access_token: ' . $access_token,
        'Content-Type: application/json'
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Wykonanie żądania
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        wp_send_json_error('Błąd: ' . curl_error($ch));
    } else {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code >= 400) {
            wp_send_json_error('Błąd HTTP: ' . $http_code);
        } else {
            $response = json_decode($result, true);

            // Sprawdź, czy odpowiedź zawiera "result" i "offline_temp_password"
            if (isset($response['result']['offline_temp_password'])) {
                $orderRecord = findOrder();
                $orderId = $orderRecord->connected_order;
                $code = $response['result']['offline_temp_password'];

                if($orderRecord){
                    // Zapisujemy jednorazowy kod do bazy danych
                    send_code_to_db($code, $orderId);
                    // Wysyłamy wiadomość email z kodem
                    send_email_with_code($code, $orderId);
                }
            } else {
                error_log('Odpowiedz nie zawiera offline_temp_password');
                wp_send_json_error('Odpowiedź nie zawiera offline_temp_password.');
            }
        }
    }

    curl_close($ch);
}

function ttlock_generate_code() {
    error_log('ttlock_keyboard_pwd_request: Rozpoczęcie funkcji.');

    $lockId = TTLOCK_LOCK_ID;

    // Pobierz access token z TTLock
    $access_token = ttlock_generate_access_token();
    if (!$access_token) {
        error_log('ttlock_keyboard_pwd_request: Nie udało się uzyskać access_token TTLock.');
        wp_send_json_error('Nie udało się uzyskać access_token TTLock.');
        return;
    }

    error_log('ttlock_keyboard_pwd_request: Uzyskano access_token TTLock.');

    // Generowanie losowej nazwy dla kodu
    $keyboardPwdName = generate_random_code_name();
    error_log('ttlock_keyboard_pwd_request: Wygenerowano nazwę kodu: ' . $keyboardPwdName);

    // --- Prawidłowe Timestampy w MILISEKUNDACH ---
    $currentTimestampMs = round(microtime(true) * 1000);
    $endDateMs = $currentTimestampMs + (24 * 3600 * 1000); // Dodaj 24 godziny w milisekundach

    error_log('ttlock_keyboard_pwd_request: Timestamp dla date i startDate (ms): ' . $currentTimestampMs);
    error_log('ttlock_keyboard_pwd_request: Timestamp dla endDate (ms): ' . $endDateMs);

    // Przygotowanie danych do żądania POST w formacie application/x-www-form-urlencoded
    $postData = http_build_query([
        'clientId'         => TTLOCK_CLIENT_ID,
        'accessToken'      => $access_token,
        'lockId'           => $lockId,          // Upewnij się, że to poprawny ID zamka
        'keyboardPwdType'  => 1,                
        'keyboardPwdName'  => $keyboardPwdName,
        'startDate'        => $currentTimestampMs, // Start: teraz (w ms)
        'endDate'          => $endDateMs,       // Koniec: za 24h (w ms)
        'date'             => $currentTimestampMs  // Aktualny czas serwera (w ms), wymagany przez API
    ]);
    
    error_log('ttlock_keyboard_pwd_request: Przygotowano dane POST');

    // --- Prawidłowy Endpoint do dodawania kodu ---
    $url = 'https://euapi.ttlock.com/v3/keyboardPwd/get';
    $ch = curl_init();
    error_log('ttlock_keyboard_pwd_request: Inicjalizacja cURL do: ' . $url);

    // Ustawienia cURL dla żądania POST
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    error_log('ttlock_keyboard_pwd_request: Ustawienia cURL zostały skonfigurowane.');

    // Wykonanie żądania
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        error_log('ttlock_keyboard_pwd_request: cURL Error: ' . $curlError);
        wp_send_json_error('Błąd cURL: ' . $curlError);
        curl_close($ch);
        return;
    }
    // Logowanie wyniku tylko dla celów debugowania, ostrożnie w produkcji
    // error_log('ttlock_keyboard_pwd_request: Wynik żądania: ' . $result);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    error_log('ttlock_keyboard_pwd_request: Otrzymany kod HTTP: ' . $http_code);

    // Logowanie pełnej odpowiedzi tylko jeśli wystąpi błąd >= 400 dla debugowania
    if ($http_code >= 400) {
        error_log('ttlock_keyboard_pwd_request: Błąd HTTP: ' . $http_code . ' - Odpowiedź serwera: ' . $result);
        // Zwróć bardziej ogólny błąd użytkownikowi
        wp_send_json_error('Błąd komunikacji z API zamka (HTTP ' . $http_code . ').');
        curl_close($ch);
        return;
    }

    curl_close($ch);
    error_log('ttlock_keyboard_pwd_request: cURL został zamknięty.');

    // Dekodowanie odpowiedzi JSON
    $response = json_decode($result, true);

    // Logowanie zdekodowanej odpowiedzi dla debugowania
    error_log('ttlock_keyboard_pwd_request: Zdekodowana odpowiedź JSON: ' . print_r($response, true));

    // Sprawdzenie błędów zwracanych przez API TTLock
    if (isset($response['errcode']) && $response['errcode'] != 0) {
        $errcode = $response['errcode'];
        $errmsg = isset($response['errmsg']) ? $response['errmsg'] : 'Brak wiadomości błędu.';
        error_log("ttlock_keyboard_pwd_request: Błąd API TTLock - errcode: $errcode, errmsg: $errmsg");
        wp_send_json_error("Błąd API zamka ($errcode): $errmsg");
        return;
    }

    // Sprawdzenie odpowiedzi – endpoint /add zwraca 'keyboardPwd' oraz 'keyboardPwdId'
    if (isset($response['keyboardPwd']) && isset($response['keyboardPwdId'])) {
        $code = $response['keyboardPwd'];
        $pwdId = $response['keyboardPwdId'];
        error_log('ttlock_keyboard_pwd_request: Wygenerowany kod: ' . $code . ' (ID: ' . $pwdId . ')');

        // Pobierz rekord zamówienia
        $orderRecord = findOrder();
        if ($orderRecord) {
            $orderId = $orderRecord->connected_order;
            error_log('ttlock_keyboard_pwd_request: Znaleziono rekord zamówienia. Order ID: ' . $orderId);

            // Zapisz wygenerowany kod do bazy danych
            send_code_to_db($code, $orderId); // Ta funkcja musi obsługiwać datę ważności poprawnie (np. na podstawie $endDateMs)
            error_log('ttlock_keyboard_pwd_request: Zapisano kod do bazy danych.');

            // Wyślij email z kodem
            send_email_with_code($code, $orderId); // Ta funkcja musi obliczyć i wyświetlić poprawną datę ważności
            error_log('ttlock_keyboard_pwd_request: Wysłano email z kodem.');

             // Możesz zwrócić sukces, jeśli to jest wywołanie AJAX
             // wp_send_json_success('Kod TTLock wygenerowany i wysłany.');

        } else {
            error_log('ttlock_keyboard_pwd_request: Nie znaleziono odpowiedniego rekordu zamówienia.');
            wp_send_json_error('Brak rekordu zamówienia do powiązania kodu.');
        }
    } else {
        // Jeśli odpowiedź ma status 200, ale brakuje oczekiwanych danych
        error_log('ttlock_keyboard_pwd_request: Odpowiedź API nie zawiera oczekiwanych danych (keyboardPwd, keyboardPwdId). Odpowiedź: ' . $result);
        wp_send_json_error('Nieprawidłowa odpowiedź z API zamka.');
    }
}

//zmiana statusu, odnajdowanie odpowiedniego zamowienia
function check_and_update_pending_bookings() {
    global $wpdb;
    // Krok 1: Pobierz wszystkie zamówienia z 'wp_amelia_customer_bookings', które mają status 'pending'
    $pending_bookings = $wpdb->get_results("
        SELECT id, appointmentId, connected_order, created 
        FROM wp_amelia_customer_bookings 
        WHERE status = 'pending'
    ");

    // Aktualny czas
    $current_time = current_time('Y-m-d H:i:s'); // Pobierz aktualny czas WordPressa
    $time_threshold = date('Y-m-d H:i:s', strtotime('-2 hours -5 minutes', strtotime($current_time))); // Oblicz 2 godziny i 5 minut wstecz

    // Przefiltruj zamówienia, aby uwzględnić tylko te utworzone po $time_threshold
    $filtered_bookings = array_filter($pending_bookings, function($booking) use ($time_threshold) {
        return $booking->created >= $time_threshold;
    });

    // Dane uwierzytelniające API WooCommerce
    $consumer_key = CONSUMER_KEY;
    $consumer_secret = CONSUMER_SECRET;

    // Krok 2: Dla każdego zamówienia sprawdź, czy connected_order występuje w wp_comments.comment_post_ID
    foreach ($filtered_bookings as $booking) {
        $connected_order_id = intval($booking->connected_order);

        // Sprawdź, czy connected_order znajduje się w comment_post_ID w tabeli wp_comments
        $comment_exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM wp_comments WHERE comment_post_ID = %d", $connected_order_id)
        );

        // Krok 3: Jeśli istnieje, zaktualizuj status zamówienia na 'on-hold', a następnie na 'processing'
        if ($comment_exists) {
            $order_id = $connected_order_id;
            $url = "https://examplesite.com/wp-json/wc/v3/orders/{$order_id}";

            // Body żądania dla 'on-hold'
            $body_on_hold = json_encode([
                'status' => 'on-hold'
            ]);

            // Nagłówki
            $headers = [
                'Authorization' => 'Basic ' . base64_encode($consumer_key . ':' . $consumer_secret),
                'Content-Type'  => 'application/json'
            ];

            // Parametry żądania PUT dla 'on-hold'
            $args = [
                'method'    => 'PUT',
                'headers'   => $headers,
                'body'      => $body_on_hold
            ];

            // Wykonanie żądania dla 'on-hold'
            $response_on_hold = wp_remote_request($url, $args);

            // Sprawdzenie odpowiedzi dla 'on-hold'
            if (is_wp_error($response_on_hold)) {
                error_log("Błąd aktualizacji zamówienia ID: " . $order_id . " na 'on-hold' - " . $response_on_hold->get_error_message());
            } else {
                $response_body_on_hold = wp_remote_retrieve_body($response_on_hold);
                $data_on_hold = json_decode($response_body_on_hold);

                if (isset($data_on_hold->id)) {
                    error_log("Zaktualizowano status zamówienia ID: " . $order_id . " na 'on-hold'.");

					// --- Generowanie kodu ---
                    $appointment_id = $booking->appointmentId;

                    $serviceId = $wpdb->get_var( // Pobierz ID usługi
                            $wpdb->prepare("SELECT serviceId FROM wp_amelia_appointments WHERE id = %d", $appointment_id)
                    );
                    error_log("Przetwarzamy appointmentID:". $appointment_id);
                    error_log("KOD USLUGI: ".$serviceId);

                    if ($serviceId == 2) {
                        tuya_door_lock_request();
                    }
                    else if($serviceId == 5){
                        ttlock_generate_code();
                    }

                    // Krok 4: Zmiana statusu na 'COMPLETED' po 'on-hold'
                    $body_processing = json_encode([
                        'status' => 'completed'
                    ]);

                    // Parametry żądania PUT dla 'COMPLETED'
                    $args_processing = [
                        'method'    => 'PUT',
                        'headers'   => $headers,
                        'body'      => $body_processing,
						'timeout'   => 15 // Zwiększenie limitu czasu na 15 sekund
                    ];

                    // Wykonanie żądania dla 'COMPLETED'
                    $response_processing = wp_remote_request($url, $args_processing);

                    // Sprawdzenie odpowiedzi dla 'COMPLETED'
                    if (is_wp_error($response_processing)) {
                        error_log("Błąd aktualizacji zamówienia ID: " . $order_id . " na 'completed' - " . $response_processing->get_error_message());
                    } else {
                        $response_body_processing = wp_remote_retrieve_body($response_processing);
                        $data_processing = json_decode($response_body_processing);

                        if (isset($data_processing->id)) {
                            error_log("Zaktualizowano status zamówienia ID: " . $order_id . " na 'completed'.");
                        } else {
                            error_log("Błąd aktualizacji zamówienia ID: " . $order_id . " na 'completed' - " . print_r($data_processing, true));
                        }
                    }
                } else {
                    error_log("Błąd aktualizacji zamówienia ID: " . $order_id . " na 'on-hold' - " . print_r($data_on_hold, true));
                }
            }
            
        } else {
            error_log("Brak powiązania dla connected_order ID: " . $connected_order_id . " dla zamówienia ID: " . $booking->id);
        }
    }
}

check_and_update_pending_bookings();

?>