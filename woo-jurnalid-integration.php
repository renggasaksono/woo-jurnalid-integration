<?php
/**
 * Plugin Name:       WooCommerce Jurnal.ID Integration
 * Description:       Integrasi data pemesanan dan stok produk dari WooCommerce ke Jurnal.ID.
 * Version:           1.7.1
 * Requires at least: 5.5
 * Author:            Rengga Saksono
 * Author URI:        https://masrengga.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if( ! class_exists( 'WJI_DbTableCreator' ) ) {
    require_once( 'includes/WJI_DbTableCreator.php' );
}
if( ! class_exists( 'WJI_TableList' ) ) {
    require_once( 'includes/WJI_TableList.php' );
}
if( ! class_exists( 'WJI_AjaxCallback' ) ) {
    require_once( 'includes/WJI_AjaxCallback.php' );
}
if( ! class_exists( 'WJI_IntegrationAPI' ) ) {
    require_once( 'includes/WJI_IntegrationAPI.php' );
}
if( ! class_exists( 'WJI_Helper' ) ) {
    require_once( 'includes/WJI_Helper.php' );
}

/* ------------------------------------------------------------------------ *
 * Plugin Activation & Deactivation
 * ------------------------------------------------------------------------ */

/**
 * Run on plugin activation
 */
register_activation_hook( __FILE__, 'wji_on_activation' );
function wji_on_activation() {
    
    // Checks if WooCommerce active
    $wc = class_exists( 'WooCommerce' );
    if(!$wc) {
        deactivate_plugins( basename( __FILE__ ) );
        wp_die("Maaf, Plugin WooCommerce tidak ditemukan.");
    }

    // Create db tables
    $tc = new WJI_DbTableCreator();
    $tc->wji_create_product_mapping_table(); // buat table untuk translasi item jurnal dan woo
    $tc->wcbc_create_order_sync_table();

    // Schedule cron event
    if( !wp_next_scheduled( 'wji_cronjob_event' ) ) {
       wp_schedule_event( time(), 'wji_everyminute', 'wji_cronjob_event' );  
    }
}

/**
 * Run on plugin deactivation
 */
register_deactivation_hook( __FILE__, 'wji_deactivate');
function wji_deactivate() {
    global $wpdb;

    // Clear scheduled cron job
    $timestamp = wp_next_scheduled ('wji_cronjob_event');
    wp_unschedule_event ($timestamp, 'wji_cronjob_event');

    // Clear cached data when settings changes
    delete_transient( 'wji_cached_journal_products' );
    delete_transient( 'wji_cached_journal_account' );
    delete_transient( 'wji_cached_journal_warehouses' );
}

/**
 * Run on plugin uninstallation
 */
register_uninstall_hook( __FILE__, 'wji_uninstall');
function wji_uninstall() {
    global $wpdb;

    // Delete plugin options
    delete_option( 'wji_plugin_general_options' );
    delete_option( 'wji_account_mapping_options' );

    // Drop db tables
    $wpdb->query( "DROP TABLE {$wpdb->prefix}wji_product_mapping" );
    $wpdb->query( "DROP TABLE {$wpdb->prefix}wji_order_sync_log" );
}

/* ------------------------------------------------------------------------ *
 * Display Plugin Settings
 * ------------------------------------------------------------------------ */

/**
 * Add custom plugin menu and settings page
 */
function wji_create_settings_menu() {
    $plugin_page = add_options_page(
        'Pengaturan Integrasi WooCommerce dan Jurnal.ID', //Page Title
        'WooCommerce Jurnal.ID Integration', //Menu Title
        'manage_woocommerce', //Capability
        'wji_settings', //Page slug
        'wji_settings_display' //Callback to print html
    );

    // Adds my_help_tab when my_admin_page loads
    add_action( 'load-'.$plugin_page, 'wji_add_help_tab' );
}
add_action('admin_menu', 'wji_create_settings_menu');

function wji_add_help_tab () {
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
                <li>Buat <b>Jurnal Entry</b> di Jurnal.ID secara otomatis sesuai Account Mapping ketika ada Order WC via checkout web, Order = On Hold.</li>
                <li>Update <b>Jurnal Entry</b> yang dibuat di Poin 1 ketika ada pembayaran masuk dan status Order = Processing.</li>
                <li>Buat <b>Stock Adjustments</b> di Jurnal.ID sesuai produk yang ada di Order sesuai Product Mapping ketika status Order = Processing.</li>
                <li>Proses sinkronisasi ke Jurnal.ID berjalan secara otomatis setiap <b>1 menit</b>.</li>
                <li>History sinkronisasi yang dilakukan dan statusnya bisa dilihat di <b>Sync History</b>.</li>
            </ol>
        ',
    ) );
}

/**
 * Add link to settings page on wp plugin page for easier navigation
 */
add_filter('plugin_action_links', 'wji_plugin_settings_link', 10, 2);
function wji_plugin_settings_link($links, $file) {
 
    if ( $file == 'woo-jurnalid-integration/woo-jurnalid-integration.php' ) {
        $links['settings'] = sprintf( '<a href="%s"> %s </a>', admin_url( 'options-general.php?page=wji_settings' ), __( 'Settings' ) );
    }
    return $links;
}

/**
 * Renders a simple page to display for the plugin settings pagedefined above
 */
