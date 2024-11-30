<?php

namespace Saksono\Woojurnal\Admin\Setting;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\JurnalApi;

class SettingsPage {

    public function __construct()
    {
        add_filter('plugin_action_links', [$this, 'plugin_settings_link'], 10, 2);
        add_action('admin_menu', [$this, 'create_settings_menu']);
        add_action('admin_init', [$this, 'initialize_general_options']);
        add_action('update_option_wji_plugin_general_options', [$this, 'on_option_update'], 10, 2);
	}

    /**
     * Add link to settings page on wp plugin page for easier navigation
     */
    public function plugin_settings_link($links, $file) {
    
        if ( $file == 'woo-jurnalid-integration/woo-jurnalid-integration.php' ) {
            $links['settings'] = sprintf( '<a href="%s"> %s </a>', admin_url( 'options-general.php?page=wji_settings' ), __( 'Settings' ) );
        }
        return $links;
    }

    /**
     * Create options page
     */
    public function create_settings_menu()
    {
        $plugin_page = add_options_page(
            'WooCommerce Jurnal.ID Integration',
            'WooCommerce Jurnal.ID Integration',
            'manage_woocommerce',
            'wji_settings',
            [$this, 'settings_display']
        );

        add_action('load-' . $plugin_page, [$this, 'add_help_tab']);
    }

    /**
     * Create help tab
     */
    public function add_help_tab () {
        $screen = get_current_screen();
     
        // Add my_help_tab if current screen is My Admin Page
        $screen->add_help_tab( array(
            'id'    => 'wji_help_configuration',
            'title' => __('Pengaturan Plugin'),
            'content'   => '
                <ol>
                    <li>Pastikan menggunakan <b>API Key</b> yang diambil dari akun Jurnal.ID.</li>
                    <li>Set <b>Account Mapping</b> yang sesuai untuk pembuatan data Jurnal Entry dan Stock Adjustments di Jurnal.ID.</li>
                    <li>Set <b>Gudang dan Product Mapping</b> yang sesuai untuk pembuatan data Stock Adjustments.</li>
                </ol>
            ',
        ) );
    
        $screen->add_help_tab( array(
            'id'    => 'wji_help_process_flow',
            'title' => __('Process Flow'),
            'content'   => '
                <ol>
                    <li>Belum ada Pembayaran = Order status On Hold. Buat <b>Jurnal Entry</b> di Jurnal.ID sesuai Account Mapping.</li>
                    <li>Pembayaran Masuk =  Order status Processing. Update <b>Jurnal Entry</b> yang dibuat di Poin 1 sesuai Account Mapping.</li>
                    <li>Order = Processing. Buat <b>Stock Adjustment</b> di Jurnal.ID sesuai Product Mapping yang ada di Order.</li>
                    <li>Jika ada Product yang belum di mapping ketika sync berjalan, maka <b>Stock Adjusment</b> akan dibuat dengan Product yang sudah di mapping saja.</li>
                    <li>Proses sinkronisasi ke Jurnal.ID berjalan secara otomatis setiap <b>5 menit</b>.</li>
                    <li>Histori sinkronisasi dan statusnya bisa dilihat di <b>Sync History</b>.</li>
                </ol>
            ',
        ) );
    }

