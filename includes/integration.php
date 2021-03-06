<?php

/**
 * Conversion tracking integration class
 *
 * @author Tareq Hasan
 */
class WeDevs_WC_Tracking_Integration extends WC_Integration {

    function __construct() {

        $this->id = 'wc_conv_tracking';
        $this->method_title = __( 'Conversion Tracking Pixel', 'woocommerce-conversion-tracking' );
        $this->method_description = __( 'Various conversion tracking pixel integration like Facebook Ad, Google AdWords, etc. Insert your scripts codes here:', 'woocommerce-conversion-tracking' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        add_action( 'init', array($this, "set_lzhash"));

        // Save settings if the we are in the right section
        if ( isset( $_POST[ 'section' ] ) && $this->id === $_POST[ 'section' ] ) {
            add_action( 'woocommerce_update_options_integration', array($this, 'process_admin_options') );
        }

        add_action( 'woocommerce_product_options_reviews', array($this, 'product_options') );
        add_action( 'woocommerce_process_product_meta', array($this, 'product_options_save'), 10, 2 );

        add_action( 'woocommerce_registration_redirect', array($this, 'wc_redirect_url') );
        add_action( 'template_redirect', array($this, 'track_registration') );
        add_action( 'wp_head', array($this, 'code_handler') );
        add_action( 'wp_footer', array($this, 'code_handler') );
        add_action( 'woocommerce_thankyou', array($this, 'thankyou_page') );
    }

    /**
     * WooCommerce settings API fields for storing our codes
     *
     * @return void
     */
    function init_form_fields() {
        $this->form_fields = array(
            'position' => array(
                'title'       => __( 'Tags Position', 'woocommerce-conversion-tracking' ),
                'description' => __( 'Select which position in your page you want to insert the tag', 'woocommerce-conversion-tracking' ),
                'desc_tip'    => true,
                'id'          => 'position',
                'type'        => 'select',
                'options'     => array(
                    'head'    => /* translators: %s: tag name */
                                 sprintf( __( 'Inside %s tag', 'woocommerce-conversion-tracking' ), 'head' ),
                    'footer'  => /* translators: %s: tag name */
                                 sprintf( __( 'Inside %s tag', 'woocommerce-conversion-tracking' ), 'body' )
                )
            ),
            'cart' => array(
                'title'       => sprintf( /* translators: %s: page name */
                                   __( 'Tags for %s', 'woocommerce-conversion-tracking' ),
                                   __( 'View Cart', 'woocommerce-conversion-tracking' )
                                 ),
                'description' => __( 'Adds script on the cart page', 'woocommerce-conversion-tracking' ),
                'desc_tip'    => true,
                'id'          => 'cart',
                'type'        => 'textarea',
            ),
            'webhook' => array(
                'id'            =>  'webhook',
                'type'          =>  'url',
                'title'         =>  __( 'Webhook URL for Purchase', 'woocommerce-conversion-tracking' ),
                'desc_tip'      =>  __( 'URL that will be called when the order has been completed' ),
                'description'   =>  sprintf("%s <br /> %s <br /> %s",
                                        sprintf(
                                            __( '&raquo; You can use dynamic values: %s', 'woocommerce-conversion-tracking' ),
                                            '<code>{order_number}</code>, <code>{order_total}</code>, <code>{order_subtotal}</code>, <code>{currency}</code>, <code>{lzhash}</code>.'
                                        ),
                                        sprintf(
                                            __( '&raquo; %s', 'woocommerce-conversion-tracking' ),
                                            'This plugin will catch the <code>lzhash</code> from the <code>QUERY_STRING</code> then store it in a <code>Cookie</code> for <code>10 days</code>, and this <code>Webhook</code> will be called upon order has been completed.'
                                        ),
                                        sprintf(
                                            __( '&raquo; %s', 'woocommerce-conversion-tracking' ),
                                            'All you need is sharing any of your woocoomerce urls and appending <code>?lzhash=SOME_CODE_HERE</code> to it, <br /> i.e <code>http://site.com/product-1/?lzhash=51264a8s0</code>.'
                                        )
                                    ),
            ),
            'checkout' => array(
                'title'       => sprintf( /* translators: %s: page name */
                                   __( 'Tags for %s', 'woocommerce-conversion-tracking' ),
                                   __( 'Purchase', 'woocommerce-conversion-tracking' )
                                 ),
                'desc_tip'    => __( 'Adds script on the purchase success page', 'woocommerce-conversion-tracking' ),
                'description' => sprintf( /* translators: %s: dynamic values */
                                   __( 'You can use dynamic values: %s', 'woocommerce-conversion-tracking' ),
                                   '<code>{order_number}</code>, <code>{order_total}</code>, <code>{order_subtotal}</code>, <code>{currency}</code>, <code>{lzhash}</code>'
                                 ),
                'id'          => 'checkout',
                'type'        => 'textarea',
            ),
            'reg' => array(
                'title'       => sprintf( /* translators: %s: page name */
                                   __( 'Tags for %s', 'woocommerce-conversion-tracking' ),
                                   __( 'User Registration', 'woocommerce-conversion-tracking' )
                                 ),
                'description' => __( 'Adds script on the successful registraion page', 'woocommerce-conversion-tracking' ),
                'desc_tip'    => true,
                'id'          => 'registration',
                'type'        => 'textarea',
            ),
        );
    }

    /**
     * Save callback of Woo integration code settings API
     *
     * @param string $key
     * @return string
     */
    function validate_textarea_field( $key, $value ) {
        $text = trim( stripslashes( $_POST[$this->plugin_id . $this->id . '_' . $key] ) );

        return $text;
    }

    /**
     * Saves tracking code of single product
     *
     * @uses woocommerce_process_product_meta
     * @param int $post_id
     */
    function product_options_save( $post_id ) {

        if ( isset( $_POST['_wc_conv_track'] ) ) {
            $value = trim( $_POST['_wc_conv_track'] );
            update_post_meta( $post_id, '_wc_conv_track', $value );
        }
    }

    /**
     * Textarea input box on product page
     *
     * Creates new textarea on WooCommerce product advanced tab for storing
     * conversion tracking pixel for single product
     *
     * @uses woocommerce_product_options_reviews
     */
    function product_options() {

        echo '<div class="options_group">';

        woocommerce_wp_textarea_input( array(
            'id'          => '_wc_conv_track',
            'label'       => __( 'Conversion Tracking Code', 'woocommerce-conversion-tracking' ),
            'desc_tip'    => true,
            'description' => __( 'Insert conversion tracking code for this product.', 'woocommerce-conversion-tracking' )
                             .sprintf(
                             /* translators: %s: dynamic values */
                               __( 'You can use dynamic values: %s', 'woocommerce-conversion-tracking' ),
                               '<code>{order_number}</code>, <code>{order_total}</code>, <code>{order_subtotal}</code>, <code>{currency}</code>, <code>{lzhash}</code>'
                             )
        ) );

        echo '</div>';
    }

    /**
     * Print registration code when particular GET tag exists
     *
     * @uses add_action()
     * @return void
     */
    function track_registration() {
        if ( isset( $_GET['_wc_user_reg'] ) && $_GET['_wc_user_reg'] == 'true' ) {
            add_action( 'wp_head', array($this, 'print_reg_code') );
        }
    }

    /**
     * Set the lzhash [store it in cookie]
     *
     * @since 1.1
     *
     * @return string
     */
    function set_lzhash() {
        if ( empty($_GET["lzhash"]) ) {
            return ;
        }
        if ( ! empty($_COOKIE["_wc_lzhash"]) && ($_COOKIE["_wc_lzhash"] == $_GET["lzhash"]) ) {
            return ;
        }
        setcookie("_wc_lzhash", $_GET["lzhash"], time() + (3600 * 24 * 10), "/");
    }

    /**
     * Return the current lzhash code
     *
     * @since 1.1
     *
     * @return string
     */
    function get_lzhash() {
        return isset($_COOKIE["_wc_lzhash"]) ? $_COOKIE["_wc_lzhash"] : $_GET["lzhash"];
    }

    /**
     * Adds a url query arg to determine newly registered user
     *
     * @uses woocommerce_registration_redirect action
     * @param string $redirect
     * @return string
     */
    function wc_redirect_url( $redirect ) {
        if ( wp_get_referer() ) {
            $redirect = add_query_arg( array('_wc_user_reg' => 'true'), esc_url( wp_get_referer() ) );
        } else {
            $redirect = add_query_arg( array('_wc_user_reg' => 'true'), esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) ) );
        }

