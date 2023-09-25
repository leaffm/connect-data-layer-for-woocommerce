<?php
/*
Plugin Name: Leaf Connect Data Layer
Plugin URI: https://leafgrow.io/
Description: Generates a Data Layer for Woocommerce.
Version: 1.0
Author: Leaf
Author URI: https://leafgrow.io/
*/

/**
 * Removes protocols (http, https) and removes (www.) if it exists.
 *
 */
function remove_protocols_and_www( $url ) {
    /** Remove protocols (http and https) */
    $url = preg_replace( '#^https?://#', '', $url );

    /** Remove www. (if it exists) */
    $url = preg_replace( '#^www\.#i', '', $url );

    return $url;
}

/**
 * Generates the store id based on the store url domain
 * so we can add it in the data layer.
 *
 */
function get_store_id() {
    $store_url = get_home_url();
    $url = remove_protocols_and_www( $store_url );
    /** Calculate the CRC32 checksum of the page domain */
    $crc32_value = crc32( $url );

    /** Convert the CRC32 value to a hexadecimal representation */
    $store_id = sprintf( '%08x', $crc32_value );
    return $store_id;
}

/**
 * Defines the page type to use in the data layer.
 *
 */
function get_page_type() {
    $page_type = 'Page Type';
    if ( is_front_page() ) {
        /** The user is viewing the home page */
        $page_type = 'home';
    } elseif ( is_shop() ) {
        /** The user is viewing a page with a collection of products */
        $page_type = 'collections';
    } elseif ( is_product() ) {
        /** The user is viewing a single product page */
        $page_type = 'product';
    } elseif ( is_cart() ) {
        /** The user is viewing the cart page */
        $page_type = 'cart';
    } elseif ( is_checkout() ) {
        /** The user is viewing the checkout page */
        $page_type = 'checkout';
    } elseif ( is_product_category() ) {
        /** The user is viewing a category page with a list of products */
        $page_type = 'category collections';
    } elseif ( is_product_tag() ) {
        /** The user is viewing a tag page with a list of products */
        $page_type = 'tag collections';
    }
    return $page_type;
}

/**
 * Generates the page view event.
 *
 */
function generate_page_view() {
    $page_type = get_page_type();
    $store_id = get_store_id();
?>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'pageType': '<?php echo $page_type; ?>',
            'event': 'page_view',
            'page_title': document.title,
            'page_location': window.location.href,
            'shop_id': '<?php echo $store_id; ?>',
        });
    </script>
<?php
}
/** Add action to track the Page View event */
add_action( 'wp_head', 'generate_page_view' );

/**
 * Gets the brand of the product
 *
 */
function get_product_brand( $product_id ) {
    $brand = '';
    /** Get the terms (including Brands) associated with the product */
    $terms = wp_get_post_terms( $product_id, 'brand', array( 'fields' => 'names' ) );
    if ( $terms && ! is_wp_error( $terms ) ) {
        /** Get the name of the brand */
        $brand = reset( $terms );
    }
    return $brand;
}

/**
 * Action to track add to cart event
 *
*/
function generate_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
    $product = wc_get_product( $product_id );

    $product_sku = $product->get_sku();
    if ( empty( $product_sku ) ) {
        /** The SKU is empty, null or undefined so we use the product id. */
        $product_sku = $product_id;
    }
    $product_name = $product->get_name();
    $currency = get_woocommerce_currency();
    $price = $product->get_price();
    $total_revenue = $price * $quantity;
    $image_url = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
    $product_url = get_permalink( $product->get_id() );
    $brand = get_product_brand( $product_id );

    $variation_name = $product_name;
	if ( is_array( $variation ) && ! empty( $variation ) ) {
		foreach ( $variation as $key => $value ) {
			if ( ! empty( $value ) ) {
				$variation_name = $value;
			}
		}
	}

    $store_id = get_store_id();
?>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'event': 'add_to_cart',
            'ecommerce': {
                'shop_id': '<?php echo $store_id ?>',
                'value': '<?php echo $total_revenue; ?>',
                'currency': '<?php echo $currency; ?>',
                'affiliation': 'Online Store',
                'items': [
                    {
                        'item_id': '<?php echo $product_sku; ?>',
                        'item_name': '<?php echo $product_name; ?>',
                        'item_variant': '<?php echo $variation_name; ?>',
                        'currency': '<?php echo $currency; ?>',
                        'price': '<?php echo $price; ?>',
                        'item_brand': '<?php echo $brand; ?>',
                        'image_url': '<?php echo $image_url[0]; ?>',
                        'product_url': '<?php echo $product_url; ?>',
                        'quantity': '<?php echo $quantity; ?>'
                    }
                ]
            },
        });
    </script>