    /**
     * Renders a page to display for the plugin settings
     */
    public function settings_display()
    {
        ?>
        <div class="wrap">
            <h2>WooCommerce Jurnal.ID Integration</h2>
            
            <?php $active_tab = isset( $_GET[ 'tab' ] ) ? sanitize_text_field($_GET[ 'tab' ]) : 'general_options'; ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=wji_settings&tab=general_options" class="nav-tab <?php echo $active_tab == 'general_options' ? 'nav-tab-active' : ''; ?>">Jurnal.ID Setting</a>
                <a href="?page=wji_settings&tab=account_options" class="nav-tab <?php echo $active_tab == 'account_options' ? 'nav-tab-active' : ''; ?>">Account Mapping</a>
                <a href="?page=wji_settings&tab=product_options" class="nav-tab <?php echo $active_tab == 'product_options' ? 'nav-tab-active' : ''; ?>">Product Mapping</a>
                <a href="?page=wji_settings&tab=order_options" class="nav-tab <?php echo $active_tab == 'order_options' ? 'nav-tab-active' : ''; ?>">Sync History</a>
            </h2>
            
            <?php
                if( $active_tab == 'general_options' ) {
                    echo '<form method="post" action="options.php">';
                    settings_fields( 'wji_plugin_general_options' );
                    do_settings_sections( 'wji_plugin_general_options' );
                    do_settings_sections( 'stock_settings_section' );
                    submit_button('Simpan Pengaturan');
                    echo '</form>';
                } elseif( $active_tab == 'account_options' ) {
                    echo '<form method="post" action="options.php">';
                    settings_fields( 'wji_account_mapping_options' );
                    do_settings_sections( 'wji_plugin_account_options' );
                    submit_button('Simpan Pengaturan');
                    echo '</form>';
                } elseif( $active_tab == 'product_options' ) {
                    do_settings_sections( 'wji_plugin_product_mapping_options' );
                } elseif( $active_tab == 'order_options' ) {
                    do_settings_sections( 'wji_plugin_order_sync_options' );
                }
            ?>
        </div>
        <?php
    }

    /**
     * Create main plugin options setting
     */
    public function initialize_general_options()
    {
        if( false == get_option( 'wji_plugin_general_options' ) ) {  
            add_option( 'wji_plugin_general_options', array() );
        }

        // Register a section
        add_settings_section(
            'general_settings_section',
            'Pengaturan API',
            [$this, 'general_section_callback'],
            'wji_plugin_general_options'
        );

        // Register a section
        add_settings_section(
            'tax_settings_section',             
            'Pengaturan Pajak',                 // Title to be displayed on the administration page
            [$this, 'tax_section_callback'],    // Callback used to render the description of the section
            'wji_plugin_general_options'        // Page on which to add this section of options
        );

        // Register a section
        add_settings_section(
            'stock_settings_section',           
            'Pengaturan Stok Produk',
            [$this, 'stock_section_callback'],
            'wji_plugin_general_options'
        );

        // Create the settings
        add_settings_field(
            'client_id',
            'Client ID',
            [$this, 'client_id_callback'],
            'wji_plugin_general_options',
            'general_settings_section'
        );

        add_settings_field( 
            'client_secret',                    // ID used to identify the field throughout the theme
            'Client Secret',                    // The label to the left of the option interface element
            [$this, 'client_secret_callback'],  // The name of the function responsible for rendering the option interface
            'wji_plugin_general_options',       // The page on which this option will be displayed
            'general_settings_section'          // The name of the section to which this field belongs
        );
    
        add_settings_field( 
            'include_tax',
            'Sinkronisasi Pajak',
            [$this, 'include_tax_callback'],
            'wji_plugin_general_options',       
            'tax_settings_section'  
        );
    
        add_settings_field( 
            'sync_stock',
            'Sinkronisasi Stok',
            [$this, 'sync_stock_callback'],
            'wji_plugin_general_options',     
            'stock_settings_section'
        );
    
        add_settings_field( 
            'wh_id',                            
            'Warehouse',                        
            [$this, 'wh_id_callback'],               
            'wji_plugin_general_options',       
            'stock_settings_section'
        );

        // Register the fields with WordPress 
        register_setting(
            'wji_plugin_general_options',           // A settings group name
            'wji_plugin_general_options',           // The name of an option to sanitize and save
            'validate_general_options'              // Validate callback
        );
    }

    /* ------------------------------------------------------------------------ *
    * Section Callbacks
    * ------------------------------------------------------------------------ */

    public function general_section_callback()
    {
        if( get_option( 'wji_plugin_api_valid', false ) && $profile_name = get_option( 'wji_plugin_profile_full_name', false ) ) {
            echo '<p>Successfully connected to Jurnal.ID. Welcome, <b>'.$profile_name.'</b> &#128075;</p>';
        } else {
            echo '<p>Masukan kredential dari aplikasi yang didaftarkan di <a href="https://developers.mekari.com/dashboard/applications" title="Mekari Developer" target="_blank">Mekari Developer</a></p>';
        }   
    }

    public function tax_section_callback() {
        return '';
    }

