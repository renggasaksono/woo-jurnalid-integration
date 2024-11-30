<?php

namespace Saksono\Woojurnal\Admin;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\JurnalApi;

class AccountMapping {

    public function __construct()
    {
        add_action('admin_init', [$this, 'initialize_account_mapping']);
    }

    /**
     * Initialize account mapping section and options
     */
    public function initialize_account_mapping()
    {
        if( false == get_option( 'wji_account_mapping_options' ) ) {  
            add_option( 'wji_account_mapping_options', array() );
        }

        add_settings_section(
            'account_mapping_section',
            'Payment Account',
            [$this, 'account_mapping_callback'],
            'wji_plugin_account_options'
        );

        /** PAYMENT ACCOUNTS **/
    
        // Get existing wc payment methods
        $gateways = function_exists('WC') ? WC()->payment_gateways->payment_gateways() : [];
    
        if(count($gateways) > 0) {
    
            // Register a section
            add_settings_section(
                'account_mapping_section',              // ID used to identify this section and with which to register options
                'Payment Account',                     // Title to be displayed on the administration page
                [$this, 'account_mapping_callback'],         // Callback used to render the description of the section
                'wji_plugin_account_options'            // Page on which to add this section of options
            );
    
            foreach ($gateways as $gateway) {
                $id = $gateway->id;
                $title = $gateway->title;
    
                // Create the settings
                add_settings_field( 
                    'acc_payment_'.$id,                         // ID used to identify the field throughout the theme
                    $title,                                     // The label to the left of the option interface element
                    [$this, 'dynamic_payment_account_callback'],     // The name of the function responsible for rendering the option interface
                    'wji_plugin_account_options',               // The page on which this option will be displayed
                    'account_mapping_section',                  // The name of the section to which this field belongs
                    [                                           // Optional arguments
                        'payment_id' => $id
                    ]
                ); 
            }
        }
    
        /** SALES ACCOUNTS **/
    
        // Register a section
        add_settings_section(
            'account_sales_mapping_section',    // ID used to identify this section and with which to register options
            'Sales Account',                    // Title to be displayed on the administration page
            [$this, 'account_mapping_callback'],     // Callback used to render the description of the section
            'wji_plugin_account_options'        // Page on which to add this section of options
        );
    
        // Create the settings
        add_settings_field( 
            'acc_sales',                        // ID used to identify the field throughout the theme
            'Sales Account',                    // The label to the left of the option interface element
            [$this, 'acc_sales_callback'],           // The name of the function responsible for rendering the option interface
            'wji_plugin_account_options',       // The page on which this option will be displayed
            'account_sales_mapping_section'     // The name of the section to which this field belongs
        );
    
        // Create the settings
        add_settings_field( 
            'acc_receivable',                   // ID used to identify the field throughout the theme
            'Account Receivable',               // The label to the left of the option interface element
            [$this, 'acc_receivable_callback'],      // The name of the function responsible for rendering the option interface
            'wji_plugin_account_options',       // The page on which this option will be displayed
            'account_sales_mapping_section'     // The name of the section to which this field belongs
        );
    
        // Create the settings
        add_settings_field( 
            'acc_tax',                          // ID used to identify the field throughout the theme
            'Tax Account',                      // The label to the left of the option interface element
            [$this, 'acc_tax_callback'],             // The name of the function responsible for rendering the option interface
            'wji_plugin_account_options',       // The page on which this option will be displayed
            'account_sales_mapping_section'     // The name of the section to which this field belongs
        );
    
        // Create the settings
        add_settings_field( 
            'acc_stock_adjustments',                // ID used to identify the field throughout the theme
            'Stock Adjustment Account',             // The label to the left of the option interface element
            [$this, 'acc_stock_adjustments_callback'],   // The name of the function responsible for rendering the option interface
            'wji_plugin_account_options',           // The page on which this option will be displayed
            'account_sales_mapping_section'         // The name of the section to which this field belongs
        );
    
        // Register the fields with WordPress 
        register_setting(
            'wji_account_mapping_options',      // A settings group name
            'wji_account_mapping_options'       // The name of an option to sanitize and save
        );
    }

    /* ------------------------------------------------------------------------ *
    * Section Callbacks
    * ------------------------------------------------------------------------ */

    public function account_mapping_callback() {
        echo '<p>Map WooCommerce payment gateways to Jurnal.ID accounts.</p>';
        // Check if cached data available
        if( false === ( get_transient( 'wji_cached_journal_account' ) ) ) {
            // Set list of accounts for future uses
            $api = new JurnalApi();
            if( $accounts = $api->getAllJurnalAccounts() ) {
                set_transient( 'wji_cached_journal_account', $accounts, 7 * DAY_IN_SECONDS );
            }
        }
    }

    /* ------------------------------------------------------------------------ *
    * Field Callbacks
    * ------------------------------------------------------------------------ */