<?php
} /** generate_add_to_cart ends */

/** hook that will trigger the add_to_cart event */
add_action( 'woocommerce_add_to_cart', 'generate_add_to_cart', 10, 6 );

/**
 * Action to track the view content event.
 *
*/
function generate_view_content() {
    // Define Page type
    $page_type = get_page_type();
    /** is_product() verifies if we are in a product page and if the product has loaded so we can use the $product object. */
    if ( is_product() ) {
        global $product;

        $product_sku = $product->get_sku();
        if ( empty( $product_sku ) ) {
            /** the SKU is empty, null or undefined */
            $product_sku = $product->get_id();
        }
        $product_name = $product->get_name();
        $currency = get_woocommerce_currency();
        $price = $product->get_price();
        $image = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
        $product_url = get_permalink( $product->get_id() );
        $brand = get_product_brand( $product->get_id() );

        $store_id = get_store_id();
?>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'event': 'view_item',
            'pageType': '<?php echo $page_type; ?>',
            'ecommerce': {
                'shop_id': '<?php echo $store_id; ?>',
                'currency': '<?php echo $currency; ?>',
                'affiliation': 'Online Store',
                'value': '<?php echo $price; ?>',
                'items': [
                    {
                        'item_id': '<?php echo $product_sku; ?>',
                        'item_name': '<?php echo $product_name; ?>',
                        'item_variant': '<?php echo $product_name; ?>',
                        'currency': '<?php echo $currency; ?>',
                        'price': '<?php echo $price; ?>',
                        'item_brand': '<?php echo $brand; ?>',
                        'image_url': '<?php echo $image[0]; ?>',
                        'product_url': '<?php echo $product_url; ?>',
                    },
                ]
            },
        });
    </script>
<?php

    } /** is_product conditional ends */
} /** generate_view_content ends */

/** hook to trigger the view_content event */
add_action( 'woocommerce_before_single_product', 'generate_view_content' );

/**
 * Generates the initiate_checkout event
 *
*/
function generate_initiate_checkout() {
    // Define Page type
    $page_type = get_page_type();
    /** Verifies the cart is not empty. */
    if ( ! empty( WC()->cart->get_cart() ) ) {
        $currency = get_woocommerce_currency();
        $cart_items = array();
        $total_revenue = 0;

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            $product_sku = $product->get_sku();
            if ( empty( $product_sku ) ) {
                /** the SKU is empty, null or undefined so we use the product id. */
                $product_sku = $product->get_id();
            }
            $product_name = $product->get_name();
            $product_quantity = $cart_item['quantity'];
            $brand = get_product_brand( $product->get_id() );

            $product_price = $product->get_price();
            $total_product_value = $product_price * $product_quantity;
            $total_revenue += $total_product_value;

            $product_url = get_permalink( $product->get_id() );
            $image = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
            $variation = $cart_item['variation'];
            $variation_name = $product->get_name();
            foreach ( $variation as $variation_key => $variation_value ) {
                if ( ! empty( $variation_value ) ) {
                    $variation_name = $variation_value;
                }
            }

            $cart_items[] = array(
                'item_id' => $product_sku,
                'item_name' => $product_name,
                'item_variant' => $variation_name,
                'currency' => $currency,
                'price' => $product_price,
                'item_brand' => $brand,
                'image_url' => $image[0],
                'product_url' => $product_url,
                'quantity' => $product_quantity,
            );
        }

        $store_id = get_store_id();

        $data_layer = array(
            'event' => 'initiate_checkout',
            'pageType' => $page_type,
            'ecommerce' => array(
                'shop_id' => $store_id,
                'currency' => $currency,
                'value' => $total_revenue,
                'affiliation' => 'Online Store',
                'items' => $cart_items,
            )
        );
    }

