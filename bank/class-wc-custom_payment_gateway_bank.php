<?php
/**
 * WC wcCpg2 Gateway Class.
 * Built the wcCpg1 method.
 */
class WC_Custom_Payment_Gateway_1 extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     *
     * @return void
     */
    public function __construct() {
        global $woocommerce;

        $this->id             = 'ncgw1';
        $this->icon           = apply_filters( 'woocommerce_wcCpg1_icon', '' );
        $this->has_fields     = false;
        $this->method_title   = __( 'Bank2Bank', 'wcwcCpg1' );
        $this->method_description = __( 'This payment option is applicable only for Bank2Bank Payment in GB.', 'wcwcCpg1' );
        $this->order_button_text  = __( 'Proceed to Pay  ', 'woocommerce' );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title          = $this->settings['title'];
        $this->description    = $this->settings['description'];
        $this->api_key    = $this->settings['api-key'];
        $this->secret_key    = $this->settings['secret-key'];
        $this->order_status    = $this->settings['order-status'];
       
        $this->instructions       = $this->get_option( 'instructions' );
        $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
        $this->callback_url = WC()->api_request_url( 'callback_url_for_zottopay' );
        $this->fail_url = WC()->api_request_url( 'fail_for_zottopay' );
        // Actions.
       // add_action('woocommerce_api_'.strtolower(get_class($this)), array(&$this, 'callback_handler'));
        add_action( 'woocommerce_api_callback_url_for_zottopay', array( $this, 'callback_url_for_zottopay' ) );
        add_action( 'woocommerce_api_fail_for_zottopay', array( $this, 'fail_for_zottopay' ) );
        add_action('woocommerce_thankyou', array(&$this, 'thankyou_page'));
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
        else
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );

        add_action( 'woocommerce_receipt_' . $this->id, array(&$this, 'pay_for_order'));
    }

    /* Admin Panel Options.*/
    function admin_options() {
        ?>
        <h3><?php _e('ZottoPay Bank2Bank','ncgw1'); ?></h3>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table> <?php
    }

    

    /* Initialise Gateway Settings Form Fields. */
    public function init_form_fields() {
        global $woocommerce;

        $shipping_methods = array();

        if ( is_admin() )
            foreach ( $woocommerce->shipping->load_shipping_methods() as $method ) {
                $shipping_methods[ $method->id ] = $method->get_title();
            }
            
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'wcwcCpg1' ),
                'type' => 'checkbox',
                'label' => __( 'Enable ZottoPay', 'wcwcCpg1' ),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __( 'Bank2Bank', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wcwcCpg1' ),
                'desc_tip' => true,
                'default' => __( 'Bank2Bank', 'wcwcCpg1' )
            ),
            'description' => array(
                'title' => __( 'Description', 'wcwcCpg1' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wcwcCpg2' ),
                'default' => __( 'Pay with zotto pay. We accept  bank payment in GB', 'wcwcCpg2' )
            ),
            'instructions' => array(
                'title' => __( 'Instructions', 'wcwcCpg1' ),
                'type' => 'textarea',
                'description' => __( 'Instructions that will be added to the thank you page.', 'wcwcCpg2' ),
                'default' => __( 'Instructions', 'wcwcCpg1' )
            ),
            'api-key' => array(
                'title' => __( 'API Key', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'Your API key.', 'wcwcCpg1' ),
                'default' => __( '', 'wcwcCpg1' )
            ),'secret-key' => array(
                'title' => __( 'Secret Key', 'wcwcCpg1' ),
                'type' => 'text',
                'description' => __( 'Your Secret key.', 'wcwcCpg1' ),
                'default' => __( '', 'wcwcCpg1' )
            ),'order-status' => array(
                'title' => __( 'Order Status On Success', 'wcwcCpg1' ),
                'type' => 'select',
                 
                'options' => array(
                    'on-hold' => 'On Hold',
                    'processing' => 'Processing',
                    'completed' => 'Completed'
                    
                ),
                'description' => __( 'Choose Order Status', 'wcwcCpg1' ),
                'default' => __( 'Processing', 'wcwcCpg1' )
            )
        );
    }

    public function get_cancel_order_url_raw( $order_id,$key, $redirect = '' ) {
        return apply_filters( 'woocommerce_get_cancel_order_url_raw', add_query_arg( array(
            'cancel_order' => 'false',
            'order'        => $order_id,
            'order_id'     => $key,
            'redirect'     => $redirect,
            '_wpnonce'     => wp_create_nonce( 'woocommerce-cancel_order' ),
        ), $this->get_cancel_endpoint() ) );


    }

    public function get_cancel_endpoint() {
        $cancel_endpoint = wc_get_page_permalink( 'cart' );
        
        if ( ! $cancel_endpoint ) {
            $cancel_endpoint = home_url();
        }

        if ( false === strpos( $cancel_endpoint, '?' ) ) {
            //echo "sorry payment is not success";
            $cancel_endpoint = trailingslashit( $cancel_endpoint );
        }
        return $cancel_endpoint;
    }

    public function callback_url_for_zottopay() {
        
        global $wpdb;
        $raw_post = file_get_contents( 'php://input' );
        //print_r($raw_post);exit;
        // $success = $wpdb->insert("wp_callback", array(
        //     'callback' => $raw_post
        // ));
        $trxid='';
        $rowdata = json_decode($raw_post);
       
      
       $status = $rowdata->status;
       $order_id = $rowdata->order_id;
       $transaction_id = $rowdata->trans_id;
       $order =  wc_get_order( $order_id ) ;
       $trxid=$order->get_transaction_id();
      
       if($status = 1 || $status ='success'){
          
           
         if(empty($trxid)){
            add_post_meta( $order_id, '_transaction_id', $transaction_id, true );
            $order->payment_complete();  
           
         }  
          
       }elseif ($status = 2 ||  $status ='pending') {
            # code...
            if(empty($trxid)){
                add_post_meta( $order_id, '_transaction_id', $transaction_id, true );
               
             }  
           
        }
        
    }


 

    public function process_payment( $order_id ) {
        $order = new WC_Order( $order_id );
        $api_key = sanitize_text_field($this->api_key);
        $secret = sanitize_text_field($this->secret_key);
        $order_currency = $order->get_currency();
       

        //echo 'apikey'.$api_key; exit;

        if(empty($api_key) || empty($secret) || empty($order_currency)){
            wc_add_notice(  'Error Processing the Payment .', 'error' );
            return array(
                'result' => 'failure',
                'message' => 'Please configure the payment settings'
            );
           
        }else{
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url( true )
            );
        }
    }
    // here, prepare your form and submit it to the required URL
    public function pay_for_order( $order_id ) {
        $order = new WC_Order( $order_id );
        $api_key = sanitize_text_field($this->api_key);
        $secret = sanitize_text_field($this->secret_key);
        $redirecttype = 2;
        $order_currency = $order->get_currency();
        if(empty($api_key) || empty($secret) || empty($order_currency) ){
            wc_add_notice(  'Please Configure the Payment Settings.', 'error' );
           
            die;
        }else{

        echo '<p>' . __( 'Redirecting to payment provider.', 'txtdomain' ) . '</p>';
        
        //$order->update_status( 'on-hold', __( 'Awaiting payment.', 'txtdomain' ) );
        // remember to empty the cart of the user
        //WC()->cart->empty_cart();
        

        $cancel_url = esc_url_raw( $order->get_cancel_order_url_raw());

            
        
        $callback_url = esc_url_raw($this->callback_url);
        $failed_url =  add_query_arg( 'order_id', $order->get_id(), esc_url_raw($this->fail_url) );
        
        $error_url =  add_query_arg( 'order_id', $order->get_id(), esc_url_raw($this->fail_url) );
        
        $success_url = esc_url_raw($this->get_return_url( $order ));
        
        $user_id ="";
    
        
        $order_amount = $order->get_total();
        $country = sanitize_text_field($order->get_billing_country());
       
        
        $merch_orderid = $order->get_id();
        $user_id = $order->get_user_id( );
        
        $language = 'EN';
        $mobile = $order->get_billing_phone();
        $email = sanitize_email($order->get_billing_email());
        $date = new DateTime();

        
        //$macstring1 = $linkamount.$back_linkurl.$callback_linkurl.$currency.$error_linkurl.$failed_linkurl.$merchant_key.$merch_orderid.$success_linkurl.$userid.$merchant_secret_key;
        $macstring1 = $order_amount.$cancel_url.$callback_url.$order_currency.$error_url.$failed_url.$api_key.$merch_orderid.$success_url.$secret;
        $macstring = str_replace(" ","",$macstring1); 
        $machash=  hash('sha256', $macstring);
        $data = array(
            'merchant_key' => $api_key,
            'amount' => $order_amount,
            'email' => $email,
            'country' => $country,
            'user_id'=> $user_id,
            'currency' => $order_currency,
            'redirect_type' => $redirecttype,
            'phone' => $mobile,
            'mac_string' => $machash,
            'success_url' => $success_url,
            'callback_url' => $callback_url,
            'error_url' => $error_url,
            'failed_url' => $failed_url,
            'back_url' => $cancel_url,
            'order_id' => $merch_orderid,
        );
       
        //$url = 'https://pay.cibopay.com/api/v1/checkoutpay/payment';
        //$url = ' http://ciboapp.me/apitest/api/v1/checkoutpay/payment';
        //$url = 'https://paymentz.z-pay.co.uk/api/payment-checkout';
        $url = 'https://api.zotto.z-payments.com/api/payment-checkout';
        
       
            wc_enqueue_js( 'jQuery( "#submit-form" ).submit();' );

            // return your form with the needed parameters
            echo'<form action="'.$url.'" method="post" target="_top" id="submit-form">
                    <input type="hidden" name="merchant_key" value="'.$api_key.'">
                    <input type="hidden" name="accept_url" value="'.$success_url.'">
                    <input type="hidden" name="error_url" value="'.$error_url.'">
                    <input type="hidden" name="back_url" value="'.$cancel_url.'">
                    <input type="hidden" name="callback_url" value="'.$callback_url.'">
                    <input type="hidden" name="failed_url" value="'.$failed_url.'">
                    <input type="hidden" name="order_id" value="'.$merch_orderid.'">
                    <input type="hidden" name="amount" value="'.$order_amount.'">
                    <input type="hidden" name="email" value="'.$email.'">
                    <input type="hidden" name="country" value="'.$country.'">
                    <input type="hidden" name="user_id" value="'.$user_id.'">
                    <input type="hidden" name="currency" value="'.$order_currency.'">
                    <input type="hidden" name="redirect_type" value="'.$redirecttype.'">
                    <input type="hidden" name="phone" value="'.$mobile.'">
                    <input type="hidden" name="mac" value="'.$machash.'">
                
                </form>';
        }
    }

    /* Output for the order received page.   */
    function thankyou_page() {

        if( !is_wc_endpoint_url( 'order-received' ) || empty( $_GET['key'] ) ) {
            return;
        }
     
        $order_id = wc_get_order_id_by_order_key( $_GET['key'] );
        $order = wc_get_order( $order_id );
       
        if ( ! $order_id )
        return;

        

        // No updated status for orders delivered with Bank wire, Cash on delivery and Cheque payment methods.
       /* if(get_post_meta($order_id, '_payment_method', true)=='ncgw1') {
            $order->update_status($this->order_status,'Order Placed Successfully');
        }else{
            return;
        } */
          
        echo $this->instructions != '' ? wpautop( $this->instructions ) : '';
    }

    function fail_for_zottopay() {
        $order_id = sanitize_text_field($_GET['order_id']);
        $order = wc_get_order( $order_id );
       
        
        $paymentfailed =  esc_url_raw($order->get_cancel_order_url());
        
        $order->update_status( 'pending','payment failed' );
        $order -> add_order_note('Payment Failed');
        wc_add_notice(  'Payment Failed. Please try again.', 'error' );
       
        wp_redirect($paymentfailed);

        
    }
}
