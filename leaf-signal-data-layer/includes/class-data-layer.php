<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Leaf_CDL {

    public function __construct() {
        add_action( 'wp_head',                        [ $this, 'generate_page_view' ], 1 );
        add_action( 'woocommerce_add_to_cart',        [ $this, 'queue_add_to_cart' ], 10, 6 );
        add_action( 'wp_footer',                      [ $this, 'flush_add_to_cart_queue' ] );
        add_action( 'woocommerce_before_single_product', [ $this, 'generate_view_item' ] );
        add_action( 'woocommerce_before_checkout_form',  [ $this, 'generate_initiate_checkout' ] );
        add_action( 'woocommerce_thankyou',           [ $this, 'generate_purchase' ] );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function get_store_id() {
        $url = preg_replace( '#^https?://#', '', get_home_url() );
        $url = preg_replace( '#^www\.#i', '', $url );
        return sprintf( '%08x', crc32( $url ) );
    }

    private function get_page_type() {
        if ( is_front_page() )       return 'home';
        if ( is_shop() )             return 'collections';
        if ( is_product() )          return 'product';
        if ( is_cart() )             return 'cart';
        if ( is_checkout() )         return 'checkout';
        if ( is_product_category() ) return 'category collections';
        if ( is_product_tag() )      return 'tag collections';
        return 'other';
    }

    private function get_product_brand( $product_id ) {
        $terms = wp_get_post_terms( $product_id, 'brand', [ 'fields' => 'names' ] );
        if ( $terms && ! is_wp_error( $terms ) ) {
            return reset( $terms );
        }
        return '';
    }

    /**
     * Returns the first non-empty variation attribute value, or the fallback.
     */
    private function get_variation_label( $variation_id, $fallback ) {
        if ( ! $variation_id ) return $fallback;
        $variation = wc_get_product( $variation_id );
        if ( ! $variation ) return $fallback;
        foreach ( $variation->get_attributes() as $value ) {
            if ( ! empty( $value ) ) return $value;
        }
        return $fallback;
    }

    private function get_image_url( $product ) {
        $attrs = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
        return $attrs ? $attrs[0] : '';
    }

    /**
     * Builds a standard item array for the data layer.
     */
    private function build_item( $product, $quantity, $variation_name = null ) {
        if ( $variation_name === null ) {
            $variation_name = $product->get_name();
        }
        return [
            'item_id'      => (string) ( $product->get_sku() ?: $product->get_id() ),
            'item_name'    => $product->get_name(),
            'item_variant' => $variation_name,
            'currency'     => get_woocommerce_currency(),
            'price'        => floatval( $product->get_price() ),
            'item_brand'   => $this->get_product_brand( $product->get_id() ),
            'image_url'    => $this->get_image_url( $product ),
            'product_url'  => get_permalink( $product->get_id() ),
            'quantity'     => intval( $quantity ),
        ];
    }

    /**
     * Outputs a dataLayer.push() script tag.
     */
    private function output_script( $data ) {
        echo "\n<script>\n";
        echo "window.dataLayer = window.dataLayer || [];\n";
        echo 'window.dataLayer.push(' . wp_json_encode( $data ) . ');' . "\n";
        echo "</script>\n";
    }

    /**
     * Normalizes a WooCommerce address array into the standard shape.
     * Pass an empty array to get a null-filled skeleton.
     */
    private function map_address( array $addr ) {
        if ( empty( $addr ) ) {
            return array_fill_keys(
                [ 'fullName', 'firstName', 'lastName', 'address1', 'address2',
                  'street', 'city', 'state', 'state_code', 'zip',
                  'country', 'country_code', 'phone' ],
                null
            );
        }
        $first  = $addr['first_name'] ?? '';
        $last   = $addr['last_name'] ?? '';
        $addr1  = $addr['address_1'] ?? '';
        $addr2  = $addr['address_2'] ?? '';
        return [
            'fullName'     => trim( "$first $last" ) ?: null,
            'firstName'    => $first ?: null,
            'lastName'     => $last ?: null,
            'address1'     => $addr1 ?: null,
            'address2'     => $addr2 ?: null,
            'street'       => trim( "$addr1 $addr2" ) ?: null,
            'city'         => $addr['city']     ?? null,
            'state'        => $addr['state']    ?? null,
            'state_code'   => $addr['state']    ?? null,
            'zip'          => $addr['postcode'] ?? null,
            'country'      => $addr['country']  ?? null,
            'country_code' => $addr['country']  ?? null,
            'phone'        => $addr['phone']    ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // Page View
    // -------------------------------------------------------------------------

    public function generate_page_view() {
        $script_url = Leaf_CDL_Settings::get( 'script_url' );
        if ( ! $script_url ) return;

        // page_title and page_location are set in JS so they're always accurate.
        $data = [
            'pageType'      => $this->get_page_type(),
            'event'         => 'page_view',
            'page_title'    => '',
            'page_location' => '',
            'shop_id'       => $this->get_store_id(),
        ];
        ?>
        <script id="leaf-cdl-page-view" data-nowprocket>
        (function() {
            var data = <?php echo wp_json_encode( $data ); ?>;
            data.page_title    = document.title;
            data.page_location = window.location.href;

            var script = document.createElement('script');
            script.src = <?php echo wp_json_encode( $script_url ); ?>;
            script.onload = function() {
                setTimeout(function() {
                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push(data);
                }, 250);
            };
            document.head.appendChild(script);
        })();
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Add to Cart
    // Queued in WC session on the server-side hook, then flushed on wp_footer.
    // This works for both standard form submits and AJAX add-to-cart flows.
    // -------------------------------------------------------------------------

    public function queue_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) return;

        $variation_name = $product->get_name();
        if ( is_array( $variation ) ) {
            foreach ( $variation as $value ) {
                if ( ! empty( $value ) ) {
                    $variation_name = $value;
                    break;
                }
            }
        }

        $event = [
            'event'     => 'add_to_cart',
            'ecommerce' => [
                'shop_id'     => $this->get_store_id(),
                'value'       => floatval( $product->get_price() ) * intval( $quantity ),
                'currency'    => get_woocommerce_currency(),
                'affiliation' => 'Online Store',
                'items'       => [ $this->build_item( $product, $quantity, $variation_name ) ],
            ],
        ];

        $queue   = WC()->session->get( 'leaf_cdl_atc_queue', [] );
        $queue[] = $event;
        WC()->session->set( 'leaf_cdl_atc_queue', $queue );
    }

    public function flush_add_to_cart_queue() {
        if ( ! WC()->session ) return;
        $queue = WC()->session->get( 'leaf_cdl_atc_queue', [] );
        if ( empty( $queue ) ) return;

        WC()->session->set( 'leaf_cdl_atc_queue', [] );

        foreach ( $queue as $event ) {
            $this->output_script( $event );
        }
    }

    // -------------------------------------------------------------------------
    // View Item
    // -------------------------------------------------------------------------

    public function generate_view_item() {
        global $product;
        if ( ! $product ) return;

        $data = [
            'event'     => 'view_item',
            'pageType'  => 'Product',
            'ecommerce' => [
                'shop_id'     => $this->get_store_id(),
                'currency'    => get_woocommerce_currency(),
                'affiliation' => 'Online Store',
                'value'       => floatval( $product->get_price() ),
                'items'       => [ $this->build_item( $product, 1 ) ],
            ],
        ];

        $this->output_script( $data );
    }

    // -------------------------------------------------------------------------
    // Initiate Checkout
    // -------------------------------------------------------------------------

    public function generate_initiate_checkout() {
        if ( empty( WC()->cart->get_cart() ) ) return;

        $items         = [];
        $total_revenue = 0;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product        = $cart_item['data'];
            $quantity       = $cart_item['quantity'];
            $variation_name = $product->get_name();

            foreach ( $cart_item['variation'] ?? [] as $value ) {
                if ( ! empty( $value ) ) {
                    $variation_name = $value;
                    break;
                }
            }

            $total_revenue += floatval( $product->get_price() ) * intval( $quantity );
            $items[]        = $this->build_item( $product, $quantity, $variation_name );
        }

        $data = [
            'event'     => 'initiate_checkout',
            'pageType'  => 'Initiate Checkout',
            'ecommerce' => [
                'shop_id'     => $this->get_store_id(),
                'currency'    => get_woocommerce_currency(),
                'value'       => floatval( $total_revenue ),
                'affiliation' => 'Online Store',
                'items'       => $items,
            ],
        ];

        $this->output_script( $data );
    }

    // -------------------------------------------------------------------------
    // Purchase
    // -------------------------------------------------------------------------

    public function generate_purchase( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $items = [];
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) continue;

            $variation_name = $this->get_variation_label( $item->get_variation_id(), $product->get_name() );
            $order_item     = $this->build_item( $product, $item->get_quantity(), $variation_name );

            // Override currency with the order's currency in case it differs.
            $order_item['currency'] = $order->get_currency();

            $items[] = $order_item;
        }

        $purchase_data = [
            'pageType'  => 'Thank You Page',
            'event'     => 'purchase',
            'ecommerce' => [
                'transaction_number'   => $order->get_order_number(),
                'transaction_id'       => $order->get_order_number(),
                'affiliation'          => 'Online Store',
                'shop_id'              => $this->get_store_id(),
                'gateway'              => $order->get_payment_method(),
                'value'                => floatval( $order->get_total() ),
                'currency'             => $order->get_currency(),
                'tax'                  => floatval( $order->get_total_tax() ),
                'shipping'             => floatval( $order->get_shipping_total() ),
                'transaction_subtotal' => floatval( $order->get_subtotal() ),
                'items'                => $items,
            ],
        ];

        $log_state_data = $this->build_log_state( $order );
        $order_id_int   = intval( $order_id );
        ?>
        <script>
        (function() {
            var sessionKey = 'leaf_cdl_purchase_<?php echo $order_id_int; ?>';
            if ( sessionStorage.getItem(sessionKey) ) return;
            sessionStorage.setItem(sessionKey, '1');

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(<?php echo wp_json_encode( $log_state_data ); ?>);
            window.dataLayer.push(<?php echo wp_json_encode( $purchase_data ); ?>);
        })();
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Log State (built server-side, pushed within the purchase script block)
    // -------------------------------------------------------------------------

    /**
     * Returns true if this is the customer's first order, false if they have ordered before.
     * Works for both guest and registered customers.
     */
    private function is_first_order( $order ) {
        $email = $order->get_billing_email();
        if ( ! $email ) return true;

        $orders = wc_get_orders( [
            'billing_email' => $email,
            'status'        => [ 'wc-completed', 'wc-processing' ],
            'limit'         => 2,
            'return'        => 'ids',
        ] );

        // More than 1 order means the current one isn't the first.
        return count( $orders ) <= 1;
    }

    private function build_log_state( $order ) {
        $data = [
            'event'         => 'logState',
            'logState'      => is_user_logged_in() ? 'Logged In' : 'Logged Out',
            'currency'      => $order->get_currency(),
            'customerEmail' => $order->get_billing_email(),
            'checkoutEmail' => $order->get_billing_email(),
            'isFirstOrder'  => $this->is_first_order( $order ),
            'customerInfo'  => $this->map_address( [] ),
            'shippingInfo'  => $this->map_address( [] ),
            'billingInfo'   => $this->map_address( [] ),
        ];

        // Populate customerInfo — only available for registered users.
        $customer = $order->get_user();
        if ( $customer ) {
            $billing_addr_1 = $customer->get_billing_address_1();
            $billing_addr_2 = $customer->get_billing_address_2();
            $data['customerInfo'] = [
                'fullName'     => trim( $customer->get_first_name() . ' ' . $customer->get_last_name() ) ?: null,
                'firstName'    => $customer->get_first_name() ?: null,
                'lastName'     => $customer->get_last_name() ?: null,
                'address1'     => $billing_addr_1 ?: null,
                'address2'     => $billing_addr_2 ?: null,
                'street'       => trim( "$billing_addr_1 $billing_addr_2" ) ?: null,
                'city'         => $customer->get_billing_city() ?: null,
                'state'        => $customer->get_billing_state() ?: null,
                'state_code'   => $customer->get_billing_state() ?: null,
                'zip'          => $customer->get_billing_postcode() ?: null,
                'country'      => $customer->get_billing_country() ?: null,
                'country_code' => $customer->get_billing_country() ?: null,
                'phone'        => $customer->get_billing_phone() ?: null,
            ];
        }

        $shipping = $order->get_address( 'shipping' );
        if ( $shipping ) {
            $data['shippingInfo'] = $this->map_address( $shipping );
        }

        $billing = $order->get_address( 'billing' );
        if ( $billing ) {
            $data['billingInfo'] = $this->map_address( $billing );
        }

        return $data;
    }
}