    public function acc_sales_callback($args) {
        $get_options = get_option('wji_account_mapping_options');
        $field_name = 'acc_sales';
        if ( !array_key_exists($field_name,$get_options) ) {
            $get_options[$field_name] = '';
        }
        $options = $get_options;
        $html = '<select name="wji_account_mapping_options[acc_sales]" class="wj-accounts-select2">';
        $html .= '<option></option>';
        if( $accounts = get_transient( 'wji_cached_journal_account' ) ) {
            foreach ($accounts as $account) {
                $html .= '<option value="' . esc_html( $account['id'] ) . '"'
                 . selected( $options['acc_sales'], $account['id'], false ) . '>'
                 . esc_html( $account['text'] ) . '</option>';
            }
        }
        echo $html;
    }
    
    public function acc_receivable_callback($args) {
        $get_options = get_option('wji_account_mapping_options');
        $field_name = 'acc_receivable';
        if ( !array_key_exists($field_name,$get_options) ) {
            $get_options[$field_name] = '';
        }
        $options = $get_options;
        $html = '<select name="wji_account_mapping_options[acc_receivable]" class="wj-accounts-select2">';
        $html .= '<option></option>';
        if( $accounts = get_transient( 'wji_cached_journal_account' ) ) {
            foreach ($accounts as $account) {
                $html .= '<option value="' . esc_html( $account['id'] ) . '"'
                 . selected( $options['acc_receivable'], $account['id'], false ) . '>'
                 . esc_html( $account['text'] ) . '</option>';
            }
        }
        echo $html;
    }
    
    public function acc_tax_callback($args) {
        $get_options = get_option('wji_account_mapping_options');
        $field_name = 'acc_tax';
        if ( !array_key_exists($field_name,$get_options) ) {
            $get_options[$field_name] = '';
        }
        $options = $get_options;
        $html = '<select name="wji_account_mapping_options[acc_tax]" class="wj-accounts-select2">';
        $html .= '<option></option>';
        if( $accounts = get_transient( 'wji_cached_journal_account' ) ) {
            foreach ($accounts as $account) {
                $html .= '<option value="' . esc_html( $account['id'] ) . '"'
                 . selected( $options['acc_tax'], $account['id'], false ) . '>'
                 . esc_html( $account['text'] ) . '</option>';
            }
        }
        echo $html;
    }
    
    public function acc_payment_callback($args) {
        $get_options = get_option('wji_account_mapping_options');
        $field_name = 'acc_payment';
        if ( !array_key_exists($field_name,$get_options) ) {
            $get_options[$field_name] = '';
        }
        $options = $get_options;
        $html = '<select name="wji_account_mapping_options[acc_payment]" class="wj-accounts-select2">';
        $html .= '<option></option>';
        if( $accounts = get_transient( 'wji_cached_journal_account' ) ) {
            foreach ($accounts as $account) {
                $html .= '<option value="' . esc_html( $account['id'] ) . '"'
                 . selected( $options['acc_payment'], $account['id'], false ) . '>'
                 . esc_html( $account['text'] ) . '</option>';
            }
        }
        echo $html;
    }
    
    public function acc_stock_adjustments_callback($args) {
        $get_options = get_option('wji_account_mapping_options');
        $field_name = 'acc_stock_adjustments';
        if ( !array_key_exists($field_name,$get_options) ) {
            $get_options[$field_name] = '';
        }
        $options = $get_options;
        $html = '<select name="wji_account_mapping_options[acc_stock_adjustments]" class="wj-accounts-select2">';
        $html .= '<option></option>';
        if( $accounts = get_transient( 'wji_cached_journal_account' ) ) {
            foreach ($accounts as $account) {
                $html .= '<option value="' . esc_html( $account['id'] ) . '"'
                 . selected( $options['acc_stock_adjustments'], $account['id'], false ) . '>'
                 . esc_html( $account['text'] ) . '</option>';
            }
        }
        echo $html;
    }
    
    public function dynamic_payment_account_callback($args) {
        $payment_id = $args['payment_id'];
        $acc_name = 'acc_payment_'.$payment_id;
        $get_options = get_option('wji_account_mapping_options');
        if ( !array_key_exists($acc_name,$get_options) ) {
            $get_options[$acc_name] = '';
        }
        $options = $get_options;
        $html = '<select name="wji_account_mapping_options['.$acc_name.']" class="wj-accounts-select2">';
        $html .= '<option></option>';
        if( $accounts = get_transient( 'wji_cached_journal_account' ) ) {
            foreach ($accounts as $account) {
                $html .= '<option value="' . esc_html( $account['id'] ) . '"'
                 . selected( $options[$acc_name], $account['id'], false ) . '>'
                 . esc_html( $account['text'] ) . '</option>';
            }
        }
        echo $html;
    }
}