    public function stock_section_callback() {
        // Check if cached data available
        if( false === ( get_transient( 'wji_cached_journal_warehouses' ) ) ) {
            // Set list of accounts for future uses
            $api = new JurnalApi();
            $warehouses = $api->getAllJurnalWarehouses();
            if( $warehouses && count($warehouses)>0 ) {
                set_transient( 'wji_cached_journal_warehouses', $warehouses, 7 * DAY_IN_SECONDS );
            }
        }
    }

    /* ------------------------------------------------------------------------ *
    * Field Callbacks
    * ------------------------------------------------------------------------ */

    public function client_id_callback()
    {
        $options = get_option('wji_plugin_general_options', []);
        $field_name = 'client_id';
        $value = $options[$field_name] ?? '';
        echo '<input type="text" id="wji_client_id" name="wji_plugin_general_options[' . esc_attr($field_name) . ']" value="' . esc_attr($value) . '" />';
    }

    public function client_secret_callback()
    {
        $options = get_option('wji_plugin_general_options', []);
        $field_name = 'client_secret';
        $value = $options[$field_name] ?? '';
        echo '<input type="password" id="wji_client_secret" name="wji_plugin_general_options[' . esc_attr($field_name) . ']" value="' . esc_attr($value) . '" />';
    }
    
    public function include_tax_callback($args) {
        $options = get_option('wji_plugin_general_options', []);
        $field_name = 'include_tax';
        $value = $options[$field_name] ?? '';
        
        echo '<label for="wji_include_tax">';
        echo '<input type="checkbox" id="wji_include_tax" name="wji_plugin_general_options[' . esc_attr($field_name) . ']" value="1"' . checked(1, $value, false) . ' />';
        echo ' Aktifkan sinkronisasi akun pajak';
        echo '</label>';
    }
    
    public function sync_stock_callback($args) {
        $options = get_option('wji_plugin_general_options', []);
        $field_name = 'sync_stock';
        $value = $options[$field_name] ?? '';
        
        echo '<label for="wji_sync_stock">';
        echo '<input type="checkbox" id="wji_sync_stock" name="wji_plugin_general_options[' . esc_attr($field_name) . ']" value="1"' . checked(1, $value, false) . ' />';
        echo ' Aktifkan sinkronisasi akun pajak';
        echo '</label>';
    }

    public function wh_id_callback($args) {
        // Get plugin options
        $options = get_option('wji_plugin_general_options', []);
        $field_name = 'wh_id';
        $selected_value = $options[$field_name] ?? '';
    
        // Start the HTML for the select dropdown
        $html = '<select id="wji_warehouse_id" name="wji_plugin_general_options[' . esc_attr($field_name) . ']" class="wj-warehouses-select2">';
        $html .= '<option value="">' . esc_html__('Select a warehouse', 'wji-plugin') . '</option>';
    
        // Check for cached warehouses in the transient
        if ($warehouses = get_transient('wji_cached_journal_warehouses')) {
            foreach ($warehouses as $wh) {
                $html .= sprintf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($wh['id']),
                    selected($selected_value, $wh['id'], false),
                    esc_html($wh['text'])
                );
            }
        } else {
            $html .= '<option value="" disabled>' . esc_html__('No warehouses available', 'wji-plugin') . '</option>';
        }
    
        // Close the select dropdown
        $html .= '</select>';
    
        // Optionally, include a description or helper text
        if (isset($args['description'])) {
            $html .= '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    
        echo $html;
    }    

    /* ------------------------------------------------------------------------ *
    * Field Validations
    * ------------------------------------------------------------------------ */

    public function validate_general_options($input) {
        $output = array();
        foreach( $input as $key => $value ) {
            // Check to see if the current option has a value. If so, process it.
            if( isset( $input[$key] ) ) {
                // Strip all HTML and PHP tags and properly handle quoted strings
                $output[$key] = strip_tags( stripslashes( $input[ $key ] ) );
            }
        }

        return $output;
    }

    public function on_option_update($old_value, $new_value) {

        // Clear cached data when settings changes
        delete_transient( 'wji_cached_journal_products' );
        delete_transient( 'wji_cached_journal_account' );
        delete_transient( 'wji_cached_journal_warehouses' );

        // Clear option
        update_option( 'wji_plugin_api_valid', false );
        update_option( 'wji_plugin_profile_full_name', false );

        // Check API key valid
        $api = new JurnalApi;
        $validApi = $api->checkApiKeyValid();
    }   
}