?>
    <script>
        // Gets a Cookie
        function getCookie(cname) {
            let name = cname + '=';
            let decodedCookie = decodeURIComponent(document.cookie);
            let ca = decodedCookie.split(';');
            for(let i = 0; i <ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
            }
            return '';
        }
        // Sets a Cookie
        function setCookie(cvalue, cname) {
            const d = new Date();
            d.setTime(d.getTime() + (365*24*60*60*1000));
            var expires = 'expires='+ d.toUTCString();
            document.cookie = cname + '=' + cvalue + ';' + expires + ';path=/';
        }
        var hasInitiatedCheckout = getCookie('hasInitiatedCheckout');
        if (!hasInitiatedCheckout) {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(<?php echo json_encode( $data_layer ); ?>);
            setCookie(true, 'hasInitiatedCheckout');
        }
    </script>
<?php

} /** generate_initiate_checkout ends */

/** Hook that will trigger the initiate_checkout event */
add_action( 'woocommerce_before_checkout_form', 'generate_initiate_checkout' );

/**
 * Generates the logState event
 *
*/
function generate_log_state( $order ) {
    /** Builds the object */
    $data = array(
        'event' => 'logState',
        'logState' => 'Logged Out',
        'currency' => $order->get_currency(),
        'customerEmail' => $order->get_billing_email(),
        'checkoutEmail' => $order->get_billing_email(),
        'customerType' => 'New',
        'customerInfo' => array(
            'fullName' => null,
            'firstName' => null,
            'lastName' => null,
            'address1' => null,
            'address2' => null,
            'street' => null,
            'city' => null,
            'state' => null,
            'state_code' => null,
            'zip' => null,
            'country' => null,
            'country_code' => null,
            'phone' => null
        ),
        'shippingInfo' => array(
            'fullName' => null,
            'firstName' => null,
            'lastName' => null,
            'address1' => null,
            'address2' => null,
            'street' => null,
            'city' => null,
            'state' => null,
            'state_code' => null,
            'zip' => null,
            'country' => null,
            'phone' => null
        ),
        'billingInfo' => array(
            'fullName' => null,
            'firstName' => null,
            'lastName' => null,
            'address1' => null,
            'address2' => null,
            'street' => null,
            'city' => null,
            'state' => null,
            'state_code' => null,
            'zip' => null,
            'country' => null,
            'phone' => null
        )
    );

    /** Gets the customer object */
    $customer = $order->get_user();
    $data['customerInfo'] = array(
        'fullName' => $customer->get_first_name() . ' ' . $customer->get_last_name(),
        'firstName' => $customer->get_first_name(),
        'lastName' => $customer->get_last_name(),
        'address1' => $customer->get_billing_address_1(),
        'address2' => $customer->get_billing_address_2(),
        'street' => $customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2(),
        'city' => $customer->get_billing_city(),
        'state' => $customer->get_billing_state(),
        'state_code' => $customer->get_billing_state(),
        'zip' => $customer->get_billing_postcode(),
        'country' => $customer->get_billing_country(),
        'country_code' => $customer->get_billing_country()
    );

    /** Gets the shipping address */
    $shipping_address = $order->get_address( 'shipping' );
    if ( $shipping_address ) {
        $data['shippingInfo'] = array(
            'fullName' => $shipping_address['first_name'] . ' ' . $shipping_address['last_name'],
            'firstName' => $shipping_address['first_name'],
            'lastName' => $shipping_address['last_name'],
            'address1' => $shipping_address['address_1'],
            'address2' => $shipping_address['address_2'],
            'street' => $shipping_address['address_1'] . ' ' . $shipping_address['address_2'],
            'city' => $shipping_address['city'],
            'state' => $shipping_address['state'],
            'state_code' => $shipping_address['state'],
            'zip' => $shipping_address['postcode'],
            'country' => $shipping_address['country'],
            'country_code' => $shipping_address['country'],
            'phone' => $shipping_address['phone']
        );
    }

    /** Gets Billing address */
    $billing_address = $order->get_address( 'billing' );
    if ( $billing_address ) {
        $data['billingInfo'] = array(
            'fullName' => $billing_address['first_name'] . ' ' . $billing_address['last_name'],
            'firstName' => $billing_address['first_name'],
            'lastName' => $billing_address['last_name'],
            'address1' => $billing_address['address_1'],
            'address2' => $billing_address['address_2'],
            'street' => $billing_address['address_1'] . ' ' . $billing_address['address_2'],
            'city' => $billing_address['city'],
            'state' => $billing_address['state'],
            'state_code' => $billing_address['state'],
            'zip' => $billing_address['postcode'],
            'country' => $billing_address['country'],
            'country_code' => $billing_address['country'],
            'phone' => $billing_address['phone']
        );
    }
?>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(<?php echo json_encode( $data ); ?>);
    </script>
<?php
} /** generate_log_state Ends */

