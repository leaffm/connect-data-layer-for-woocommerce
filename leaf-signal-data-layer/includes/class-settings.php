<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Leaf_CDL_Settings {

    const OPTION_KEY = 'leaf_cdl_settings';

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',    [ $this, 'register_settings' ] );
        add_action( 'admin_notices', [ $this, 'maybe_show_setup_notice' ] );
    }

    /**
     * Retrieves a single setting value.
     */
    public static function get( $key, $default = '' ) {
        $options = get_option( self::OPTION_KEY, [] );
        return $options[ $key ] ?? $default;
    }

    public function add_settings_page() {
        // viewBox is cropped tightly around the leaf path so it fills the menu icon area.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="265 270 660 650">'
             . '<path fill-rule="evenodd" clip-rule="evenodd" fill="#000000" d="'
             . 'M804.3,361.1c-98.4-84.1-242.9-101.7-361.5-33.3c-64.8,37.4-111.3,94.5-136.2,159.3'
             . 'c65.6,56.1,162,67.8,241,22.1L804.3,361.1z '
             . 'M704.8,896.4C826.9,853.2,914.3,736.8,914.3,600c0-74.9-26.2-143.6-69.9-197.6'
             . 'C763.1,431.2,704.8,508.8,704.8,600V896.4z '
             . 'M290.9,542.5c-23.7,127.3,33.4,261.2,151.9,329.6c64.8,37.4,137.5,49.1,206.1,38.3'
             . 'c15.8-84.8-22.3-174.1-101.3-219.7L290.9,542.5z'
             . '"/></svg>';

        add_menu_page(
            'Leaf Signal',
            'Leaf Signal',
            'manage_options',
            'leaf-cdl-settings',
            [ $this, 'render_page' ],
            'data:image/svg+xml;base64,' . base64_encode( $svg ),
            80
        );
    }

    public function register_settings() {
        register_setting( 'leaf_cdl_group', self::OPTION_KEY, [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );

        add_settings_section( 'leaf_cdl_main', 'Tracking Configuration', null, 'leaf-cdl-settings' );

        add_settings_field(
            'leaf_cdl_script_url',
            'Tracking Script URL',
            [ $this, 'render_field' ],
            'leaf-cdl-settings',
            'leaf_cdl_main',
            [ 'key' => 'script_url' ]
        );

        add_settings_section( 'leaf_cdl_events', 'Events', [ $this, 'render_events_section' ], 'leaf-cdl-settings' );

        $event_fields = [
            'event_view_item'         => 'View Item',
            'event_add_to_cart'       => 'Add to Cart',
            'event_initiate_checkout' => 'Begin Checkout',
            'event_purchase'          => 'Purchase',
        ];

        foreach ( $event_fields as $key => $label ) {
            add_settings_field(
                'leaf_cdl_' . $key,
                $label,
                [ $this, 'render_checkbox_field' ],
                'leaf-cdl-settings',
                'leaf_cdl_events',
                [ 'key' => $key ]
            );
        }
    }

    public function sanitize( $input ) {
        $event_keys = [
            'event_add_to_cart',
            'event_view_item',
            'event_initiate_checkout',
            'event_purchase',
        ];

        $output = [
            'script_url' => esc_url_raw( $input['script_url'] ?? '' ),
        ];

        foreach ( $event_keys as $key ) {
            $output[ $key ] = isset( $input[ $key ] ) ? '1' : '0';
        }

        return $output;
    }

    public function render_events_section() {
        echo '<p>Enable or disable individual tracking events.</p>';
    }

    public function render_checkbox_field( $args ) {
        $value = self::get( $args['key'], '1' );
        printf(
            '<input type="checkbox" name="%s[%s]" value="1"%s>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $args['key'] ),
            checked( '1', $value, false )
        );
    }

    public function render_field( $args ) {
        $value = self::get( $args['key'] );
        printf(
            '<input type="text" name="%s[%s]" value="%s" class="regular-text">',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $args['key'] ),
            esc_attr( $value )
        );
    }

    /**
     * Shows an admin notice if the script URL has not been configured yet.
     */
    public function maybe_show_setup_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( self::get( 'script_url' ) ) return;

        $settings_url = admin_url( 'admin.php?page=leaf-cdl-settings' );
        printf(
            '<div class="notice notice-warning"><p><strong>Leaf Signal</strong> is active but not configured. <a href="%s">Add your tracking script URL</a> to start tracking.</p></div>',
            esc_url( $settings_url )
        );
    }

    public function render_page() {
        ?>
        <div class="wrap">
            <h1>Leaf Signal</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'leaf_cdl_group' );
                do_settings_sections( 'leaf-cdl-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
