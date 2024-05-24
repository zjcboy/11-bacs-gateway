<?php
/*
Plugin Name: 11 BACS Gateway
Description: Customized version of the Bank Transfer Payment Gateway for WooCommerce.
Version: 1.4.0
Author: 11 TEAM
*/

// Hook into the plugins_loaded action to ensure WooCommerce is loaded
add_action('plugins_loaded', 'custom_bacs_gateway_init');

function custom_bacs_gateway_init() {
    // Check if WooCommerce is active
    if (class_exists('WC_Payment_Gateway')) {
            /**
             * Custom Bank Transfer Payment Gateway.
             *
             * Provides a Custom Bank Transfer Payment Gateway based on WC_Gateway_BACS.
             */
            class Custom_BACS_Gateway extends WC_Payment_Gateway
            {

                /**
                 * Constructor for the gateway.
                 */
                public function __construct()
                {
                    $this->id                 = 'custom_bacs';
                    $this->icon               = apply_filters('woocommerce_custom_bacs_icon', '');
                    $this->has_fields         = false;
                    $this->method_title       = __('Custom Bank Transfer', 'woocommerce');
                    $this->method_description = __('Take payments in person via BACS.', 'woocommerce'); 

                    // Load the settings.
                    $this->init_form_fields();
                    $this->init_settings();

                    // Define user set variables.
                    $this->title        = $this->get_option('title');
                    $this->description  = $this->get_option('description');
                    $this->instructions = $this->get_option('instructions');
                    $this->heading = $this->get_option('heading');
                    $this->voucher_upload_page_url = $this->get_option('voucher_upload_page_url');
                    $this->sepa_qrcode_url = $this->get_option('sepa_qrcode_url');
                    $this->custom_style = $this->get_option('custom_style');
                    

                    // BACS account fields shown on the thanks page and in emails.
                    $this->account_details = get_option(
                        'woocommerce_custom_bacs_accounts',
                        array(
                            array(
                                'account_name'   => $this->get_option( 'account_name' ),
                                'account_number' => $this->get_option( 'account_number' ),
                                'sort_code'      => $this->get_option( 'sort_code' ),
                                'bank_name'      => $this->get_option( 'bank_name' ),
                                'iban'           => $this->get_option( 'iban' ),
                                'bic'            => $this->get_option( 'bic' ),
                                'currency_support'   => $this->get_option( 'currency_support' ),
                                'sepa_qrcode'   => $this->get_option( 'sepa_qrcode' ),
                                'custom_style'   => $this->get_option( 'custom_style' ),
                            ),
                        )
                    );

                    // Actions.
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
                    add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
                    add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
                }

                /**
                 * Initialise Gateway Settings Form Fields.
                 */
                public function init_form_fields()
                {
                    $this->form_fields = array(
                        'enabled'      => array(
                            'title'   => __('Enable/Disable', 'woocommerce'),
                            'type'    => 'checkbox',
                            'label'   => __('Enable bank transfer', 'woocommerce'),
                            'default' => 'no',
                        ),
                        'title'        => array(
                            'title'       => __('Title', 'woocommerce'),
                            'type'        => 'text',
                            'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                            'default'     => __('Direct bank transfer', 'woocommerce'),
                            'desc_tip'    => true,
                        ),
                        'description'  => array(
                            'title'       => __('Description', 'woocommerce'),
                            'type'        => 'textarea',
                            'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                            'default'     => __('Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.', 'woocommerce'),
                            'desc_tip'    => true,
                        ),
                        'instructions' => array(
                            'title'       => __('Instructions', 'woocommerce'),
                            'type'        => 'textarea',
                            'description' => __('', 'woocommerce'),
                            'default'     => '',
                            'desc_tip'    => true,
                        ),
                        'heading'        => array(
                            'title'       => __('Heading', 'woocommerce'),
                            'type'        => 'text',
                            'default'     => __('Please use bank app transfer, we will not ship until payment is made.', 'woocommerce'),
                        ),
                        'voucher_upload_page_url'  => array(
                            'title'       => __('Voucher Upload Page URL', 'woocommerce'),
                            'type'        => 'text'
                        ),
                        'sepa_qrcode_url'  => array(
                            'title'       => __('SEPA Qrcode Url', 'woocommerce'),
                            'type'        => 'text'
                        ),
                        'custom_style'  => array(
                            'title'       => __('Custom style', 'woocommerce'),
                            'type'        => 'textarea',
                            'default' 	  => '.bacs_account{
                                                background: #f3f3f3;border:none;padding:10px;margin: 0 0 15px 0;
                                            }
                                            .wc-bacs-bank-details-heading{
                                                margin-bottom: 10px;
                                            }
                                            .bacs_account .bacs_account_title{
                                                margin: 0px;
                                                font-size:14px;
                                            }
                                            .bacs_account .wc-bacs-bank-details div:not(:last-child){
                                                border-bottom: 0.4px solid #ccc;
                                            }
                                            .bacs_account .wc-bacs-bank-details div{
                                                padding: 5px 0;
                                                word-break:break-all;
                                            }

                                            .bacs_account .wc-bacs-bank-details div span{
                                                display: block;
                                                line-height: 1.4;
                                            }

                                            .bacs_account .wc-bacs-bank-details div label{
                                                margin-bottom: 0;
                                                font-size: 12px;
                                            }
                                            .bacs_account .sepa-qrcode{
                                                margin-bottom: 10px;
                                            }
                                            .bacs_account  .sepa-qrcode-img{
                                                width: 200px;
                                                margin-bottom: 5px;
                                            }
                                            .bacs_account .sepa-qrcode h6{
                                                margin: 0 0 5px 0;
                                            }
                                            .bacs_account .sepa-qrcode span{
                                                font-size:14px;
                                            }'
                        ),
                        'account_details' => array(
                            'type' => 'account_details',
                        ),
                    );
                }

                /**
                 * Generate account details html.
                 *
                 * @return string
                 */
                public function generate_account_details_html() {

                    ob_start();

                    $country = WC()->countries->get_base_country();
                    $locale  = $this->get_country_locale();

                    // Get sortcode label in the $locale array and use appropriate one.
                    $sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'woocommerce' );

                    ?>
                    <tr valign="top">
                        <th scope="row" class="titledesc"><?php esc_html_e( 'Account details:', 'woocommerce' ); ?></th>
                        <td class="forminp" id="bacs_accounts">
                            <div class="wc_input_table_wrapper">
                                <table class="widefat wc_input_table sortable" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th class="sort">&nbsp;</th>
                                            <th><?php esc_html_e( 'Account name', 'woocommerce' ); ?></th>
                                            <th><?php esc_html_e( 'Account number', 'woocommerce' ); ?></th>
                                            <th><?php esc_html_e( 'Bank name', 'woocommerce' ); ?></th>
                                            <th><?php echo esc_html( $sortcode ); ?></th>
                                            <th><?php esc_html_e( 'IBAN', 'woocommerce' ); ?></th>
                                            <th><?php esc_html_e( 'BIC / Swift', 'woocommerce' ); ?></th>
                                            <th><?php esc_html_e( 'Currency support', 'woocommerce' ); ?></th>
                                            <th><?php esc_html_e( 'SEPA QRcode', 'woocommerce' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="accounts">
                                        <?php
                                        $i = -1;
                                        if ( $this->account_details ) {
                                            foreach ( $this->account_details as $account ) {
                                                $i++;

                                                echo '<tr class="account">
                                                    <td class="sort"></td>
                                                    <td><input type="text" value="' . esc_attr( wp_unslash( $account['account_name'] ) ) . '" name="account_name[' . esc_attr( $i ) . ']" /></td>
                                                    <td><input type="text" value="' . esc_attr( $account['account_number'] ) . '" name="account_number[' . esc_attr( $i ) . ']" /></td>
                                                    <td><input type="text" value="' . esc_attr( wp_unslash( $account['bank_name'] ) ) . '" name="bank_name[' . esc_attr( $i ) . ']" /></td>
                                                    <td><input type="text" value="' . esc_attr( $account['sort_code'] ) . '" name="sort_code[' . esc_attr( $i ) . ']" /></td>
                                                    <td><input type="text" value="' . esc_attr( $account['iban'] ) . '" name="iban[' . esc_attr( $i ) . ']" /></td>
                                                    <td><input type="text" value="' . esc_attr( $account['bic'] ) . '" name="bic[' . esc_attr( $i ) . ']" /></td>
                                                    <td><input type="text" placeholder="使用英文逗号分隔, ex: USD,EUR,CAD..."  value="' . esc_attr( $account['currency_support'] ) . '" name="currency_support[' . esc_attr( $i ) . ']" /></td>
                                                    <td><input type="checkbox"  ' . ( $account['sepa_qrcode'] === 'on' ? 'checked' : '' ) . ' name="sepa_qrcode[' . esc_attr( $i ) . ']" /></td>
                                                </tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="7"><a href="#" class="add button"><?php esc_html_e( '+ Add account', 'woocommerce' ); ?></a> <a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected account(s)', 'woocommerce' ); ?></a></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <script type="text/javascript">
                                jQuery(function() {
                                    jQuery('#bacs_accounts').on( 'click', 'a.add', function(){

                                        var size = jQuery('#bacs_accounts').find('tbody .account').length;

                                        jQuery('<tr class="account">\
                                                <td class="sort"></td>\
                                                <td><input type="text" name="account_name[' + size + ']" /></td>\
                                                <td><input type="text" name="account_number[' + size + ']" /></td>\
                                                <td><input type="text" name="bank_name[' + size + ']" /></td>\
                                                <td><input type="text" name="sort_code[' + size + ']" /></td>\
                                                <td><input type="text" name="iban[' + size + ']" /></td>\
                                                <td><input type="text" name="bic[' + size + ']" /></td>\
                                                <td><input type="text" placeholder="使用英文逗号分隔, ex: USD,EUR,CAD..."   name="currency_support[' + size + ']" /></td>\
                                                <td><input type="checkbox" name="sepa_qrcode[' + size + ']" /></td>\
                                            </tr>').appendTo('#bacs_accounts table tbody');

                                        return false;
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                    <?php
                    return ob_get_clean();
                }

                /**
                 * Save account details.
                 */
                public function save_account_details()
                {
                    $accounts = array();
                    
                    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
                    if ( isset( $_POST['account_name'] ) && isset( $_POST['account_number'] ) && isset( $_POST['bank_name'] )
                        && isset( $_POST['sort_code'] ) && isset( $_POST['iban'] ) && isset( $_POST['bic'] ) ) {

                        $account_names   = wc_clean(wp_unslash($_POST['account_name']));
                        $account_numbers = wc_clean(wp_unslash($_POST['account_number']));
                        $bank_names      = wc_clean(wp_unslash($_POST['bank_name']));
                        $sort_codes      = wc_clean(wp_unslash($_POST['sort_code']));
                        $ibans           = wc_clean(wp_unslash($_POST['iban']));
                        $bics            = wc_clean(wp_unslash($_POST['bic']));
                        $currency_supports  = wc_clean(wp_unslash($_POST['currency_support']));
                        $sepa_qrcodes  = wc_clean(wp_unslash($_POST['sepa_qrcode']));

                        foreach ($account_names as $i => $name) {
                            if (!isset($account_numbers[$i])) {
                                continue;
                            }
                            if (!empty($account_names[$i])) {
                                $accounts[] = array(
                                    'account_name'   => $account_names[ $i ],
                                    'account_number' => $account_numbers[ $i ],
                                    'bank_name'      => $bank_names[ $i ],
                                    'sort_code'      => $sort_codes[ $i ],
                                    'iban'           => $ibans[ $i ],
                                    'bic'            => $bics[ $i ],
                                    'currency_support'   => $currency_supports[ $i ],
                                    'sepa_qrcode'   => $sepa_qrcodes[ $i ],
                                );
                            }
                        }
                    }
                    // phpcs:enable

                    do_action( 'woocommerce_update_option', array( 'id' => 'woocommerce_custom_bacs_accounts' ) );
                    update_option('woocommerce_custom_bacs_accounts', $accounts);
                }

            
                /**
                 * Output for the order received page.
                 *
                 * @param int $order_id Order ID.
                 */
                public function thankyou_page( $order_id ) {

                    if ( $this->instructions ) {
                        echo wp_kses_post( wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ) );
                    }
                    $this->bank_details( $order_id );

                }

                /**
                 * Add content to the WC emails.
                 *
                 * @param WC_Order $order Order object.
                 * @param bool     $sent_to_admin Sent to admin.
                 * @param bool     $plain_text Email format: plain text or HTML.
                 */
                public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
                    /**
                     * Filter the email instructions order status.
                     *
                     * @since 7.4
                     * @param string $terms The order status.
                     * @param object $order The order object.
                     */
                    if ( ! $sent_to_admin && 'bacs' === $order->get_payment_method() && $order->has_status( apply_filters( 'woocommerce_bacs_email_instructions_order_status', 'on-hold', $order ) ) ) {
                        if ( $this->instructions ) {
                            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
                        }
                        $this->bank_details( $order->get_id() );
                    }

                }

                
                
                /**
                 * Get bank details and place into a list format.
                 *
                 * @param int $order_id Order ID.
                 */
                private function bank_details( $order_id = '' ) {

                    if ( empty( $this->account_details ) ) {
                        return;
                    }

                    // Get order and store in $order.
                    $order = wc_get_order( $order_id );

                    // Get the order country and country $locale.
                    $country = $order->get_billing_country();
                    $locale  = $this->get_country_locale();


                    // Get the order currency.
                    $currency = $order->get_currency();

                    // Check if the currency is Euro.
                    $only_euro = 'EUR' === $currency;


                    // Get sortcode label in the $locale array and use appropriate one.
                    $sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort code', 'woocommerce' );

                    $bacs_accounts = apply_filters( 'woocommerce_custom_bacs_accounts', $this->account_details, $order_id );

                    if ( ! empty( $bacs_accounts ) ) {
                        $account_html = '';
                        $has_details  = false;
                        
                        $filtered_bacs_accounts = array(); // Temporary variable to store filtered accounts
                        foreach ( $bacs_accounts as $key => $bacs_account ) {
                            $bacs_account = (object) $bacs_account;

                            // Check if it's Euro currency and IBAN is not empty, or if it's not Euro currency.
                            if ( ( $only_euro && ! empty( $bacs_account->iban ) ) || ! $only_euro ) {
                                $filtered_bacs_accounts[] = $bacs_account;
                            }
                        }


                        foreach ( $filtered_bacs_accounts as $key => $bacs_account ) {
                            $bacs_account = (object) $bacs_account;
            
                            $account_html .= '<div class="bacs_account"><h5 class="bacs_account_title">- Bank Account '. $key + 1 .'</h5>';
                            
                            $account_html .= '<div class="wc-bacs-bank-details order_details bacs_details">' . PHP_EOL;
            
                            $has_details   = true;

                            // BACS account fields shown on the thanks page and in emails.
                            $account_fields = apply_filters(
                                'woocommerce_bacs_account_fields',
                                array(
                                    'account_name'      => array(
                                        'label' => __( 'Beneficiary name', 'woocommerce' ),
                                        'value' => $bacs_account->account_name,
                                    ),
                                    'account_number' => array(
                                        'label' => __( 'Beneficiary account number', 'woocommerce' ),
                                        'value' => $bacs_account->account_number,
                                    ),
                                    'iban'   => array(
                                        'label' => __( 'IBAN', 'woocommerce' ),
                                        'value' => $bacs_account->iban,
                                    ),
                                    'currency_support'      => array(
                                        'label' => __( 'Support currency', 'woocommerce' ),
                                        'value' => $bacs_account->currency_support,
                                    ),
                                    'bank_name'      => array(
                                        'label' => __( 'Bank name', 'woocommerce' ),
                                        'value' => $bacs_account->bank_name,
                                    ),
                                    'bic'   => array(
                                        'label' => __( 'BIC/SWIFT', 'woocommerce' ),
                                        'value' => $bacs_account->bic,
                                    ),
                                    'sort_code'      => array(
                                        'label' => $sortcode,
                                        'value' => $bacs_account->sort_code,
                                    ),
                                ),
                                $order_id
                            );

                            
                            foreach ( $account_fields as $field_key => $field ) {
                                if ( ! empty( $field['value'] ) ) {
                                    $value = wp_kses_post( wptexturize( $field['value'] ) );
                                    // If rendering BIC field, append the suffix
                                    if ( $field_key === 'bic' ) {
                                        // Check if the BIC is less than 11 characters
                                        if ( strlen( $value ) < 11 ) {
                                            $value .= ' (' . $field['value'] .'XXX * If 11 characters are required)';
                                        }
                                    }
                                    $account_html .= '<div class="' . esc_attr( $field_key ) . '"><label>' . wp_kses_post( $field['label'] ) . ': </label>' . '<span data-no-translation>' . wp_kses_post( wptexturize( $field['value'] ) ) . '</span></div>' . PHP_EOL;
                                    $has_details   = true;
                                }
                                
                            }

                            $account_html .= '<div class="remark"><label>Remark:</label>' . '<div data-no-translation>' .$order->get_billing_first_name() .', #'.  $order_id   .'</div></div>';
                            
                            if ($bacs_account -> sepa_qrcode == 'on' && !empty($bacs_account -> iban)) {
                                $account_html .= '<div class="sepa-qrcode"><label>SEPA Qrcode:</label>' . '<div><img class="sepa-qrcode-img" src="'. $this->sepa_qrcode_url . '?recipientName='. $bacs_account->account_name . '&bic=' . $bacs_account->bic . '&iban=' . $bacs_account->iban . '&amount=' . $order->get_total() . '&paymentPurpose=' . $order->get_billing_first_name() .'-'.  $order_id . '&currency=EUR' .'"/>' . '<br /> <h6>To pay, scan this QR code using the bank app.</h6>
                                <span>Or save the QR code to upload to your banking app.</span> </div></div>';
                            }
                            
                            
                            
                        
                    
                            $account_html .= '</div></div>';
                        
                        }
                
                        if(!empty($this->voucher_upload_page_url)) {
                            $account_html .= '<a class="wc-bacs-bank-details-upload-tips" target="_blank"  href="'. $this->voucher_upload_page_url . $order_id .'">Click here, Upload transfer records and confirm the order as soon as possible.</a>';
                        }
                        $account_html .= '<div style="padding: 10px 0;"></div>';
                            
                        if ( $has_details ) {
                            echo '<section class="woocommerce-bacs-bank-details"><h5 class="wc-bacs-bank-details-heading">' . esc_html__( $this->heading, 'woocommerce' ) . '</h5>' . wp_kses_post( PHP_EOL . $account_html ) . '</section>';
                        }
                        
                        echo '<style>' . $this->custom_style . '</style>';
                    }

                }

                
                
                /**
                 * Process the payment and return the result.
                 *
                 * @param int $order_id Order ID.
                 * @return array
                 */
                public function process_payment( $order_id ) {

                    $order = wc_get_order( $order_id );

                    if ( $order->get_total() > 0 ) {
                        // Mark as on-hold (we're awaiting the payment).
                        $order->update_status( apply_filters( 'woocommerce_custom_bacs_process_payment_order_status', 'on-hold', $order ), __( 'Awaiting BACS payment', 'woocommerce' ) );
                    } else {
                        $order->payment_complete();
                    }

                    // Remove cart.
                    WC()->cart->empty_cart();

                    // Return thankyou redirect.
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url( $order ),
                    );

                }



                /**
                 * Get country locale if localized.
                 *
                 * @return array
                 */
                public function get_country_locale() {

                    if ( empty( $this->locale ) ) {

                        // Locale information to be used - only those that are not 'Sort Code'.
                        $this->locale = apply_filters(
                            'woocommerce_get_bacs_locale',
                            array(
                                'AU' => array(
                                    'sortcode' => array(
                                        'label' => __( 'BSB', 'woocommerce' ),
                                    ),
                                ),
                                'CA' => array(
                                    'sortcode' => array(
                                        'label' => __( 'Bank transit number', 'woocommerce' ),
                                    ),
                                ),
                                'IN' => array(
                                    'sortcode' => array(
                                        'label' => __( 'IFSC', 'woocommerce' ),
                                    ),
                                ),
                                'IT' => array(
                                    'sortcode' => array(
                                        'label' => __( 'Branch sort', 'woocommerce' ),
                                    ),
                                ),
                                'NZ' => array(
                                    'sortcode' => array(
                                        'label' => __( 'Bank code', 'woocommerce' ),
                                    ),
                                ),
                                'SE' => array(
                                    'sortcode' => array(
                                        'label' => __( 'Bank code', 'woocommerce' ),
                                    ),
                                ),
                                'US' => array(
                                    'sortcode' => array(
                                        'label' => __( 'Routing number', 'woocommerce' ),
                                    ),
                                ),
                                'ZA' => array(
                                    'sortcode' => array(
                                        'label' => __( 'Branch code', 'woocommerce' ),
                                    ),
                                ),
                            )
                        );

                    }

                    return $this->locale;

                }
            }
            


            /**
             * Add the custom gateway to WooCommerce.
             */
            function add_custom_bacs_gateway($methods)
            {
                $methods[] = 'Custom_BACS_Gateway';
                return $methods;
            }
            add_filter('woocommerce_payment_gateways', 'add_custom_bacs_gateway');

    } else {
        // WooCommerce is not active, handle accordingly
        // For example, you can display a notice to the user or deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce to be installed and activated.');
    }
}





// Hook into the admin_init action to check for plugin updates
add_action('admin_init', 'check_for_plugin_update');

function check_for_plugin_update() {
    $plugin_data = get_plugin_data(__FILE__);
    $plugin_version = $plugin_data['Version'];
    $update_url = 'https://bacs-gateway-plugin.0079527.xyz/update-info.json'; // Update with your actual Cloudflare Pages URL

    $response = wp_remote_get($update_url);
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $update_info = json_decode(wp_remote_retrieve_body($response), true);

        if (version_compare($plugin_version, $update_info['new_version'], '<')) {
            add_action('admin_notices', function() use ($update_info) {
                $download_url = $update_info['download_url'];
                echo '<div class="notice notice-warning is-dismissible">
                        <p>There is a new version of Custom BACS Gateway available. <a href="' . esc_url($download_url) . '" target="_blank">Download version ' . esc_html($update_info['new_version']) . '</a></p>
                      </div>';
            });
        }
    }
}
