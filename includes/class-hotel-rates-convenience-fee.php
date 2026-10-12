<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Global booking fee, stored as a native WooCommerce order fee. */
class Hotel_Rates_Convenience_Fee {
        const OPTION = 'hr_convenience_fee_percent';
        const FEE_ID = 'hr_global_convenience_fee';

        public function __construct() {
                add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
                add_action( 'admin_init', array( $this, 'register_setting' ) );
                add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_fee' ), 30 );
                add_action( 'woocommerce_checkout_create_order_fee_item', array( $this, 'save_fee_metadata' ), 10, 4 );
                add_filter( 'woocommerce_get_order_item_totals', array( $this, 'position_order_fee' ), 20, 3 );
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_position' ) );
        }

        public function register_menu() {
                add_submenu_page( 'hotel-rates', __( 'Set Convenience Fee', 'hotel-rates' ), __( 'Set Convenience Fee', 'hotel-rates' ), 'manage_options', 'hotel-rates-convenience-fee', array( $this, 'render_page' ) );
        }

        public function register_setting() {
                register_setting( 'hr_convenience_fee', self::OPTION, array(
                        'type' => 'number',
                        'default' => 2,
                        'sanitize_callback' => array( $this, 'sanitize_percent' ),
                ) );
        }

        public function sanitize_percent( $value ) {
                if ( ! is_scalar( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 || (float) $value > 100 ) {
                        add_settings_error( self::OPTION, 'invalid_percentage', __( 'Enter a percentage between 0 and 100.', 'hotel-rates' ) );
                        return $this->get_percent();
                }
                return round( (float) $value, 2 );
        }

        public function get_percent() {
                $value = get_option( self::OPTION, 2 );
                return is_numeric( $value ) && is_finite( (float) $value ) ? min( 100, max( 0, (float) $value ) ) : 2;
        }

        public function render_page() {
                if ( ! current_user_can( 'manage_options' ) ) { return; }
                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'Set Convenience Fee', 'hotel-rates' ); ?></h1>
                        <?php settings_errors(); ?>
                        <form action="options.php" method="post">
                                <?php settings_fields( 'hr_convenience_fee' ); ?>
                                <table class="form-table" role="presentation"><tr>
                                        <th scope="row"><label for="hr-convenience-fee"><?php esc_html_e( 'Convenience Fee (%)', 'hotel-rates' ); ?></label></th>
                                        <td><input id="hr-convenience-fee" name="<?php echo esc_attr( self::OPTION ); ?>" type="number" min="0" max="100" step="0.01" required value="<?php echo esc_attr( $this->get_percent() ); ?>">
                                                <p class="description"><?php esc_html_e( 'Default: 2%. Set to 0 to disable. Applies to booking amounts after discounts, excluding shipping, taxes, and other checkout fees. Shown at checkout and on orders; hidden from booking forms.', 'hotel-rates' ); ?></p>
                                        </td>
                                </tr></table>
                                <?php submit_button(); ?>
                        </form>
                </div>
                <?php
        }

        public function add_fee( $cart ) {
                if ( is_admin() && ! wp_doing_ajax() ) { return; }
                $percent = $this->get_percent();
                if ( $percent <= 0 ) { return; }
                $base = 0;
                $booking_keys = array( 'hr_selected_rate_id', 'hr_tour_rate_id', 'hr_airport_transfer_rate_id', 'hr_package_deal_id', 'hr_motorbike_rental_id', 'hr_boat_rental_id', 'hr_self_drive_rental_id', 'hr_port_transfer_id', 'hr_destination_tour_id', 'hr_pickup_dropoff_service_id' );
                foreach ( $cart->get_cart() as $line ) {
                        foreach ( $booking_keys as $key ) {
                                if ( ! empty( $line[ $key ] ) ) {
                                        // WooCommerce line_total already includes quantity, discounts and currency conversion.
                                        $base += max( 0, (float) ( $line['line_total'] ?? 0 ) );
                                        break;
                                }
                        }
                }
                $amount = round( $base * $percent / 100, wc_get_price_decimals() );
                if ( $amount <= 0 ) { return; }
                $label = rtrim( rtrim( number_format( $percent, 2, '.', '' ), '0' ), '.' );
                $fee = $cart->fees_api()->add_fee( array(
                        'id' => self::FEE_ID,
                        'name' => sprintf( __( 'Plus %s%% Convenience Fee', 'hotel-rates' ), $label ),
                        'amount' => $amount,
                        'taxable' => false,
                ) );
                if ( ! is_wp_error( $fee ) ) {
                        $fee->hr_percent = $percent;
                        $fee->hr_base = $base;
                }
        }

        public function save_fee_metadata( $item, $fee_key, $fee, $order ) {
                if ( self::FEE_ID !== $fee_key ) { return; }
                $item->add_meta_data( '_hr_convenience_fee_percent', $fee->hr_percent, true );
                $item->add_meta_data( '_hr_convenience_fee_base', $fee->hr_base, true );
        }

        public function position_order_fee( $totals, $order, $tax_display ) {
                foreach ( $order->get_items( 'fee' ) as $id => $fee ) {
                        if ( '' === $fee->get_meta( '_hr_convenience_fee_percent', true ) ) { continue; }
                        $key = 'fee_' . $id;
                        if ( ! isset( $totals[ $key ], $totals['cart_subtotal'] ) ) { continue; }
                        $row = $totals[ $key ];
                        unset( $totals[ $key ] );
                        $ordered = array();
                        foreach ( $totals as $total_key => $total ) {
                                $ordered[ $total_key ] = $total;
                                if ( 'cart_subtotal' === $total_key ) { $ordered[ $key ] = $row; }
                        }
                        $totals = $ordered;
                }
                return $totals;
        }

        /** Move the native fee row without replacing checkout templates or totals. */
        public function enqueue_checkout_position() {
                if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
                $label = __( 'Plus %s%% Convenience Fee', 'hotel-rates' );
                $parts = explode( '%s', $label, 2 );
                $prefix = $parts[0];
                $suffix = isset( $parts[1] ) ? str_replace( '%%', '%', $parts[1] ) : '';
                wp_enqueue_script( 'jquery' );
                wp_add_inline_script( 'jquery', 'jQuery(function($) {
                        function positionFee() {
                                $(".woocommerce-checkout-review-order-table").each(function() {
                                        var table = $(this), subtotal = table.find("tr.cart-subtotal").first();
                                        if (!subtotal.length) { return; }
                                        table.find("tr.fee").each(function() {
                                                var text = $(this).find("th").text().trim();
                                                if (text.indexOf(' . wp_json_encode( $prefix ) . ') === 0 && text.indexOf(' . wp_json_encode( $suffix ) . ') !== -1) {
                                                        $(this).insertAfter(subtotal);
                                                }
                                        });
                                });
                        }
                        positionFee();
                        $(document.body).on("updated_checkout", positionFee);
                });' );
        }
}