function wji_settings_display() {
?>
    <div class="wrap">
        <h2>WooCommerce Jurnal.ID Integration</h2>

        <?php $active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general_options'; ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=wji_settings&tab=general_options" class="nav-tab <?php echo $active_tab == 'general_options' ? 'nav-tab-active' : ''; ?>">Jurnal.ID Setting</a>
            <a href="?page=wji_settings&tab=account_options" class="nav-tab <?php echo $active_tab == 'account_options' ? 'nav-tab-active' : ''; ?>">Account Mapping</a>
            <a href="?page=wji_settings&tab=product_options" class="nav-tab <?php echo $active_tab == 'product_options' ? 'nav-tab-active' : ''; ?>">Product Mapping</a>
            <a href="?page=wji_settings&tab=order_options" class="nav-tab <?php echo $active_tab == 'order_options' ? 'nav-tab-active' : ''; ?>">Sync History</a>
        </h2>
         
        <?php
            // Check API key valid
            $api = new WJI_IntegrationAPI;
            $validApi = $api->checkApiKeyValid();

            if( $active_tab == 'general_options' ) {
                echo '<form method="post" action="options.php">';
                settings_fields( 'wji_plugin_general_options' );
                do_settings_sections( 'wji_plugin_general_options' );
                do_settings_sections( 'stock_settings_section' );
                submit_button('Simpan Pengaturan');
                echo '</form>';
            } elseif( $active_tab == 'account_options' ) {
                if( !$validApi ) { echo '<p>API Key tidak valid. Silahkan mengisi API Key di Pengaturan.</p>'; }
                else {
                    echo '<form method="post" action="options.php">';
                    settings_fields( 'wji_account_mapping_options' );
                    do_settings_sections( 'wji_plugin_account_options' );
                    submit_button('Simpan Pengaturan');
                    echo '</form>';
                }
            } elseif( $active_tab == 'product_options' ) {
                if( !$validApi ) { echo '<p>API Key tidak valid. Silahkan mengisi API Key di Pengaturan.</p>'; }
                else {
                    do_settings_sections( 'wji_plugin_product_mapping_options' );
                }
            } elseif( $active_tab == 'order_options' ) {
                if( !$validApi ) { echo '<p>API Key tidak valid. Silahkan mengisi API Key di Pengaturan.</p>'; }
                else {
                    do_settings_sections( 'wji_plugin_order_sync_options' );
                }
            }
        ?>
    </div>
<?php
}

/* ------------------------------------------------------------------------ *
 * Setting Registration
 * ------------------------------------------------------------------------ */

/**
 * Initialize general options settings for plugin
 */
function wji_initialize_general_options() {
 
    if( false == get_option( 'wji_plugin_general_options' ) ) {  
        add_option( 'wji_plugin_general_options', array() );
    }

    // Register a section
    add_settings_section(
        'general_settings_section',         // ID used to identify this section and with which to register options
        'Pengaturan Umum',                  // Title to be displayed on the administration page
        'wji_general_options_callback',     // Callback used to render the description of the section
        'wji_plugin_general_options'        // Page on which to add this section of options
    );

    // Register a section
    add_settings_section(
        'stock_settings_section',           // ID used to identify this section and with which to register options
        'Pengaturan Stok Produk',           // Title to be displayed on the administration page
        'wji_stock_section_callback',       // Callback used to render the description of the section
        'wji_plugin_general_options'        // Page on which to add this section of options
    );
     
    // Create the settings
    add_settings_field( 
        'api_key',                          // ID used to identify the field throughout the theme
        'API Key',                          // The label to the left of the option interface element
        'wji_api_key_callback',             // The name of the function responsible for rendering the option interface
        'wji_plugin_general_options',       // The page on which this option will be displayed
        'general_settings_section'          // The name of the section to which this field belongs
    );

    add_settings_field( 
        'include_tax',                      // ID used to identify the field throughout the theme
        'Kalkulasi Nilai Pajak',            // The label to the left of the option interface element
        'wji_include_tax_callback',         // The name of the function responsible for rendering the option interface
        'wji_plugin_general_options',       // The page on which this option will be displayed
        'general_settings_section'          // The name of the section to which this field belongs
    );

    add_settings_field( 
        'sync_stock',                       // ID used to identify the field throughout the theme
        'Sinkronisasi Stok',                // The label to the left of the option interface element
        'wji_sync_stock_callback',          // The name of the function responsible for rendering the option interface
        'wji_plugin_general_options',       // The page on which this option will be displayed
        'stock_settings_section'            // The name of the section to which this field belongs
    );

    add_settings_field( 
        'wh_id',                            // ID used to identify the field throughout the theme
        'Gudang',                           // The label to the left of the option interface element
        'wji_wh_id_callback',               // The name of the function responsible for rendering the option interface
        'wji_plugin_general_options',       // The page on which this option will be displayed
        'stock_settings_section'            // The name of the section to which this field belongs
    );

    add_settings_field( 
        'jurnal_item_count',                // ID used to identify the field throughout the theme
        'Total Jumlah Produk',              // The label to the left of the option interface element
        'wji_jurnal_item_count_callback',   // The name of the function responsible for rendering the option interface
        'wji_plugin_general_options',       // The page on which this option will be displayed
        'stock_settings_section'            // The name of the section to which this field belongs
    );

    // Register the fields with WordPress 
    register_setting(
        'wji_plugin_general_options',           // A settings group name
        'wji_plugin_general_options',           // The name of an option to sanitize and save
        'wji_plugin_validate_general_options',  // Validate callback
    );
}
add_action('admin_init', 'wji_initialize_general_options');

