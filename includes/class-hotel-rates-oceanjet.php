<?php
/** Admin-managed ferry requests. No supplier inventory or ticket issuance API. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Hotel_Rates_Oceanjet {
    const OPTION = 'hr_oceanjet_settings';
    const CATEGORIES = array( 'adult' => 'Adult', 'minor' => 'Minor', 'infant' => 'Infant', 'student' => 'Student', 'pwd' => 'PWD', 'senior' => 'Senior Citizen' );
    private $adding = false;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ), 90 );
        add_action( 'admin_post_hr_oceanjet_save', array( $this, 'save_settings' ) );
        add_shortcode( 'oceanjet_booking', array( $this, 'shortcode' ) );
        foreach ( array( 'wp_ajax_hr_oceanjet', 'wp_ajax_nopriv_hr_oceanjet' ) as $hook ) { add_action( $hook, array( $this, 'ajax' ) ); }
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'guard_product' ), 100, 3 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'cart_prices' ), 5 );
        add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart' ) );
        add_filter( 'woocommerce_update_cart_validation', array( $this, 'quantity' ), 10, 4 );
        add_filter( 'woocommerce_cart_item_quantity', array( $this, 'quantity_display' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'item_data' ), 20, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_item' ), 30, 4 );
        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'admin_order' ) );
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_ticket' ), 30 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'ticket_status' ) );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'email_status' ), 10, 4 );
    }

    public function settings() {
        return wp_parse_args( get_option( self::OPTION, array() ), array( 'product_id' => 0, 'cutoff' => 120, 'max_pax' => 10, 'infant_max' => 5, 'minor_max' => 11, 'senior_min' => 60, 'ticket_fee' => '0', 'sailings' => array() ) );
    }
    public function menu() {
        add_submenu_page( 'hotel-rates', 'OceanJet', 'OceanJet', 'manage_options', 'hr-oceanjet', array( $this, 'admin_page' ) );
    }
    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $settings = $this->settings();
        include HR_PLUGIN_DIR . 'admin/views/oceanjet.php';
    }
    public static function date( $value ) {
        if ( ! is_string( $value ) ) { return false; }
        $date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'Asia/Manila' ) );
        return $date && $date->format( 'Y-m-d' ) === $value ? $date : false;
    }
    public static function valid_operating_dates( $start, $end ) {
        return ( '' === $start || self::date( $start ) )
            && ( '' === $end || self::date( $end ) )
            && ( '' === $start || '' === $end || $start <= $end );
    }
    public static function operating_dates_overlap( $a, $b ) {
        return ( '' === $a['start'] || '' === $b['end'] || $a['start'] <= $b['end'] )
            && ( '' === $b['start'] || '' === $a['end'] || $b['start'] <= $a['end'] );
    }
    public static function money( $value ) {
        if ( ! is_scalar( $value ) || ! preg_match( '/^\d{1,8}(\.\d{1,2})?$/', (string) $value ) ) { throw new InvalidArgumentException( 'Enter a non-negative amount with at most two decimal places.' ); }
        return (int) round( (float) $value * 100 );
    }
    private function integer( $value, $min, $max ) {
        if ( false === filter_var( $value, FILTER_VALIDATE_INT ) || $value < $min || $value > $max ) { throw new InvalidArgumentException( 'Invalid passenger count or numeric setting.' ); }
        return (int) $value;
    }
    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not permitted.' ); }
        check_admin_referer( 'hr_oceanjet_save' );
        try {
            if ( ! class_exists( 'WooCommerce' ) ) { throw new RuntimeException( 'Activate WooCommerce first.' ); }
            $input = wp_unslash( $_POST );
            if ( empty( $input['complete_form'] ) ) { throw new RuntimeException( 'The server truncated this settings form. Increase PHP max_input_vars before saving; existing settings were not changed.' ); }
            $old = $this->settings();
            $out = array(
                'product_id' => (int) $old['product_id'],
                'cutoff' => $this->integer( $input['cutoff'] ?? 120, 0, 10080 ),
                'max_pax' => $this->integer( $input['max_pax'] ?? 10, 1, 30 ),
                'infant_max' => $this->integer( $input['infant_max'] ?? 5, 0, 10 ),
                'minor_max' => $this->integer( $input['minor_max'] ?? 11, 2, 17 ),
                'senior_min' => $this->integer( $input['senior_min'] ?? 60, 18, 100 ),
                'ticket_fee' => number_format( self::money( $input['ticket_fee'] ?? '0' ) / 100, 2, '.', '' ),
                'sailings' => array(),
            );
            $records = $input['sailings'] ?? array();
            if ( ! is_array( $records ) || count( $records ) > 200 ) { throw new RuntimeException( 'Maximum 200 sailing records.' ); }
            foreach ( $records as $row ) {
                if ( ! is_array( $row ) ) { throw new RuntimeException( 'Invalid sailing.' ); }
                $clean = array();
                foreach ( array( 'from', 'to', 'code', 'class', 'time', 'start', 'end', 'blocked' ) as $key ) { $clean[ $key ] = sanitize_text_field( $row[ $key ] ?? '' ); }
                if ( ! $clean['from'] || ! $clean['to'] || ! $clean['code'] || ! $clean['class'] || strcasecmp( $clean['from'], $clean['to'] ) === 0 ) { throw new RuntimeException( 'Every sailing needs distinct ports, a trip code, and an accommodation.' ); }
                if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $clean['time'] ) || ! self::valid_operating_dates( $clean['start'], $clean['end'] ) ) { throw new RuntimeException( 'Check sailing times and effective dates.' ); }
                $clean['duration'] = $this->integer( $row['duration'] ?? 60, 1, 2880 );
                $clean['days'] = array_values( array_intersect( array_map( 'strval', (array) ( $row['days'] ?? array() ) ), array( '1', '2', '3', '4', '5', '6', '7' ) ) );
                if ( ! $clean['days'] ) { throw new RuntimeException( 'Select at least one operating weekday.' ); }
                foreach ( array_filter( array_map( 'trim', explode( ',', $clean['blocked'] ) ) ) as $blocked ) { if ( ! self::date( $blocked ) ) { throw new RuntimeException( 'Blocked dates must be comma-separated YYYY-MM-DD values.' ); } }
                $clean['active'] = ! empty( $row['active'] );
                $id = sanitize_key( $row['id'] ?? '' );
                $clean['id'] = $id && isset( $old['sailings'][ $id ] ) ? $id : wp_generate_uuid4();
                foreach ( self::CATEGORIES as $key => $label ) {
                    $value = $row['fares'][ $key ] ?? '';
                    $clean['fares'][ $key ] = '' === $value ? null : self::money( $value );
                }
                if ( null === $clean['fares']['adult'] ) { throw new RuntimeException( 'Adult fare is required. Blank other fares disable those categories for this sailing.' ); }
                foreach ( $out['sailings'] as $existing ) {
                    if ( $clean['active'] && $existing['active'] && $existing['from'] === $clean['from'] && $existing['to'] === $clean['to'] && $existing['class'] === $clean['class'] && $existing['time'] === $clean['time'] && self::operating_dates_overlap( $existing, $clean ) && array_intersect( $existing['days'], $clean['days'] ) ) {
                        throw new RuntimeException( 'Overlapping records for the same route, time and accommodation. Use separate effective date ranges.' );
                    }
                }
                $out['sailings'][ $clean['id'] ] = $clean;
            }
            $product = wc_get_product( $out['product_id'] );
            if ( ! $product ) {
                $product = new WC_Product_Simple();
                $product->set_name( 'OceanJet booking request' );
                $product->set_status( 'publish' );
                $product->set_catalog_visibility( 'hidden' );
                $product->set_virtual( true );
                $product->set_regular_price( '0' );
                $product->set_tax_status( 'none' );
                $product->update_meta_data( '_hr_oceanjet_product', 'yes' );
                $out['product_id'] = $product->save();
            }
            update_option( self::OPTION, $out, false );
            wp_safe_redirect( admin_url( 'admin.php?page=hr-oceanjet&saved=1' ) );
            exit;
        } catch ( Exception $e ) { wp_die( esc_html( $e->getMessage() ), 'OceanJet settings', array( 'back_link' => true ) ); }
    }

    public function shortcode() {
        if ( ! function_exists( 'WC' ) ) { return '<p>OceanJet booking requires WooCommerce.</p>'; }
        $settings = $this->settings();
        if ( ! $settings['product_id'] || ! $settings['sailings'] ) { return '<p>OceanJet bookings are not available yet.</p>'; }
        wp_enqueue_style( 'hr-oceanjet', HR_PLUGIN_URL . 'public/css/oceanjet.css', array(), HR_VERSION );
        wp_enqueue_script( 'hr-oceanjet', HR_PLUGIN_URL . 'public/js/oceanjet.js', array(), HR_VERSION, true );
        $routes = array();
        foreach ( $settings['sailings'] as $sailing ) { if ( $sailing['active'] ) { $routes[ $sailing['from'] ][] = $sailing['to']; } }
        foreach ( $routes as &$destinations ) { $destinations = array_values( array_unique( $destinations ) ); sort( $destinations ); } unset( $destinations );
        ksort( $routes );
        wp_localize_script( 'hr-oceanjet', 'hrOceanjet', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'hr_oceanjet' ), 'routes' => $routes, 'categories' => self::CATEGORIES, 'max' => $settings['max_pax'], 'infantMax' => $settings['infant_max'], 'minorMax' => $settings['minor_max'], 'seniorMin' => $settings['senior_min'], 'today' => ( new DateTimeImmutable( 'now', new DateTimeZone( 'Asia/Manila' ) ) )->format( 'Y-m-d' ) ) );
        ob_start();
        include HR_PLUGIN_DIR . 'public/views/oceanjet.php';
        return ob_get_clean();
    }
    public function normalize_search( $input ) {
        $s = $this->settings();
        $out = array( 'from' => sanitize_text_field( $input['from'] ?? '' ), 'to' => sanitize_text_field( $input['to'] ?? '' ), 'date' => $input['date'] ?? '', 'return' => $input['return'] ?? '', 'counts' => array() );
        if ( ! $out['from'] || ! $out['to'] || $out['from'] === $out['to'] || ! self::date( $out['date'] ) ) { throw new RuntimeException( 'Choose distinct ports and a valid departure date.' ); }
        if ( $out['return'] && ( ! self::date( $out['return'] ) || $out['return'] < $out['date'] ) ) { throw new RuntimeException( 'Return date must be on or after departure.' ); }
        $total = 0;
        foreach ( self::CATEGORIES as $key => $label ) {
            $count = $this->integer( $input['counts'][ $key ] ?? 0, 0, 'infant' === $key ? $s['infant_max'] : $s['max_pax'] );
            $out['counts'][ $key ] = $count;
            if ( 'infant' !== $key ) { $total += $count; }
        }
        if ( $total < 1 || $total > $s['max_pax'] || array_sum( array_intersect_key( $out['counts'], array_flip( array( 'adult', 'student', 'pwd', 'senior' ) ) ) ) < 1 ) { throw new RuntimeException( 'Select at least one adult-age passenger and stay within the passenger limit.' ); }
        return $out;
    }
    public function available( $row, $date, $from, $to ) {
        $day = self::date( $date );
        if ( ! $day || ! $row['active'] || $row['from'] !== $from || $row['to'] !== $to || ( '' !== $row['start'] && $date < $row['start'] ) || ( '' !== $row['end'] && $date > $row['end'] ) || ! in_array( $day->format( 'N' ), $row['days'], true ) || in_array( $date, array_map( 'trim', explode( ',', $row['blocked'] ) ), true ) ) { return false; }
        $depart = new DateTimeImmutable( $date . ' ' . $row['time'], new DateTimeZone( 'Asia/Manila' ) );
        $settings = $this->settings();
        return $depart->getTimestamp() > time() + 60 * (int) $settings['cutoff'];
    }
    private function leg( $row, $date, $counts ) {
        $amount = 0; $breakdown = array();
        foreach ( $counts as $key => $count ) {
            if ( ! $count ) { continue; }
            if ( ! isset( $row['fares'][ $key ] ) ) { throw new RuntimeException( 'This sailing has no fare for ' . self::CATEGORIES[ $key ] . '.' ); }
            $amount += $count * $row['fares'][ $key ];
            $breakdown[] = self::CATEGORIES[ $key ] . ' x ' . $count . ' @ PHP ' . number_format( $row['fares'][ $key ] / 100, 2 );
        }
        $departure = new DateTimeImmutable( $date . ' ' . $row['time'], new DateTimeZone( 'Asia/Manila' ) );
        return array( 'id' => $row['id'], 'from' => $row['from'], 'to' => $row['to'], 'date' => $date, 'time' => $row['time'], 'arrival' => $departure->modify( '+' . $row['duration'] . ' minutes' )->format( 'Y-m-d H:i' ), 'class' => $row['class'], 'code' => $row['code'], 'cents' => $amount, 'breakdown' => $breakdown );
    }
    public function quote( $input ) {
        $search = $this->normalize_search( $input ); $settings = $this->settings(); $legs = array();
        foreach ( array( 'outbound', 'inbound' ) as $index => $key ) {
            if ( $index && ! $search['return'] ) { break; }
            $row = $settings['sailings'][ $input[ $key ] ?? '' ] ?? null;
            $date = $index ? $search['return'] : $search['date'];
            if ( ! $row || ! $this->available( $row, $date, $index ? $search['to'] : $search['from'], $index ? $search['from'] : $search['to'] ) ) { throw new RuntimeException( 'A selected sailing is no longer available. Please search again.' ); }
            $legs[] = $this->leg( $row, $date, $search['counts'] );
        }
        if ( count( $legs ) === 2 && $legs[1]['date'] . ' ' . $legs[1]['time'] <= $legs[0]['arrival'] ) { throw new RuntimeException( 'Return departure must be after outbound arrival.' ); }
        $fee = self::money( $settings['ticket_fee'] );
        $total = array_sum( array_column( $legs, 'cents' ) ) + $fee;
        return array( 'search' => $search, 'legs' => $legs, 'ticket_fee' => $fee, 'cents' => $total, 'signature' => hash( 'sha256', wp_json_encode( array( $legs, $fee ) ) ) );
    }
    public function passengers( $input, $quote ) {
        $settings = $this->settings(); $people = $input['passengers'] ?? array(); $clean = array(); $expected = array();
        foreach ( $quote['search']['counts'] as $key => $count ) { for ( $i = 0; $i < $count; $i++ ) { $expected[] = $key; } }
        if ( ! is_array( $people ) || count( $people ) !== count( $expected ) ) { throw new RuntimeException( 'Complete details for every passenger.' ); }
        foreach ( array_values( $people ) as $i => $person ) {
            $row = array( 'category' => $expected[ $i ] );
            foreach ( array( 'first', 'last', 'middle', 'phone', 'nationality', 'sex', 'dob' ) as $key ) {
                $row[ $key ] = sanitize_text_field( $person[ $key ] ?? '' );
                if ( strlen( $row[ $key ] ) > 150 ) { throw new RuntimeException( 'A passenger field is too long.' ); }
            }
            $row['email'] = sanitize_email( $person['email'] ?? '' );
            if ( ! $row['first'] || ! $row['last'] || ! $row['nationality'] || ! in_array( $row['sex'], array( 'Male', 'Female' ), true ) || ! self::date( $row['dob'] ) || ( ! empty( $person['email'] ) && ! is_email( $row['email'] ) ) ) { throw new RuntimeException( 'Check each passenger name, birth date, sex, nationality, and email.' ); }
            if ( ! in_array( $row['category'], array( 'minor', 'infant' ), true ) && ! preg_match( '/^[+()\d\s.-]{6,30}$/', $row['phone'] ) ) { throw new RuntimeException( 'Enter a valid contact number for each adult-age passenger.' ); }
            foreach ( $quote['legs'] as $leg ) {
                $dob = self::date( $row['dob'] ); $travel = self::date( $leg['date'] ); $age = $dob->diff( $travel )->y;
                $cat = $row['category'];
                if ( $dob > $travel || $age > 120 || ( 'infant' === $cat && $age >= 2 ) || ( 'minor' === $cat && ( $age < 2 || $age > $settings['minor_max'] ) ) || ( ! in_array( $cat, array( 'infant', 'minor' ), true ) && $age <= $settings['minor_max'] ) || ( 'senior' === $cat && $age < $settings['senior_min'] ) ) { throw new RuntimeException( 'Passenger age does not match the selected category on every travel date.' ); }
            }
            if ( in_array( $row['category'], array( 'student', 'pwd', 'senior' ), true ) && ! in_array( strtolower( $row['nationality'] ), array( 'filipino', 'philippines', 'philippine' ), true ) ) { throw new RuntimeException( 'Student, PWD and senior fares require Filipino nationality and staff ID verification.' ); }
            $clean[] = $row;
        }
        return $clean;
    }
    private function contact( $input ) {
        $out = array();
        foreach ( array( 'first', 'last', 'phone', 'alternative' ) as $key ) { $out[ $key ] = substr( sanitize_text_field( $input[ $key ] ?? '' ), 0, 150 ); }
        $out['email'] = sanitize_email( $input['email'] ?? '' );
        if ( ! $out['first'] || ! $out['last'] || ! preg_match( '/^[+()\d\s.-]{6,30}$/', $out['phone'] ) || ! is_email( $out['email'] ) || $out['email'] !== sanitize_email( $input['confirm'] ?? '' ) ) { throw new RuntimeException( 'Check booking contact details and matching email addresses.' ); }
        return $out;
    }
    public function ajax() {
        check_ajax_referer( 'hr_oceanjet', 'nonce' );
        try {
            if ( ! function_exists( 'WC' ) ) { throw new RuntimeException( 'WooCommerce is unavailable.' ); }
            $raw = wp_unslash( $_POST['payload'] ?? '' );
            if ( ! is_string( $raw ) || strlen( $raw ) > 60000 ) { throw new RuntimeException( 'Invalid request.' ); }
            $input = json_decode( $raw, true );
            if ( ! is_array( $input ) ) { throw new RuntimeException( 'Invalid request.' ); }
            $mode = sanitize_key( $_POST['mode'] ?? '' );
            if ( 'search' === $mode ) {
                $search = $this->normalize_search( $input ); $result = array( 'outbound' => array(), 'inbound' => array() );
                foreach ( $this->settings()['sailings'] as $row ) {
                    foreach ( array( 'outbound', 'inbound' ) as $index => $key ) {
                        $date = $index ? $search['return'] : $search['date'];
                        if ( ! $date || ! $this->available( $row, $date, $index ? $search['to'] : $search['from'], $index ? $search['from'] : $search['to'] ) ) { continue; }
                        try { $result[ $key ][] = $this->leg( $row, $date, $search['counts'] ); } catch ( Exception $e ) { continue; }
                    }
                }
                foreach ( $result as &$list ) { usort( $list, function ( $a, $b ) { return strcmp( $a['time'], $b['time'] ); } ); } unset( $list );
                wp_send_json_success( $result );
            }
            if ( ! in_array( $mode, array( 'review', 'book' ), true ) ) { throw new RuntimeException( 'Unknown action.' ); }
            $quote = $this->quote( $input );
            $people = $this->passengers( $input, $quote ); $contact = $this->contact( $input['contact'] ?? array() );
            if ( empty( $input['consent'] ) ) { throw new RuntimeException( 'Accept the booking terms and availability notice.' ); }
            if ( 'review' === $mode ) { wp_send_json_success( $quote ); }
            if ( ! hash_equals( $quote['signature'], (string) ( $input['signature'] ?? '' ) ) ) { throw new RuntimeException( 'Fares changed. Please review the booking again.' ); }
            if ( null === WC()->cart ) { wc_load_cart(); }
            $settings = $this->settings(); $product = wc_get_product( $settings['product_id'] );
            if ( ! $product || ! $product->is_purchasable() ) { throw new RuntimeException( 'OceanJet bookings are currently unavailable.' ); }
            $request_id = hash( 'sha256', wp_json_encode( array( $quote, $people, $contact ) ) );
            foreach ( WC()->cart->get_cart() as $line ) { if ( ( $line['hr_oceanjet_request'] ?? '' ) === $request_id ) { wp_send_json_success( array( 'redirect' => wc_get_checkout_url() ) ); } }
            $data = array( 'hr_oceanjet_id' => 1, 'hr_oceanjet_request' => $request_id, 'hr_oceanjet' => array( 'input' => array_merge( $quote['search'], array( 'outbound' => $quote['legs'][0]['id'], 'inbound' => $quote['legs'][1]['id'] ?? '' ) ), 'quote' => $quote, 'passengers' => $people, 'contact' => $contact ) );
            $this->adding = true;
            $key = WC()->cart->add_to_cart( $settings['product_id'], 1, 0, array(), $data );
            $this->adding = false;
            if ( ! $key ) { throw new RuntimeException( 'Could not add the booking. Please check your cart.' ); }
            WC()->customer->set_billing_first_name( $contact['first'] ); WC()->customer->set_billing_last_name( $contact['last'] );
            WC()->customer->set_billing_email( $contact['email'] ); WC()->customer->set_billing_phone( $contact['phone'] );
            WC()->customer->save();
            WC()->cart->calculate_totals(); WC()->cart->set_session();
            wp_send_json_success( array( 'redirect' => wc_get_checkout_url() ) );
        } catch ( Throwable $e ) { $this->adding = false; wp_send_json_error( array( 'message' => $e instanceof Exception ? $e->getMessage() : 'Invalid booking data. Please check the form.' ), 400 ); }
    }
    public function guard_product( $valid, $id, $qty ) {
        if ( 'yes' === get_post_meta( $id, '_hr_oceanjet_product', true ) && ! $this->adding ) { wc_add_notice( 'Please use the OceanJet booking form.', 'error' ); return false; }
        return $valid;
    }
    public function cart_prices( $cart ) {
        foreach ( $cart->get_cart() as $key => $line ) {
            if ( empty( $line['hr_oceanjet'] ) ) { continue; }
            $cart->cart_contents[ $key ]['data'] = clone $line['data'];
            $cart->cart_contents[ $key ]['data']->set_price( $line['hr_oceanjet']['quote']['cents'] / 100 );
            $cart->cart_contents[ $key ]['quantity'] = 1;
        }
    }
    public function check_cart() {
        foreach ( WC()->cart->get_cart() as $line ) {
            if ( empty( $line['hr_oceanjet'] ) ) {
                if ( 'yes' === get_post_meta( $line['product_id'], '_hr_oceanjet_product', true ) ) { wc_add_notice( 'Remove the incomplete OceanJet item and use the booking form.', 'error' ); }
                continue;
            }
            try {
                $booking = $line['hr_oceanjet']; $fresh = $this->quote( $booking['input'] );
                $this->passengers( array( 'passengers' => $booking['passengers'] ), $fresh );
                if ( $fresh['signature'] !== $booking['quote']['signature'] ) { throw new RuntimeException( 'OceanJet fares changed. Remove this booking and submit a new search.' ); }
            } catch ( Throwable $e ) { wc_add_notice( $e->getMessage(), 'error' ); }
        }
    }
    public function quantity( $valid, $key, $line, $qty ) {
        if ( ! empty( $line['hr_oceanjet'] ) && 1 !== (int) $qty ) { wc_add_notice( 'Change OceanJet passenger counts through the booking form.', 'error' ); return false; }
        return $valid;
    }
    public function quantity_display( $html, $key, $line ) { return empty( $line['hr_oceanjet'] ) ? $html : '1'; }
    private function summary( $booking ) {
        $out = array( 'Ticket status' => 'Awaiting supplier confirmation - not an issued ticket' );
        foreach ( $booking['quote']['legs'] as $i => $leg ) {
            $out[ $i ? 'Return' : 'Departure' ] = $leg['from'] . ' → ' . $leg['to'] . ' | ' . $leg['date'] . ' ' . $leg['time'] . ' | ' . $leg['class'] . ' | ' . $leg['code'] . ' | Arrival ' . $leg['arrival'] . ' (Philippine time)';
            $out[ $i ? 'Return fares' : 'Departure fares' ] = implode( '; ', $leg['breakdown'] );
        }
        $out['Passengers'] = implode( ', ', array_map( function ( $p ) { return $p['first'] . ' ' . $p['last'] . ' (' . self::CATEGORIES[ $p['category'] ] . ')'; }, $booking['passengers'] ) );
        if ( $booking['quote']['ticket_fee'] ) { $out['Ticketing charge included'] = 'PHP ' . number_format( $booking['quote']['ticket_fee'] / 100, 2 ); }
        return $out;
    }
    public function item_data( $data, $line ) {
        if ( ! empty( $line['hr_oceanjet'] ) ) { foreach ( $this->summary( $line['hr_oceanjet'] ) as $key => $value ) { $data[] = array( 'key' => $key, 'value' => $value ); } }
        return $data;
    }
    public function order_item( $item, $key, $values, $order ) {
        if ( empty( $values['hr_oceanjet'] ) ) { return; }
        $booking = $values['hr_oceanjet'];
        $fresh = $this->quote( $booking['input'] );
        $this->passengers( array( 'passengers' => $booking['passengers'] ), $fresh );
        if ( $fresh['signature'] !== $booking['quote']['signature'] ) { throw new Exception( 'OceanJet fare changed. Please search again.' ); }
        foreach ( $this->summary( $booking ) as $name => $value ) { $item->add_meta_data( $name, $value, true ); }
        $item->add_meta_data( '_hr_oceanjet', $booking, true );
    }
    private function has_booking( $order ) { foreach ( $order->get_items() as $item ) { if ( $item->get_meta( '_hr_oceanjet' ) ) { return true; } } return false; }
    public function admin_order( $order ) {
        if ( ! $this->has_booking( $order ) ) { return; }
        echo '<div class="hr-oceanjet-order"><h3>OceanJet ticket fulfilment</h3><p>Payment does not issue a supplier ticket. Confirm availability and passenger eligibility before issuing.</p>';
        wp_nonce_field( 'hr_oceanjet_ticket_' . $order->get_id(), 'hr_oceanjet_ticket_nonce' );
        echo '<p><label>Supplier ticket reference (blank = awaiting confirmation)<br><input type="text" name="hr_oceanjet_ticket" value="' . esc_attr( $order->get_meta( '_hr_oceanjet_ticket' ) ) . '"></label></p>';
        foreach ( $order->get_items() as $item ) {
            $booking = $item->get_meta( '_hr_oceanjet' ); if ( ! $booking ) { continue; }
            echo '<details><summary>Passenger manifest / booking contact</summary><ul>';
            foreach ( $booking['passengers'] as $p ) { echo '<li>' . esc_html( implode( ' | ', array( $p['category'], $p['first'], $p['middle'], $p['last'], $p['dob'], $p['sex'], $p['nationality'], $p['phone'], $p['email'] ) ) ) . '</li>'; }
            echo '</ul><p>' . esc_html( implode( ' | ', $booking['contact'] ) ) . '</p></details>';
        }
        echo '</div>';
    }
    public function save_ticket( $id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) || empty( $_POST['hr_oceanjet_ticket_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hr_oceanjet_ticket_nonce'] ) ), 'hr_oceanjet_ticket_' . $id ) ) { return; }
        $order = wc_get_order( $id ); if ( ! $order || ! $this->has_booking( $order ) ) { return; }
        $reference = substr( sanitize_text_field( wp_unslash( $_POST['hr_oceanjet_ticket'] ?? '' ) ), 0, 200 );
        $order->update_meta_data( '_hr_oceanjet_ticket', $reference ); $order->save();
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( '_hr_oceanjet' ) ) { $item->update_meta_data( 'Ticket status', $reference ? 'Supplier ticket reference: ' . $reference : 'Awaiting supplier confirmation - not an issued ticket' ); $item->save(); }
        }
    }
    public function ticket_status( $order ) {
        if ( ! $this->has_booking( $order ) ) { return; }
        $ref = $order->get_meta( '_hr_oceanjet_ticket' );
        echo '<p><strong>OceanJet:</strong> ' . esc_html( $ref ? 'Supplier ticket reference: ' . $ref : 'Awaiting supplier confirmation. This order is not an issued ferry ticket. Our team will contact you with ticket details.' ) . '</p>';
    }
    public function email_status( $order, $admin, $plain, $email ) {
        if ( ! $this->has_booking( $order ) ) { return; }
        if ( $plain ) { echo "\nOceanJet: " . ( $order->get_meta( '_hr_oceanjet_ticket' ) ?: 'Awaiting supplier confirmation; this order is not an issued ticket.' ) . "\n"; }
        else { $this->ticket_status( $order ); }
    }
}