        return $redirect;
    }

    /**
     * Code print handler on HEAD tag
     *
     * It prints conversion tracking pixels on cart page, order received page
     * and single product page
     *
     * @uses wp_head
     * @return void
     */
    function code_handler() {

        $position = $this->get_option( 'position', 'head' );

        if ( 'wp_head' == current_filter() && $position != 'head' ) {
            return;
        } elseif ( 'wp_footer' == current_filter() && $position != 'footer' ) {
            return;
        }

        if ( is_cart() ) {

            echo $this->print_conversion_code( $this->get_option( 'cart' ) );

        } elseif ( is_order_received_page() ) {

            echo $this->print_conversion_code( $this->process_order_markdown( $this->get_option( 'checkout' ) ) );
        }
    }

    /**
     * Put product specific conversion tracking pixel in thank you page
     *
     * @param  int  $order_id
     *
     * @since 0.3
     *
     * @return void
     */
    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( $items = $order->get_items() ) {
            foreach ($items as $item) {
                $product = $order->get_product_from_item( $item );

                if ( ! $product ) {
                    continue;
                }

                $this->call_webhook($order_id);

                $code = get_post_meta( $product->id, '_wc_conv_track', true );

                if ( empty( $code ) ) {
                    continue;
                }

                echo $this->print_conversion_code( $this->process_product_markdown( $code, $product ) );
            }
        }
    }

    /**
     * Registration code print handler
     *
     * @return void
     */
    function print_reg_code() {
        echo $this->print_conversion_code( $this->get_option( 'reg' ) );
    }

    /**
     * Prints the code
     *
     * @param string $code
     *
     * @return void
     */
    function print_conversion_code( $code ) {
        if ( $code == '' ) {
            return;
        }

        echo "<!-- Tracking pixel by WooCommerce Conversion Tracking plugin by Tareq Hasan -->\n";
        echo $code;
        echo "\n<!-- Tracking pixel by WooCommerce Conversion Tracking plugin -->\n";
    }

    /**
     * Call the webhook url [if exists]
     *
     * @param   string  $oder_id
     *  
     * @return  void
     */
    function call_webhook($order_id) {
        $webhook = $this->get_option( 'webhook' );
        if ( $webhook == ""  ) {
            return ;
        }
        $webhook = html_entity_decode($webhook);
        $webhook = $this->process_webhook_markdown( $order_id, $webhook );
        file_get_contents($webhook);
    }

    /**
     * Filter the code for dynamic data for webhook url
     *
     * @since 1.1
     *
     * @param  string  $order_id
     * @param  string  $code
     *
     * @return string
     */
    function process_webhook_markdown( $order_id, $code ) {
        $order = wc_get_order( $order_id );

        // bail out if not a valid instance
        if ( ! is_a( $order, 'WC_Order' ) ) {
            return $code;
        }

        $order_currency = $order->get_order_currency();
        $order_total    = $order->get_total();
        $order_number   = $order->get_order_number();
        $order_subtotal = $order->get_subtotal();
        $lzhash         = $this->get_lzhash();

        $code           = str_replace( '{currency}', $order_currency, $code );
        $code           = str_replace( '{order_total}', $order_total, $code );
        $code           = str_replace( '{order_number}', $order_number, $code );
        $code           = str_replace( '{order_subtotal}', $order_subtotal, $code );
        $code           = str_replace( '{lzhash}', $lzhash, $code );

        return $code;
    }

    /**
     * Filter the code for dynamic data for order received page
     *
     * @since 1.1
     *
     * @param  string  $code
     *
     * @return string
     */
    function process_order_markdown( $code ) {
        global $wp;

        if ( ! is_order_received_page() ) {
            return $code;
        }

        $order = wc_get_order( $wp->query_vars['order-received'] );

        // bail out if not a valid instance
        if ( ! is_a( $order, 'WC_Order' ) ) {
            return $code;
        }

        $order_currency = $order->get_order_currency();
        $order_total    = $order->get_total();
        $order_number   = $order->get_order_number();
        $order_subtotal = $order->get_subtotal();
        $lzhash         = $this->get_lzhash();

        $code           = str_replace( '{currency}', $order_currency, $code );
        $code           = str_replace( '{order_total}', $order_total, $code );
        $code           = str_replace( '{order_number}', $order_number, $code );
        $code           = str_replace( '{order_subtotal}', $order_subtotal, $code );
        $code           = str_replace( '{lzhash}', $lzhash, $code );

        return $code;
    }

    /**
     * Filter the code for dynamic data for products
     *
     * @since 1.1
     *
     * @param  string  $code
     *
     * @return string
     */
    function process_product_markdown( $code, $product ) {
        $price               = $product->get_price();
        $sale_price          = $product->get_sale_price();
        $regular_price       = $product->get_regular_price();
        $price_excluding_tax = $product->get_price_excluding_tax();
        $price_including_tax = $product->get_price_including_tax();

        $code                = str_replace( '{product_name}', $product->get_title(), $code );
        $code                = str_replace( '{price}', $price, $code );
        $code                = str_replace( '{sale_price}', $sale_price, $code );
        $code                = str_replace( '{regular_price}', $regular_price, $code );
        $code                = str_replace( '{price_including_tax}', $price_including_tax, $code );
        $code                = str_replace( '{price_excluding_tax}', $price_excluding_tax, $code );

        return $code;
    }

}