/** gets the variation's name of the product in the order. */
function get_variation_name( $variation_id, $product_name ) {
    $variation_name = $product_name;
    if ( $variation_id ) {
        $variation = wc_get_product( $variation_id );
        $attributes = $variation->get_attributes();
        foreach ( $attributes as $key => $value ) {
			if ( ! empty( $value ) ) {
				$variation_name = $value;
			}
		}
    }
    return $variation_name;
}

/** Generates the Purchase event */
function generate_purchase_event( $order_id ) {
    // Define Page type
    $page_type = get_page_type();
    /** Check if the thankyou hook has already been triggered for this order */
    if ( ! WC()->session->get( 'thankyou_triggered_' . $order_id ) ) {
        /** Get the order object */
        $order = wc_get_order( $order_id );

        /** Generate the log state event. */
        generate_log_state( $order );

        /** Get the transaction ID and value */
        $transaction_id = $order->get_order_number();
        $value = $order->get_total();

        /** Get the items in the order */
        $items = array();
        $order_items = $order->get_items();
        foreach ( $order_items as $item ) {
            /** Get the product id and name */
            $product = $item->get_product();
            $product_id = $product->get_id();
            $product_sku = $product->get_sku();
            if ( empty( $product_sku ) ) {
                /** the SKU is empty, null or undefined */
                $product_sku = $product_id;
            }
            $product_name = $product->get_name();

            /** Get the variation id and name */
            $variation_id = $item->get_variation_id();
            $variation_name = get_variation_name( $variation_id, $product_name );

            /** Get the product url and image */
            $product_url = get_permalink( $product_id );
            $image = wp_get_attachment_image_src( $product->get_image_id(), 'full' );

            /** Get the brand of the product */
            $brand = get_product_brand( $product_id );

            $items[] = array(
                'item_id' => $product_sku,
                'item_name' => $product_name,
                'item_variant' => $variation_name,
                'currency' => $order->get_currency(),
                'price' => $product->get_price(),
                'item_brand' => $brand,
                'image_url' => $image[0],
                'product_url' => $product_url,
                'quantity' => $item->get_quantity(),
            );
        }

        $store_id = get_store_id();

        /** Build the data layer event */
        $data_layer = array(
            'pageType' => 'thank you page',
            'event' => 'purchase',
            'ecommerce' => array(
                'transaction_number' => $transaction_id,
                'transaction_id' => $transaction_id,
                'affiliation' => 'Online Store',
                'shop_id' => $store_id,
                'gateway' => $order->get_payment_method(),
                'value' => $value,
                'currency' => $order->get_currency(),
                'tax' => $order->get_total_tax(),
                'shipping' => $order->get_shipping_total(),
                'transaction_subtotal' => $order->get_subtotal(),
                'items' => $items,
            ),
        );

        /** Set the session variable to indicate that the thankyou hook has been triggered */
        WC()->session->set( 'thankyou_triggered_' . $order_id, true );
    }
?>
    <script>
        // Set COOKIE
        function setCookie(cvalue, cname) {
            const d = new Date();
            d.setTime(d.getTime() + (365*24*60*60*1000));
            var expires = 'expires='+ d.toUTCString();
            document.cookie = cname + '=' + cvalue + ';' + expires + ';path=/';
        }
        var orderId = <?php echo $order_id; ?>;
        var sessionItem = 'thankyou_triggered' + orderId;
        if (!sessionStorage.getItem(sessionItem)) {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(<?php echo json_encode( $data_layer ); ?>);
            sessionStorage.setItem(sessionItem, true);
            setCookie(false, 'hasInitiatedCheckout');
        }
    </script>
<?php
} /** generate_purchase_event ends */

/** Hook that will trigger the purchase event when this is completed. */
add_action( 'woocommerce_thankyou', 'generate_purchase_event' );