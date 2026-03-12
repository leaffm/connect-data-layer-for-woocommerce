<?php
/**
 * Plugin Name: Leaf Signal
 * Plugin URI:  https://leafgrow.io/
 * Description: Generates a WooCommerce Data Layer for ecommerce tracking.
 * Version:     2.0.0
 * Author:      Leaf
 * Author URI:  https://leafgrow.io/
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LEAF_CDL_DIR', plugin_dir_path( __FILE__ ) );

require_once LEAF_CDL_DIR . 'includes/class-settings.php';
require_once LEAF_CDL_DIR . 'includes/class-data-layer.php';

function leaf_cdl_init() {
    new Leaf_CDL_Settings();
    new Leaf_CDL();
}
add_action( 'plugins_loaded', 'leaf_cdl_init' );
