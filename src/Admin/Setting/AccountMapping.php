<?php

namespace Saksono\Woojurnal\Admin\Setting;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\Account as AccountApi;

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

        // Get existing wc payment methods
        $gateways = function_exists('WC') ? WC()->payment_gateways->payment_gateways() : [];

        if(count($gateways) > 0) {

            foreach ($gateways as $gateway) {
                $id = $gateway->id;
                $title = $gateway->title;

                add_settings_field( 
                    'acc_payment_'.$id,
                    $title,
                    [$this, 'dynamic_payment_account_callback'],
                    'wji_plugin_account_options',
                    'account_mapping_section',
                    [
                        'payment_id' => $id
                    ]
                ); 
            }
        }
    
        add_settings_section(
            'account_sales_mapping_section',
            'Sales Account',
            [$this, 'account_mapping_callback'],
            'wji_plugin_account_options'
        );

        $sales_account_fields = [
            [
                'id' => 'acc_sales', 
                'label' => 'Sales Account', 
                'callback' => [$this, 'acc_sales_callback'],
            ],
            [
                'id' => 'acc_receivable',
                'label' => 'Account Receivable',
                'callback' => [$this, 'acc_receivable_callback'],
            ],
            [
                'id' => 'acc_tax',
                'label' => 'Tax Account',
                'callback' => [$this, 'acc_tax_callback'],             
            ],
            [
                'id' => 'acc_stock_adjustments',
                'label' => 'Stock Adjustment Account',
                'callback' => [$this, 'acc_stock_adjustments_callback'],   
            ]
        ];

        foreach($sales_account_fields as $field)
        {
            add_settings_field( 
                $field['id'],
                $field['label'],
                $field['callback'],
                'wji_plugin_account_options',
                'account_sales_mapping_section'
            );
        }
    
        register_setting(
            'wji_account_mapping_options',
            'wji_account_mapping_options'
        );
    }

    public function account_mapping_callback() {
        $this->set_journal_accounts_cache();
    }

    public function acc_sales_callback($args) {
        $this->generate_account_dropdown('acc_sales');
    }
    
    public function acc_receivable_callback($args) {
        $this->generate_account_dropdown('acc_receivable');
    }
    
    public function acc_tax_callback($args) {
        $this->generate_account_dropdown('acc_tax');
    }
    
    public function acc_payment_callback($args) {
        $this->generate_account_dropdown('acc_payment');
    }
    
    public function acc_stock_adjustments_callback($args) {
        $this->generate_account_dropdown('acc_stock_adjustments');
    }
    
    public function dynamic_payment_account_callback($args) {
        $payment_id = $args['payment_id'];
        $acc_name = 'acc_payment_' . $payment_id;
        $this->generate_account_dropdown($acc_name);
    }

    private function generate_account_dropdown($field_name, $args = []) {
        $get_options = get_option('wji_account_mapping_options', []);
        if (!array_key_exists($field_name, $get_options)) {
            $get_options[$field_name] = '';
        }
    
        $html = '<select name="wji_account_mapping_options[' . esc_attr($field_name) . ']" class="wj-accounts-select2">';
        $html .= '<option></option>';
    
        if ($accounts = get_transient('wji_cached_journal_account')) {
            foreach ($accounts as $account) {
                $html .= '<option value="' . esc_html($account['id']) . '"'
                    . selected($get_options[$field_name], $account['id'], false) . '>'
                    . esc_html($account['text']) . '</option>';
            }
        }
        $html .= '</select>';
    
        echo $html;
    }

    private function set_journal_accounts_cache() {
        if( false === ( get_transient( 'wji_cached_journal_account' ) ) ) {
            $accountApi = new AccountApi();
            if( $accounts = $accountApi->getAll() ) {
                set_transient( 'wji_cached_journal_account', $accounts, 7 * DAY_IN_SECONDS );
            }
        }
    }
}