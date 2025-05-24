<?php if (!defined('ABSPATH')) { exit; }

function check_booking_relationship() {
    global $wpdb;

    // Pobierz ostatnie 50 rezerwacji z tabeli Amelia (sortując po ID malejąco)
    $amelia_table = $wpdb->prefix . 'amelia_customer_bookings'; // Użycie prefixu
    $posts_table = $wpdb->prefix . 'posts';                   // Użycie prefixu

    $amelia_bookings = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, created FROM {$amelia_table} ORDER BY id DESC LIMIT %d",
        50 // Limit 50 wierszy
    ));

    foreach ($amelia_bookings as $booking) {
        // Przekształć datę do formatu GMT
        $created_time = strtotime($booking->created);
        $time_range_start = date('Y-m-d H:i:s', $created_time - 5);
        $time_range_end = date('Y-m-d H:i:s', $created_time + 5);

        $query = $wpdb->prepare(
            "SELECT ID, post_modified_gmt 
            FROM wp_posts a
            WHERE post_modified_gmt BETWEEN %s AND %s", 
            $time_range_start, 
            $time_range_end
        );

        // Wykonaj zapytanie i pobierz wyniki
        $results = $wpdb->get_results($query);

        if ($results) {
            // Wybierz najbliższy czas
            $closest_post = null;
            $closest_time_diff = PHP_INT_MAX;

            foreach ($results as $result) {
                $post_modified_time = strtotime($result->post_modified_gmt);
                $time_diff = abs($created_time - $post_modified_time);
                if ($time_diff < $closest_time_diff) {
                    $closest_time_diff = $time_diff;
                    $closest_post = $result;
                }
            }

            if ($closest_post) {
                // Zaktualizuj kolumnę 'connected_order' w tabeli wp_amelia_customer_bookings
                $update_query = $wpdb->prepare(
                    "UPDATE wp_amelia_customer_bookings 
                    SET connected_order = %d 
                    WHERE id = %d",
                    $closest_post->ID, // ID posta, który został znaleziony
                    $booking->id // ID rezerwacji, którą aktualizujemy
                );

                $update_result = $wpdb->query($update_query);
            }
        } else {
            // Loguj brak powiązania
            error_log("Brak powiązania dla: " . $booking->created);
        }
    }
}

// Możesz wywołać tę funkcję, gdy zajdzie taka potrzeba
check_booking_relationship();

?>