/**
 * Initializes plugin's product mapping between products in WooCommerce and products in Jurnal.ID
 *
 */
function wji_intialize_product_options() {
 
    add_settings_section(
        'product_settings_section',             // ID used to identify this section and with which to register options
        '',                                     // Title to be displayed on the administration page
        'wji_product_mapping_callback',         // Callback used to render the description of the section
        'wji_plugin_product_mapping_options'    // Page on which to add this section of options
    );
}
add_action( 'admin_init', 'wji_intialize_product_options' );

/**
 * Initializes plugin's order sync log section
 *
 */
function wji_intialize_order_sync_options() {
 
    add_settings_section(
        'order_sync_section',               // ID used to identify this section and with which to register options
        '',                                 // Title to be displayed on the administration page
        'wji_order_sync_callback',          // Callback used to render the description of the section
        'wji_plugin_order_sync_options'     // Page on which to add this section of options
    );
}
add_action( 'admin_init', 'wji_intialize_order_sync_options' );

/**
 * Initialize account mapping section and options
 */
function wji_initialize_account_mapping_section() {
 
    if( false == get_option( 'wji_account_mapping_options' ) ) {  
        add_option( 'wji_account_mapping_options', array() );
    }

    /** PAYMENT ACCOUNTS **/

    // Get existing wc payment methods
    $gateways = WC()->payment_gateways->payment_gateways();
    if(count($gateways) > 0) {

        // Register a section
        add_settings_section(
            'account_mapping_section',          // ID used to identify this section and with which to register options
            'Payment Account',                  // Title to be displayed on the administration page
            'wji_account_mapping_callback',     // Callback used to render the description of the section
            'wji_plugin_account_options'        // Page on which to add this section of options
        );

        foreach ($gateways as $gateway) {
            $id = $gateway->id;
            $title = $gateway->title;

            // Create the settings
            add_settings_field( 
                'acc_payment_'.$id,                         // ID used to identify the field throughout the theme
                $title.' Account ',                         // The label to the left of the option interface element
                'wji_dynamic_payment_account_callback',     // The name of the function responsible for rendering the option interface
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
        'wji_account_mapping_callback',     // Callback used to render the description of the section
        'wji_plugin_account_options'        // Page on which to add this section of options
    );

    // Create the settings
    add_settings_field( 
        'acc_sales',                        // ID used to identify the field throughout the theme
        'Sales Account',                    // The label to the left of the option interface element
        'wji_acc_sales_callback',           // The name of the function responsible for rendering the option interface
        'wji_plugin_account_options',       // The page on which this option will be displayed
        'account_sales_mapping_section'     // The name of the section to which this field belongs
    );

    // Create the settings
    add_settings_field( 
        'acc_receivable',                   // ID used to identify the field throughout the theme
        'Account Receivable',               // The label to the left of the option interface element
        'wji_acc_receivable_callback',      // The name of the function responsible for rendering the option interface
        'wji_plugin_account_options',       // The page on which this option will be displayed
        'account_sales_mapping_section'     // The name of the section to which this field belongs
    );

    // Create the settings
    add_settings_field( 
        'acc_tax',                          // ID used to identify the field throughout the theme
        'Tax Account',                      // The label to the left of the option interface element
        'wji_acc_tax_callback',             // The name of the function responsible for rendering the option interface
        'wji_plugin_account_options',       // The page on which this option will be displayed
        'account_sales_mapping_section'     // The name of the section to which this field belongs
    );

    // Create the settings
    add_settings_field( 
        'acc_stock_adjustments',                // ID used to identify the field throughout the theme
        'Stock Adjustment Account',             // The label to the left of the option interface element
        'wji_acc_stock_adjustments_callback',   // The name of the function responsible for rendering the option interface
        'wji_plugin_account_options',           // The page on which this option will be displayed
        'account_sales_mapping_section'         // The name of the section to which this field belongs
    );

    // Register the fields with WordPress 
    register_setting(
        'wji_account_mapping_options',      // A settings group name
        'wji_account_mapping_options'       // The name of an option to sanitize and save
    );
}
add_action('admin_init', 'wji_initialize_account_mapping_section');

/* ------------------------------------------------------------------------ *
 * Section Callbacks
 * ------------------------------------------------------------------------ */
 
function wji_general_options_callback() {
    return '';
}

function wji_stock_section_callback() {
    // Check if cached data available
    if( false === ( get_transient( 'wji_cached_journal_warehouses' ) ) ) {
        // Set list of accounts for future uses
        $api = new WJI_IntegrationAPI();
        $warehouses = $api->getAllJurnalWarehouses();
        if( $warehouses && count($warehouses)>0 ) {
            set_transient( 'wji_cached_journal_warehouses', $warehouses, 7 * DAY_IN_SECONDS );
        }
    }
}

function wji_product_mapping_callback() {
    global $wpdb;
    $tablelist = new WJI_TableList();

    $data = [];
    $table_name = $wpdb->prefix . 'wji_product_mapping';
    $table_post = $wpdb->prefix . 'posts';
    $offset = $tablelist->getPerpage() * ($tablelist->get_pagenum() - 1);
    $where = '';

    $products = $wpdb->get_results("select wjpm.* from {$table_name} wjpm join {$table_post} p on wjpm.wc_item_id=p.id {$where} order by post_name limit {$tablelist->getPerpage()} offset {$offset}");
    $count = $wpdb->get_var("select count(id) from {$table_name} {$where}");

    $tablelist->setTotalItem($count);
    $tablelist->setDatas($products);
    $tablelist->setColumns([
        'serialid' => '#',
        'wcproductname' => 'Produk pada Woocommerce (SKU - Nama Produk)',
        'jurnal_item_code' => 'Produk pada Jurnal.ID (SKU - Nama Produk)'
    ]);
    
    $tablelist->generate();
}

function wji_order_sync_callback() {
    global $wpdb;
    $tablelist = new WJI_TableList();

    $data = [];
    $table_name = $wpdb->prefix . 'wji_order_sync_log';
    $offset = $tablelist->getPerpage() * ($tablelist->get_pagenum() - 1);
    $where = '';

    $products = $wpdb->get_results("select * from {$table_name} order by created_at desc limit {$tablelist->getPerpage()} offset {$offset}");
    $count = $wpdb->get_var("select count(id) from {$table_name} {$where}");
    $tablelist->setTotalItem($count);
    $tablelist->setDatas($products);
    $tablelist->setColumns([
        'serialid'          => '#',
        'wc_order_id'       => 'Order ID',
        'sync_action'       => 'Action',
        'sync_status'       => 'Status',
        'sync_note'         => 'Pesan',
        'sync_at'           => 'Tanggal'
    ]);
    
    $tablelist->generate();
}

function wji_account_mapping_callback() {
    // Check if cached data available
    if( false === ( get_transient( 'wji_cached_journal_account' ) ) ) {
        // Set list of accounts for future uses
        $api = new WJI_IntegrationAPI();
        if( $accounts = $api->getAllJurnalAccounts() ) {
            set_transient( 'wji_cached_journal_account', $accounts, 7 * DAY_IN_SECONDS );
        }
    }
}

/* ------------------------------------------------------------------------ *
 * Field Callbacks
 * ------------------------------------------------------------------------ */
 
function wji_api_key_callback($args) {
    $get_options = get_option('wji_plugin_general_options');
    $field_name = 'api_key';
    if ( !array_key_exists($field_name,$get_options) ) {
        $get_options[$field_name] = '';
    }
    $options = $get_options;
    $html = '<input type = "text" class="regular-text" id="api_key" name="wji_plugin_general_options[api_key]" value="' . sanitize_text_field($options['api_key']) . '">';
    echo $html;
}

function wji_wh_id_callback($args) {
    $api = new WJI_IntegrationAPI();
    if ( !$api->checkApiKeyValid() ) {
        echo 'API Key tidak valid.';
    } else {

        $get_options = get_option('wji_plugin_general_options');
        $field_name = 'wh_id';
        if ( !array_key_exists($field_name,$get_options) ) {
            $get_options[$field_name] = '';
        }
        $options = $get_options;

        $html = '<select name="wji_plugin_general_options[wh_id]" class="wj-warehouses-select2">';
        $html .= '<option></option>';
        if( $warehouses = get_transient( 'wji_cached_journal_warehouses' ) ) {
            foreach ($warehouses as $wh) {
                $html .= '<option value="' . esc_html( $wh['id'] ) . '"'
                 . selected( $options[$field_name], $wh['id'], false ) . '>'
                 . esc_html( $wh['text'] ) . '</option>';
            }
        }
        echo $html;
    }
}

function wji_jurnal_item_count_callback($args) {
    $get_options = get_option('wji_plugin_general_options');
    $field_name = 'jurnal_item_count';
    if ( !array_key_exists($field_name,$get_options) ) {
        $get_options[$field_name] = '';
    }
    $options = $get_options;
    $html = '<input type = "text" class="regular-text" id="jurnal_item_count" name="wji_plugin_general_options[jurnal_item_count]" value="' . sanitize_text_field($options['jurnal_item_count']) . '">'; 
    echo $html;
}

function wji_include_tax_callback($args) {
    $get_options = get_option('wji_plugin_general_options');
    $field_name = 'include_tax';
    if ( !array_key_exists($field_name,$get_options) ) {
        $get_options[$field_name] = '';
    }
    $html = '<input type="checkbox" name="wji_plugin_general_options['.$field_name.']" value="1"' . checked( 1, $get_options['include_tax'], false ) . '/>';
    $html .= '<label for="include_tax">Harga produk sudah termasuk 10% pajak</label>';
    echo $html;
}

function wji_sync_stock_callback($args) {
    $get_options = get_option('wji_plugin_general_options');
    $field_name = 'sync_stock';
    if ( !array_key_exists($field_name,$get_options) ) {
        $get_options[$field_name] = '';
    }
    $html = '<input type="checkbox" name="wji_plugin_general_options['.$field_name.']" value="1"' . checked( 1, $get_options[$field_name], false ) . '/>';
    $html .= '<label for="sync_stock">Aktifkan sinkronisasi stok produk ketika status Order = Processing</label>';
    echo $html;
}

function wji_acc_sales_callback($args) {
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

function wji_acc_receivable_callback($args) {
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

function wji_acc_tax_callback($args) {
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

function wji_acc_payment_callback($args) {
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

function wji_acc_stock_adjustments_callback($args) {
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

function wji_dynamic_payment_account_callback($args) {
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

/* ------------------------------------------------------------------------ *
 * Field Validations
 * ------------------------------------------------------------------------ */

function wji_plugin_validate_general_options($input) {
    $output = array();
    foreach( $input as $key => $value ) {
        // Check to see if the current option has a value. If so, process it.
        if( isset( $input[$key] ) ) {
            // Strip all HTML and PHP tags and properly handle quoted strings
            $output[$key] = strip_tags( stripslashes( $input[ $key ] ) );
        }
    }
    
    // Clear cached data when settings changes
    delete_transient( 'wji_cached_journal_products' );
    delete_transient( 'wji_cached_journal_account' );
    delete_transient( 'wji_cached_journal_warehouses' );

    return $output;
}

/* ------------------------------------------------------------------------ *
 * WooCommerce Hooks
 * ------------------------------------------------------------------------ */

/*
// Flow order sync
1. Order dibuat, set sync_action = JE_CREATE, status = 'UNSYNCED'
2. Sync jalan, sync_action = JE_CREATE, status = 'SYNCED'
3. Order = processing, buat record baru dengan sync_action = JE_UPDATE, status = 'UNSYNCED'
4. Sync jalan, update record bary sync_action = JE_UPDATE, status = 'SYNCED'
5. Order = processing, buat record baru untuk stock adj, sync_action = SA_CREATE, status = 'UNSYNCED'
6. Sync jalan, update record stock adj, sync_status = SYNCED

*/
/**
 * Fungsi ini dipanggil setelah order berhasil dibuat ketika checkout via web
 * Referensi https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-checkout.html#source-view.403
 *
*/
add_action( 'woocommerce_new_order', 'wji_new_order_created', 10, 2 );
function wji_new_order_created( $order_id, $order ) {
    global $wpdb;
    $api = new WJI_IntegrationAPI();

    $order_status = $order->get_status();
    $table_name = $wpdb->prefix . 'wji_order_sync_log';

    // Check order status
    if($order_status == 'pending') {

        // Insert to db sync table with action je_create
        $insert_new_order = $wpdb->insert($table_name, [
            'wc_order_id' => $order_id,
            'sync_action' => 'JE_CREATE',
            'sync_status' => 'UNSYNCED'
        ]);

    } elseif ($order_status == 'processing') {

        // Check if previous JE_CREATE sync exists
        $where = 'WHERE wc_order_id='.$order_id.' AND sync_action="JE_CREATE" AND sync_status="SYNCED"';
        $get_results = $wpdb->get_row("SELECT * FROM {$table_name} {$where}");

        // If not, immediately get journal entry id so it doesn't conflicts with current business flow
        if(empty($get_results)) {

            // DIRECTLY API CALL JUST TO GET THE JOURNAL ENTRY ID

            $get_options = get_option('wji_account_mapping_options');
            $acc_receivable = $api->getJurnalAccountName( $get_options['acc_receivable'] );
            $acc_tax = $api->getJurnalAccountName( $get_options['acc_tax'] );
            $acc_sales = $api->getJurnalAccountName( $get_options['acc_sales'] );
            $acc_stock_adjustments = $api->getJurnalAccountName( $get_options['acc_stock_adjustments'] );

            // Ambil data order nya
            $order_id = $order->get_id();
            $order_created_date = $order->get_date_created()->format( 'Y-m-d' );
            $order_billing_first_name = strtoupper($order->get_billing_first_name());
            $order_billing_last_name = strtoupper($order->get_billing_last_name());
            $order_billing_full_name = $order_billing_first_name.'-'.$order_billing_last_name;
            $order_trans_no = $order_id . '-' . $order_billing_full_name;
            $order_total = $order->get_total();

            // Calculate tax
            $general_options = get_option('wji_plugin_general_options');
            if( !$general_options['include_tax'] ) {
                $order_tax = $order->total_tax;
                $order_total_after_tax = $order_total;
            } else {
                $order_tax = $order_total * 0.1;
                $order_total_after_tax = $order_total - $order_tax;
            }

            // Sample format data untuk order dengan status pembayaran BELUM lunas, refer ke sample di akun Jurnal.ID
            $data = array(
                "journal_entry" => array(
                    "transaction_date" => $order_created_date,
                    "transaction_no" => $order_trans_no,
                    "memo" => 'Order On Hold',
                    "transaction_account_lines_attributes" => [
                        [
                            "account_name" => $acc_receivable,
                            "debit" => $order_total,
                        ],
                        [
                            "account_name" => $acc_tax,
                            "credit" => $order_tax,
                        ],
                        [
                            "account_name" => $acc_sales,
                            "credit" => $order_total_after_tax,
                        ]
                    ],
                    "tags" => [
                        'WooCommerce Order ID: '.$order_id,
                        $order_billing_full_name
                    ]
                )
            );

            // Make the API call
            $postJournalEntry = $api->postJournalEntry($data);

            // THEN INSERT THE REAL JE_UPDATE
            if( isset($postJournalEntry->journal_entry) ) {
                // Then, insert the update sync action
                $jurnal_entry_id = $postJournalEntry->journal_entry->id;
                $wpdb->insert($table_name, [
                    'wc_order_id' => $order_id,
                    'jurnal_entry_id' => $jurnal_entry_id,
                    'sync_action' => 'JE_UPDATE',
                    'sync_status' => 'UNSYNCED'
                ]);

                // 2. Create stock adjustments if enabled
                $options = get_option('wji_plugin_general_options');
                if($options['sync_stock']) {
                    $wpdb->insert($table_name, [
                        'wc_order_id' => $order_id,
                        'sync_action' => 'SA_CREATE',
                        'sync_status' => 'UNSYNCED'
                    ]);
                }
            } else {
                write_log('Cannot get Jurnal Entry ID');
            }

        } else {

            // Insert the update sync action
            $insert_new_order = $wpdb->insert($table_name, [
                'wc_order_id' => $order_id,
                'jurnal_entry_id' => $get_results->jurnal_entry_id,
                'sync_action' => 'JE_UPDATE',
                'sync_status' => 'UNSYNCED'
            ]);

        }
    }
}

/**
 * Fungsi ini dipanggil setelah order status berubah menjadi Processing
 * Referensi http://hookr.io/actions/woocommerce_order_status_processing
 *
*/
add_action( 'woocommerce_order_status_processing', 'wji_update_order_processing', 10, 1 );
function wji_update_order_processing( $order_id ) {
    global $wpdb;

    // Check for unsync orders haven't been updated
    $table_name = $wpdb->prefix . 'wji_order_sync_log';
    $where = 'WHERE wc_order_id='.$order_id.' AND sync_action="JE_UPDATE" AND sync_status="SYNCED"';
    $updated_orders = $wpdb->get_results("SELECT * FROM {$table_name} {$where} ORDER BY id");
    $count_updated_orders = $wpdb->get_var("SELECT COUNT(id) FROM {$table_name} {$where}");
    
    // If data exists
    if($count_updated_orders > 0) {
        // Jurnal Entry sudah pernah di sync update sebelumnya
        write_log('Jurnal entry sudah sync dan updated Order ID: '.$order_id);
    } else {

        // Check apakah ada data create jurnal entry nya
        $where = 'WHERE wc_order_id='.$order_id.' AND sync_action="JE_CREATE" AND sync_status="SYNCED"';
        $tobesync_orders = $wpdb->get_results("SELECT * FROM {$table_name} {$where} ORDER BY id");
        $count = $wpdb->get_var("SELECT COUNT(id) FROM {$table_name} {$where}");
        
        // Datanya ada, buat record baru untuk sync yang updated nya 
        if($count > 0) {
            foreach ($tobesync_orders as $tobesync_order) {

                // 1. Create journal entry
                // If all ok, set action to je_update and status to unsycnhed
                $wpdb->insert($table_name, [
                    'wc_order_id' => $tobesync_order->wc_order_id,
                    'jurnal_entry_id' => $tobesync_order->jurnal_entry_id,
                    'sync_action' => 'JE_UPDATE',
                    'sync_status' => 'UNSYNCED'
                ]);

                // 2. Create stock adjustments if enabled
                $options = get_option('wji_plugin_general_options');
                if($options['sync_stock']) {
                    $wpdb->insert($table_name, [
                        'wc_order_id' => $tobesync_order->wc_order_id,
                        'sync_action' => 'SA_CREATE',
                        'sync_status' => 'UNSYNCED'
                    ]);
                }
            }
        }
    }
}

/* ------------------------------------------------------------------------ *
 * WP Cron functions
 * ------------------------------------------------------------------------ */

/**
 * Add custom plugin cron job interval
 */
function wji_cron_add_minute_interval( $schedules ) {
    // Adds once every minute to the existing schedules.
    $schedules['wji_everyminute'] = array(
        'interval' => 60,
        'display' => __( 'WooCommerce & Jurnal.ID Sync Every Minute' )
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'wji_cron_add_minute_interval' );

/**
 * Sync order job
 */
add_action ('wji_cronjob_event', 'wji_sync_order_job');
function wji_sync_order_job() {
    global $wpdb;
    $api = new WJI_IntegrationAPI();

    // Ambil data dr db table
    $table_name = $wpdb->prefix . 'wji_order_sync_log';
    $where = 'WHERE sync_status = "UNSYNCED"';
    $limit = 5; // Max numbers per order to process in one time
    $tobesync_orders = $wpdb->get_results("SELECT * FROM {$table_name} {$where} ORDER BY id ASC LIMIT {$limit}");
    $count = $wpdb->get_var("SELECT COUNT(id) FROM {$table_name} {$where}");

    // Proses data yang belum sync
    if($count > 0) {

        // Get account options
        $get_options = get_option('wji_account_mapping_options');
        $acc_receivable = $api->getJurnalAccountName( $get_options['acc_receivable'] );
        $acc_tax = $api->getJurnalAccountName( $get_options['acc_tax'] );
        $acc_sales = $api->getJurnalAccountName( $get_options['acc_sales'] );
        $acc_stock_adjustments = $api->getJurnalAccountName( $get_options['acc_stock_adjustments'] );

        foreach ($tobesync_orders as $tobesync_order) {

            // Ambil data order nya
            $order = wc_get_order($tobesync_order->wc_order_id);
            $order_id = $order->get_id();
            $order_created_date = $order->get_date_created()->format( 'Y-m-d' );
            $order_billing_first_name = strtoupper($order->get_billing_first_name());
            $order_billing_last_name = strtoupper($order->get_billing_last_name());
            $order_billing_full_name = $order_billing_first_name.'-'.$order_billing_last_name;
            $order_trans_no = $order_id . '-' . $order_billing_full_name;
            $order_total = $order->get_total();

            // Calculate tax
            $general_options = get_option('wji_plugin_general_options');
            if( !$general_options['include_tax'] ) {
                $order_tax = $order->total_tax;
                $order_total_after_tax = $order_total;
            } else {
                $order_tax = $order_total * 0.1;
                $order_total_after_tax = $order_total - $order_tax;
            }

            // Check ini action nya ngapain
            $sync_action = $tobesync_order->sync_action;

            if($sync_action == 'JE_CREATE') {

                // Sample format data untuk order dengan status pembayaran BELUM lunas, refer ke sample di akun Jurnal.ID
                $data = array(
                    "journal_entry" => array(
                        "transaction_date" => $order_created_date,
                        "transaction_no" => $order_trans_no,
                        "memo" => 'WooCommerce Order ID: '.$order_id,
                        "transaction_account_lines_attributes" => [
                            [
                                "account_name" => $acc_receivable,
                                "debit" => $order_total,
                            ],
                            [
                                "account_name" => $acc_tax,
                                "credit" => $order_tax,
                            ],
                            [
                                "account_name" => $acc_sales,
                                "credit" => $order_total_after_tax,
                            ]
                        ],
                        "tags" => [
                            'WooCommerce',
                            $order_billing_full_name
                        ]
                    )
                );

                // Make the API call
                $postJournalEntry = $api->postJournalEntry($data);

                // Update order sync status in db
                if( isset($postJournalEntry->journal_entry) ) {
                    $where = [ 'id' => $tobesync_order->id ];
                    $jurnal_entry_id = $postJournalEntry->journal_entry->id;
                    $wpdb->update($table_name, [
                            'jurnal_entry_id' => $jurnal_entry_id,
                            'sync_data' => json_encode($data),
                            'sync_status' => 'SYNCED',
                            'sync_at' => date("Y-m-d H:i:s")
                        ],
                        $where
                    );
                } else {
                    $where = [ 'id' => $tobesync_order->id ];
                    @$message = $postJournalEntry->error_full_messages;
                    $wpdb->update($table_name, [
                            'sync_status' => 'ERROR',
                            'sync_data' => json_encode($data),
                            'sync_note' => json_encode($message)
                        ],
                        $where
                    );
                }
            } elseif ($sync_action == 'JE_UPDATE') {

                // Get variables
                $jurnal_entry_id = $tobesync_order->jurnal_entry_id;
                $order_payment = 'acc_payment_'.$order->get_payment_method();
                $acc_payment = $api->getJurnalAccountName( $get_options[$order_payment] );

                // Sample format data untuk order dengan status pembayaran SUDAH lunas, refer ke sample di akun Jurnal.ID
                $data = array(
                    "journal_entry" => array(
                        "transaction_date" => $order_created_date,
                        "transaction_no" => $order_trans_no,
                        "memo" => 'WooCommerce Order ID: '.$order_id,
                        "transaction_account_lines_attributes" => [
                            [
                                "account_name" => $acc_payment,
                                "debit" => $order_total,
                            ],
                            [
                                "account_name" => $acc_tax,
                                "credit" => $order_tax,
                            ],
                            [
                                "account_name" => $acc_sales,
                                "credit" => $order_total_after_tax,
                            ]
                        ],
                        "tags" => [
                            'WooCommerce',
                            $order_billing_full_name
                        ]
                    )
                );

                // Make the API call
                $patchJournalEntry = $api->patchJournalEntry($jurnal_entry_id,$data);
                
                // Update order sync status in db
                if( isset($patchJournalEntry->journal_entry) ) {
                    $where = [ 'id' => $tobesync_order->id ];
                    $wpdb->update($table_name, [
                            'sync_data' => json_encode($data),
                            'sync_status' => 'SYNCED',
                            'sync_at' => date("Y-m-d H:i:s")
                        ],
                        $where
                    );
                } else {
                    $where = [ 'id' => $tobesync_order->id ];
                    $wpdb->update($table_name, [
                            'sync_status' => 'ERROR',
                            'sync_data' => json_encode($data),
                            'sync_note' => $patchJournalEntry->errors
                        ],
                        $where
                    );
                }
            } elseif ($sync_action == 'SA_CREATE') {

                $get_options = get_option('wji_plugin_general_options');
                $field_name = 'wh_id';
                $warehouse_id = $get_options[$field_name];

                if( empty($warehouse_id) ) {
                    // Return error immediately
                    $where = [ 'id' => $tobesync_order->id ];
                    $table_name = $wpdb->prefix . 'wji_order_sync_log';
                    $wpdb->update($table_name, [
                            'sync_status' => 'ERROR',
                            'sync_note' => 'Pengaturan Warehouse belum di set.'
                        ],
                        $where
                    );
                    continue; // End current pending order, continue with next iteration
                }

                // Sample format data untuk order dengan status pembayaran BELUM lunas, refer ke sample di akun Jurnal.ID
                $data = array(
                    "stock_adjustment" => array(
                        "stock_adjustment_type" => 'general',
                        "warehouse_id" => $warehouse_id,
                        "account_name" => $acc_stock_adjustments,
                        "date" => date("Y-m-d"),
                        "memo" => 'WooCommerce Order ID#'.$order_id,
                        "maintain_actual" => false
                    )
                );
                    
                foreach ( $order->get_items() as $key => $item ) {
                    
                    // Get an instance of the WC_Product object (can be a product variation too)
                    $product = $item->get_product();
                    $product_id = $product->get_id();

                    // Get item mapping
                    $table_name = $wpdb->prefix . 'wji_product_mapping';
                    $where = 'WHERE wc_item_id='.$product_id.' AND jurnal_item_id IS NOT NULL';
                    $product = $wpdb->get_row("SELECT * FROM {$table_name} {$where}");

                    if( !empty($product) ) {
                        $data['stock_adjustment']['lines_attributes'][] = array(
                            "product_name" => $api->getJurnalProductName($product->jurnal_item_id),
                            "difference" => -$item->get_quantity(),
                            "use_custom_average_price" => false
                        );
                    } else {
                        // Return error immediately
                        $where = [ 'id' => $tobesync_order->id ];
                        $table_name = $wpdb->prefix . 'wji_order_sync_log';
                        $wpdb->update($table_name, [
                                'sync_data' => json_encode($data),
                                'sync_status' => 'ERROR',
                                'sync_note' => 'Data Product Mapping tidak ditemukan untuk Product ID: '.$product_id
                            ],
                            $where
                        );
                        continue 2; // Continue with next unsynced order
                    }
                }

                // Kalau ada data product nya
                if( count( $data['stock_adjustment']['lines_attributes'] ) > 0 ) {

                    // Make the API call
                    $postStockAdjustments = $api->postStockAdjustments($data);

                    // Update order sync status in db
                    if( isset($postStockAdjustments->stock_adjustment) ) {
                        $where = [ 'id' => $tobesync_order->id ];
                        $stock_adj_id = $postStockAdjustments->stock_adjustment->id;
                        $table_name = $wpdb->prefix . 'wji_order_sync_log';
                        $wpdb->update($table_name, [
                                'stock_adj_id' => $stock_adj_id,
                                'sync_data' => json_encode($data),
                                'sync_status' => 'SYNCED',
                                'sync_at' => date("Y-m-d H:i:s")
                            ],
                            $where
                        );
                    } else {
                        $where = [ 'id' => $tobesync_order->id ];
                        $table_name = $wpdb->prefix . 'wji_order_sync_log';
                        if(@$postStockAdjustments->errors) {
                            $message = $postStockAdjustments->errors;
                        }
                        if(@$postStockAdjustments->error_full_messages) {
                            $message = json_encode($postStockAdjustments->error_full_messages);
                        }
                        $wpdb->update($table_name, [
                                'sync_data' => json_encode($data),
                                'sync_status' => 'ERROR',
                                'sync_note' => $message
                            ],
                            $where
                        );
                    } // end if postStockAdjustments
                } else {

                    $where = [ 'id' => $tobesync_order->id ];
                    $table_name = $wpdb->prefix . 'wji_order_sync_log';
                    $wpdb->update($table_name, [
                            'sync_status' => 'ERROR',
                            'sync_note' => 'Data produk tidak ada'
                        ],
                        $where
                    );
                }
            } // end if SA_CREATE
        } // end if foreach tobesync_orders
    } // end if count
}

/* ------------------------------------------------------------------------ *
 * WP AJAX Calls
 * ------------------------------------------------------------------------ */

add_action( 'wp_ajax_wji_translasi_item', ['WJI_AjaxCallback', 'wji_item_ajax_callback'] ); // ajax cari item di jurnal
add_action( 'wp_ajax_wji_translasi_item_save', ['WJI_AjaxCallback', 'wji_save_item_ajax_callback'] ); // ajax save mapping item
add_action( 'wp_ajax_wji_check_used_item', ['WJI_AjaxCallback', 'wji_check_used_item_ajax_callback'] ); // ajax cek mapping jika sudah digunakan

/* ------------------------------------------------------------------------ *
 * Enqueue Plugin Scripts
 * ------------------------------------------------------------------------ */

add_action( 'admin_enqueue_scripts', 'wji_enqueue_scripts' );
function wji_enqueue_scripts(){
    // CSS
    wp_enqueue_style('wji_select2_style', plugin_dir_url( __FILE__ ) . 'css/select2.min.css', null, '4.0.3' );
    wp_enqueue_style('wji_main_style', plugin_dir_url( __FILE__ ) . 'css/wji_main.css', null, '1.1.5'); 
    
    // JS
    wp_enqueue_script('wji_select2', plugin_dir_url( __FILE__ ) . 'js/select2.min.js', array('jquery'), '4.0.3' );
    wp_enqueue_script('wji_main', plugin_dir_url( __FILE__ ) . 'js/wji_main.js', array( 'jquery', 'select2' ), '1.1.53'); 
}

/* ------------------------------------------------------------------------ *
 * DEBUG only, write to file debug.log on wp-content dir
 * ------------------------------------------------------------------------ */

if (!function_exists('write_log')) {
    function write_log ( $log )  {
        if ( true === WP_DEBUG ) {
            $pluginlog = plugin_dir_path(__FILE__).'debug.log';
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ).PHP_EOL, 3, $pluginlog );
            } else {
                error_log( $log.PHP_EOL, 3, $pluginlog );
            }
        }
    }
}
