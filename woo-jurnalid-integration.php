<?php
/**
 * Plugin Name:       WooCommerce Jurnal.ID Integration
 * Description:       Integrasi data pemesanan dan stok produk dari WooCommerce ke Jurnal.ID.
 * Version:           4.0.0
 * Requires at least: 5.5
 * Author:            Rengga Saksono
 * Author URI:        https://id.linkedin.com/in/renggasaksono
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

}

/**
 * Run on plugin deactivation
 */
register_deactivation_hook( __FILE__, 'wji_deactivate');
function wji_deactivate() {
    global $wpdb;

    // Clear cached data when settings changes
    delete_transient( 'wji_cached_journal_products' );
    delete_transient( 'wji_cached_journal_account' );
    delete_transient( 'wji_cached_journal_warehouses' );

    // Debug purpose, delete plugin options
    // delete_option('wji_plugin_general_options');
    // delete_option('wji_account_mapping_options');
}

/* ------------------------------------------------------------------------ *
 * Display Plugin Settings
 * ------------------------------------------------------------------------ */

/**
 * Add custom plugin menu and settings page
 */
function wji_create_settings_menu() {
    $plugin_page = add_options_page(
        'WooCommerce Jurnal.ID Integration', //Page Title
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

        <?php $active_tab = isset( $_GET[ 'tab' ] ) ? sanitize_text_field($_GET[ 'tab' ]) : 'general_options'; ?>
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
        'Jurnal.ID API Key',                // The label to the left of the option interface element
        'wji_api_key_callback',             // The name of the function responsible for rendering the option interface
        'wji_plugin_general_options',       // The page on which this option will be displayed
        'general_settings_section'          // The name of the section to which this field belongs
    );

    add_settings_field( 
        'include_tax',                      // ID used to identify the field throughout the theme
        'Sinkronisasi Pajak',               // The label to the left of the option interface element
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
        'Warehouse',                        // The label to the left of the option interface element
        'wji_wh_id_callback',               // The name of the function responsible for rendering the option interface
        'wji_plugin_general_options',       // The page on which this option will be displayed
        'stock_settings_section'            // The name of the section to which this field belongs
    );

    // Register the fields with WordPress 
    register_setting(
        'wji_plugin_general_options',           // A settings group name
        'wji_plugin_general_options',           // The name of an option to sanitize and save
        'wji_plugin_validate_general_options'   // Validate callback
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
            'account_mapping_section',              // ID used to identify this section and with which to register options
            'Payment Account',                     // Title to be displayed on the administration page
            'wji_account_mapping_callback',         // Callback used to render the description of the section
            'wji_plugin_account_options'            // Page on which to add this section of options
        );

        foreach ($gateways as $gateway) {
            $id = $gateway->id;
            $title = $gateway->title;

            // Create the settings
            add_settings_field( 
                'acc_payment_'.$id,                         // ID used to identify the field throughout the theme
                $title,                                     // The label to the left of the option interface element
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

    $products = $wpdb->get_results("select wjpm.* from {$table_name} wjpm join {$table_post} p on wjpm.wc_item_id=p.id {$where} order by id limit {$tablelist->getPerpage()} offset {$offset}");
    $count = $wpdb->get_var("select count(*) from {$table_name} wjpm join {$table_post} p on wjpm.wc_item_id=p.id {$where}");

    $tablelist->setTotalItem($count);
    $tablelist->setDatas($products);
    $tablelist->setColumns([
        'wcproductname' => 'Produk pada Woocommerce (SKU - Nama Produk)',
        'jurnal_item_code' => 'Produk pada Jurnal.ID (SKU - Nama Produk)'
    ]);
    
    $tablelist->generate();
}

function wji_order_sync_callback() {
    global $wpdb;

    $tablelist = new WJI_TableList();
    $api = new WJI_IntegrationAPI();

    // Retry sync function
    if ( isset($_GET['_wjinonce']) || wp_verify_nonce( isset($_GET['_wjinonce']), 'retry_sync' ) || is_numeric( isset($_GET[ '_syncid' ]) ) ) {
        
        write_log('Retry sync #'.$_GET['_syncid']);
        $sync_id = sanitize_key($_GET['_syncid']);

        // Get sync data
        $sync_data = $wpdb->get_row(
            $wpdb->prepare( "SELECT * from {$api->getSyncTableName()} where id = %d",
                $sync_id
            )
        );

        if( $sync_data->sync_status != 'SYNCED' ) {

            // Get sync action
            $sync_action = $sync_data->sync_action;
            $order_id = $sync_data->wc_order_id;

            // Run sync process
            switch ( $sync_action ) {
                case "JE_CREATE":
                case "JE_UPDATE":
                case 'JE_PAID':
                case 'JE_UNPAID':
                    wji_sync_journal_entry( (int) $sync_id, (int) $order_id );
                    break;
                case "SA_CREATE":
                    wji_sync_stock_adjustment( (int) $sync_id, (int) $order_id );
                    break;
                case "JE_DELETE":
                    wji_desync_journal_entry( (int) $sync_id, (int) $order_id );
                    break;
                case "SA_DELETE":
                    wji_desync_stock_adjustment( (int) $sync_id, (int) $order_id );
                    break;
            }

            // Remove retry sync url args
            function remove_retry_sync_query_args( $args ) {
                $args[] = '_wjinonce';
                $args[] = '_syncid';
                return $args;
            }
            add_filter( 'removable_query_args', 'remove_retry_sync_query_args', 10, 1 );
        }
    }

    // Render table
    $data = [];
    $offset = $tablelist->getPerpage() * ($tablelist->get_pagenum() - 1);
    $where = ( isset($_GET['sync_status']) && $_GET['sync_status'] !== '' ) ? 'where sync_status="'.sanitize_text_field($_GET['sync_status']).'"' : '';

    $products = $wpdb->get_results("select * from {$api->getSyncTableName()} {$where} order by created_at desc limit {$tablelist->getPerpage()} offset {$offset}");
    $count = $wpdb->get_var("select count(id) from {$api->getSyncTableName()} {$where}");
    $tablelist->setTotalItem($count);
    $tablelist->setDatas($products);
    $tablelist->setColumns([
        'id'                => '#',
        'wc_order_id'       => 'Order',
        'sync_action'       => 'Task',
        'sync_status'       => 'Status',
        'sync_note'         => 'Message',
        'sync_at'           => 'Date'
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

function wji_include_tax_callback($args) {
    $get_options = get_option('wji_plugin_general_options');
    $field_name = 'include_tax';
    if ( !array_key_exists($field_name,$get_options) ) {
        $get_options[$field_name] = '';
    }
    $html = '<input type="checkbox" name="wji_plugin_general_options['.$field_name.']" value="1"' . checked( 1, $get_options['include_tax'], false ) . '/>';
    $html .= '<label for="include_tax">Aktifkan sinkronisasi akun pajak</label>';
    echo $html;
}

function wji_sync_stock_callback($args) {
    $get_options = get_option('wji_plugin_general_options');
    $field_name = 'sync_stock';
    if ( !array_key_exists($field_name,$get_options) ) {
        $get_options[$field_name] = '';
    }
    $html = '<input type="checkbox" name="wji_plugin_general_options['.$field_name.']" value="1"' . checked( 1, $get_options[$field_name], false ) . '/>';
    $html .= '<label for="sync_stock">Aktifkan sinkronisasi stok produk</label>';
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

/**
 * Fungsi ini dipanggil setelah checkout berhasil via web
 * Full process see sync flow screenshot
*/
add_action( 'woocommerce_thankyou', 'wji_run_sync_process', 10, 1 );

/**
 * Fungsi ini dipanggil ketika status order dirubah menjadi processing
 * Full process see sync flow screenshot
*/
add_action( 'woocommerce_order_status_processing', 'wji_run_sync_process', 10, 1 );

/**
 * Fungsi untuk menjalankan proses sync
 *
*/
function wji_run_sync_process( $order_id ) {
    global $wpdb;
    
    $api = new WJI_IntegrationAPI();
    $options = get_option('wji_plugin_general_options');

    // Check if order is paid
    $order = wc_get_order( $order_id );

    if( $order->is_paid() ) {
        
        // Validate apakah sudah pernah sync
        $order_sync_where = 'WHERE wc_order_id='.$order_id.' AND sync_status="SYNCED" AND sync_action="JE_PAID"';
        $get_order_sync = $wpdb->get_row("SELECT * FROM {$api->getSyncTableName()} {$order_sync_where}");
        
        if( ! $get_order_sync ) {

            // Create new sync action
            $wpdb->insert( $api->getSyncTableName(), [
                'wc_order_id' => $order_id,
                'sync_action' => 'JE_PAID',
                'sync_status' => 'PENDING'
            ]);

            // Run sync process
            wji_sync_journal_entry( $wpdb->insert_id, $order_id );
        }

    } else {
        
        // Validate apakah sudah pernah sync
        $order_sync_where = 'WHERE wc_order_id='.$order_id.' AND sync_status="SYNCED" AND sync_action="JE_UNPAID"';
        $get_order_sync = $wpdb->get_row("SELECT * FROM {$api->getSyncTableName()} {$order_sync_where}");
        
        if( ! $get_order_sync ) {

            // Create new sync action
            $wpdb->insert( $api->getSyncTableName(), [
                'wc_order_id' => $order_id,
                'sync_action' => 'JE_UNPAID',
                'sync_status' => 'PENDING'
            ]);

            // Run sync process
            wji_sync_journal_entry( $wpdb->insert_id, $order_id );
        }
    }  

    // 2. Sync stock adjustment if enabled
    if( isset($options['sync_stock']) ) {

        // Validate apakah sudah pernah sync
        $stock_sync_where = 'WHERE wc_order_id='.$order_id.' AND sync_status="SYNCED" AND sync_action="SA_CREATE"';
        $get_stock_sync = $wpdb->get_row("SELECT * FROM {$api->getSyncTableName()} {$stock_sync_where}");
           
        if( ! $get_stock_sync ) {
            
            // Add new sync record if haven't synced yet
            $wpdb->insert( $api->getSyncTableName(), [
                'wc_order_id' => $order_id,
                'sync_action' => 'SA_CREATE',
                'sync_status' => 'PENDING'
            ]);

            // Run sync process
            wji_sync_stock_adjustment( $wpdb->insert_id, $order_id );
        }
    }
}

/**
 * Fungsi ini dipanggil setelah order status berubah menjadi Cancelled
 *
*/
add_action( 'woocommerce_order_status_cancelled', 'wji_update_order_cancelled', 10, 1 );
function wji_update_order_cancelled( $order_id ) {
    global $wpdb;
    write_log('Update order cancelled Order #'.$order_id);

    $api = new WJI_IntegrationAPI();
    
    // Check if order meta exists
    if( $journal_entry_id = get_post_meta( $order_id, $api->getMetaKey(), true )  ) {

        // 1. Add delete journal entry sync record
        $wpdb->insert( $api->getSyncTableName(), [
            'wc_order_id' => $order_id,
            'jurnal_entry_id' => $journal_entry_id,
            'sync_action' => 'JE_DELETE',
            'sync_status' => 'PENDING'
        ]);

        $desync_journal_entry_id = $wpdb->insert_id;

        // Run desync process
        $run_desync_journal_entry = wji_desync_journal_entry( $desync_journal_entry_id, $order_id );

        // 2. Check apakah ada data stock adjustment
        if( $stock_adjustment_id = get_post_meta( $order_id, $api->getStockMetaKey(), true )  ) {

            // Add delete stock adjusment sync record
            $wpdb->insert( $api->getSyncTableName(), [
                'wc_order_id' => $order_id,
                'stock_adj_id' => $stock_adjustment_id,
                'sync_action' => 'SA_DELETE',
                'sync_status' => 'PENDING'
            ]);

            $desync_stock_adjustment_id = $wpdb->insert_id;

            // Run desync process
            $run_desync_stock_adjustment = wji_desync_stock_adjustment( $desync_stock_adjustment_id, $order_id );
        }
    }      
}

/**
 * Fungsi ini dipanggil ketika ada product yang di modify
 *
*/
add_action( 'save_post_product', 'wji_check_new_product', 10, 3 );
function wji_check_new_product( $post_id, $post, $update ){
    global $wpdb;

    // Get a WC_Product object https://woocommerce.github.io/code-reference/classes/WC-Product.html
    $product = wc_get_product( $post_id );
    if( !$product ) {
        return;
    }

    // Add new product mapping record
    $table_name = $wpdb->prefix . 'wji_product_mapping';
    $id = $product->get_id();
    $data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$id}");
    if(count($data) < 1) {
        $wpdb->insert($table_name, ['wc_item_id' => $id, 'jurnal_item_id' => null]);
    }

    // Check for product variations
    if ( $product->is_type( "variable" ) ) {
        $childrens = $product->get_children();
        if(count($childrens) > 0) {
            foreach ( $childrens as $child_id ) {
                $variation = wc_get_product( $child_id ); 
                if ( ! $variation || ! $variation->exists() ) {
                    continue;
                }
                $data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$child_id}");
                if(count($data) < 1) {
                    $wpdb->insert($table_name, ['wc_item_id' => $child_id, 'jurnal_item_id' => null]);
                }
            }
        }
    }
}

/**
 * Fungsi ini dipanggil ketika ada product yang di hapus
 *
*/
add_action( 'before_delete_post', 'wji_delete_product', 10, 1 );
function wji_delete_product( $post_id ) {
    global $wpdb;

    if ( get_post_type( $post_id ) != 'product' ) {
        return;
    }

    // Delete product
    $table_name = $wpdb->prefix . 'wji_product_mapping';
    $product = wc_get_product($post_id);
    $id = $product->get_id();
    $data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$id}");
    if(count($data) < 1) {
        $wpdb->delete($table_name, ['wc_item_id' => $id]);
    }

    // Check for product variations
    if ( $product->is_type( "variable" ) ) {
        $childrens = $product->get_children();
        if(count($childrens) > 0) {
            foreach ( $childrens as $child_id ) {
                $variation = wc_get_product( $child_id ); 
                if ( ! $variation || ! $variation->exists() ) {
                    continue;
                }
                $data = $wpdb->get_results("SELECT id FROM $table_name WHERE wc_item_id = {$child_id}");
                if(count($data) < 1) {
                    $wpdb->delete($table_name, ['wc_item_id' => $child_id]);
                }
            }
        }
    }
}

/* ------------------------------------------------------------------------ *
 * SYNC functions
 * ------------------------------------------------------------------------ */

/**
 * Do sync function
 * @param sync_id INT - ID from wji_order_sync_log table
 * @param order_id INT - woocommerce order ID
 * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
 */
function wji_sync_journal_entry( int $sync_id, int $order_id ) {
    global $wpdb;
    write_log('Sync jurnal entry #'.$sync_id);
    
    $api = new WJI_IntegrationAPI();
    $order = wc_get_order( $order_id );
    $data = [];
    $sync_data = [];
    $do_sync = false;

    // Get sync data
    $get_sync_data = $wpdb->get_row(
        $wpdb->prepare( "SELECT * from {$api->getSyncTableName()} where id = %d",
            $sync_id
        )
    );

    // Create data format for sync
    if( $get_sync_data->sync_action == 'JE_PAID' ) {

        // Validate if previous UNPAID journal entry has been created, we need to format the data to reverse and balance the previous record
        if( ! get_post_meta( $order->get_id(), $api->getUnpaidMetaKey(), true )  ) {
            $data = $api->get_paid_sync_data( $order );
        } else {
            $data = $api->get_payment_sync_data( $order );
        }

    } elseif( $get_sync_data->sync_action == 'JE_UNPAID' ) {
        $data = $api->get_unpaid_sync_data( $order );
    }

    // Verify data
    if( empty($data) ) {
        return false;
    }

    // Run sync function
    $do_sync = $api->postJournalEntry( $data );

    if( isset( $do_sync->journal_entry ) ) {
        
        // Success
        $sync_data['jurnal_entry_id']   = $do_sync->journal_entry->id;
        $sync_data['sync_data']         = json_encode( $data );
        $sync_data['sync_status']       = 'SYNCED';
        $sync_data['sync_note']         = '';
        $sync_data['sync_at']           = date("Y-m-d H:i:s");

        // Update post order metadata
        update_post_meta( $order->get_id(), $api->getMetaKey(), $do_sync->journal_entry->id );
        update_post_meta( $order->get_id(), $api->getUnpaidMetaKey(), $do_sync->journal_entry->id );

    } else {
        
        // Failed
        $sync_data['sync_status']       = 'ERROR';
        $sync_data['sync_data']         = json_encode( $data );
        $sync_data['sync_note']         = $api->getErrorMessages( $do_sync );
    }

    // Update sync log
    return $wpdb->update( $api->getSyncTableName(), $sync_data, [ 'id' => $sync_id ]);
}

/**
 * Delete sync function
 * @param sync_id INT - ID from wji_order_sync_log table
 * @param order_id INT - WC order ID
 * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
 */
function wji_desync_journal_entry( int $sync_id, int $order_id ) {
    global $wpdb;
    write_log('Desync journal entry #'.$sync_id);
    
    $api = new WJI_IntegrationAPI();
    
    // Delete journal entry if exists
    $journal_entry_id = get_post_meta( $order_id, $api->getMetaKey(), true );
    
    if( $journal_entry_id ) {

        // Make the API call
        $deleteEntryResponse = $api->deleteJournalEntry( $journal_entry_id );
    
        // Update sync status in db
        if( ! isset($deleteEntryResponse->errors) ) {

            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_status'   => 'SYNCED',
                    'sync_note'     => '',
                    'sync_action'   => 'JE_DELETE',
                    'sync_at'       => date("Y-m-d H:i:s")
                ],
                [ 'id' => $sync_id ]
            );
        } else {

            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_status'   => 'ERROR',
                    'sync_action'   => 'JE_DELETE',
                    'sync_note'     => $api->getErrorMessages( $deleteEntryResponse )
                ],
                [ 'id' => $sync_id ]
            );
        }
    }
}

/**
 * Create stock adjustment sync function
 * @param sync_id INT - ID from wji_order_sync_log table
 * @param order_id INT - WC order ID
 * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
 */
function wji_sync_stock_adjustment( int $sync_id, int $order_id ) {
    global $wpdb;
    write_log('Sync stock adjustment #'.$sync_id);
    
    $api = new WJI_IntegrationAPI();
    $order = wc_get_order( $order_id );
    $get_general_options = get_option('wji_plugin_general_options');
    $get_account_options = get_option('wji_account_mapping_options');
    $get_warehouse = $get_general_options['wh_id'];
    $sync_note = 'Product mapping tidak tersedia: ';

    // Verify warehouse
    if( empty($get_warehouse) ) {
        // Return error immediately
        return $wpdb->update($api->getSyncTableName(), [
                'sync_status' => 'ERROR',
                'sync_note' => 'Pengaturan Warehouse belum di set'
            ],
            [ 'id' => $sync_id ]
        );
    }

    // Verify account mapping
    if( ! isset($get_account_options['acc_stock_adjustments']) ) {
        return false;
    }
    
    $data = array(
        "stock_adjustment"  => array(
            "stock_adjustment_type" => 'general',
            "warehouse_id"          => $get_warehouse,
            "account_name"          => $api->getJurnalAccountName( $get_account_options['acc_stock_adjustments'] ),
            "date"                  => date("Y-m-d"),
            "memo"                  => 'WooCommerce Order ID#'.$order_id,
            "maintain_actual"       => false,
            "lines_attributes"      => []
        )
    );

    // Loop order items
    foreach ( $order->get_items() as $order_item_id => $wc_item ) {

        // Get product_id
        $product_id = $wc_item->get_product_id();
        
        // Get item mapping
        $product_mapping_table = $wpdb->prefix . 'wji_product_mapping';
        $product_mapping_where = 'WHERE wc_item_id='.$product_id.' AND jurnal_item_id IS NOT NULL';
        $product_mapping = $wpdb->get_row("SELECT * FROM {$product_mapping_table} {$product_mapping_where}");

        // Check mapping
        if( ! $product_mapping ) {
            $sync_note .= $wc_item->get_name().", ";
            continue;
        }

        // Check product name
        $product_name = $api->getJurnalProductName($product_mapping->jurnal_item_id);

        // If data valid
        if ( $product_mapping && $product_name ) {

            $lines_attributes = array_column( $data['stock_adjustment']['lines_attributes'], 'product_name' );
            $found_key = array_search( $product_name, $lines_attributes );

            // Check for duplicates
            if ( $found_key !== false ) {

                // Merge values
                $current_diff = $data['stock_adjustment']['lines_attributes'][$found_key]['difference'];
                $new_diff = $current_diff - $wc_item->get_quantity();

                // Set data
                $data['stock_adjustment']['lines_attributes'][$found_key]['difference'] = $new_diff;
 
            } else {

                // Set data
                $data['stock_adjustment']['lines_attributes'][] = array(
                    "product_name" => $product_name,
                    "difference" => - $wc_item->get_quantity(),
                    "use_custom_average_price" => false
                );
            }
        }
    }

    // write_log($data);

    // Kalau ada data product nya
    if( ! empty($data['stock_adjustment']['lines_attributes']) ) {

        // Make the API call
        $postStockAdjustments = $api->postStockAdjustments($data);

        // Update order sync status in db
        if( isset($postStockAdjustments->stock_adjustment) ) {
            
            // Update post order metadata
            update_post_meta( $order_id, $api->getStockMetaKey(), $postStockAdjustments->stock_adjustment->id );

            return $wpdb->update( $api->getSyncTableName(), [
                    'stock_adj_id'  => $postStockAdjustments->stock_adjustment->id,
                    'sync_data'     => json_encode( $data ),
                    'sync_status'   => 'SYNCED',
                    'sync_note'     => '',
                    'sync_at'       => date("Y-m-d H:i:s")
                ],
                [ 'id' => $sync_id ]
            );

        } else {

            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_data'     => json_encode( $data ),
                    'sync_status'   => 'ERROR',
                    'sync_note'     => $api->getErrorMessages( $postStockAdjustments )
                ],
                [ 'id' => $sync_id ]
            );
        } // end if postStockAdjustments

    } else {

        return $wpdb->update( $api->getSyncTableName(), [
                'sync_status'   => 'ERROR',
                'sync_note'     => rtrim( trim($sync_note), ','),
            ],
            [ 'id' => $sync_id ]
        );
    }
    
}

/**
 * Delete stock adjustment sync function
 * @param sync_id INT - ID from wji_order_sync_log table
 * @param order_id INT - WC order ID
 * @return $wpdb->update response https://developer.wordpress.org/reference/classes/wpdb/update/
 */
function wji_desync_stock_adjustment( int $sync_id, int $order_id ) {
    global $wpdb;
    write_log('Desync stock adjustment #'.$sync_id);

    $api = new WJI_IntegrationAPI();

    // Delete journal entry if exists
    $stock_adjustment_id = get_post_meta( $order_id, $api->getStockMetaKey(), true );
    
    if( $stock_adjustment_id ) {

        // Make the API call
        $deleteEntryResponse = $api->deleteStockAdjustments( $stock_adjustment_id );
        
        // Update sync status in db
        if( ! isset( $deleteEntryResponse->errors ) ) {
            
            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_status'   => 'SYNCED',
                    'sync_action'   => 'SA_DELETE',
                    'sync_note'     => '',
                    'sync_at'       => date("Y-m-d H:i:s")
                ],
                [ 'id' => $sync_id ]
            );

        } else {

            return $wpdb->update( $api->getSyncTableName(), [
                    'sync_status'   => 'ERROR',
                    'sync_note'     => $api->getErrorMessages( $deleteEntryResponse )
                ],
                [ 'id' => $sync_id ]
            );

        }
    }
    
}

/* ------------------------------------------------------------------------ *
 * WP AJAX Calls
 * ------------------------------------------------------------------------ */

add_action( 'wp_ajax_wji_translasi_item', ['WJI_AjaxCallback', 'wji_item_ajax_callback'] ); // ajax cari item di jurnal
add_action( 'wp_ajax_wji_translasi_item_save', ['WJI_AjaxCallback', 'wji_save_item_ajax_callback'] ); // ajax save mapping item
add_action( 'wp_ajax_wji_check_used_item', ['WJI_AjaxCallback', 'wji_check_used_item_ajax_callback'] ); // ajax cek mapping jika sudah digunakan
add_action( 'wp_ajax_wji_select2_products', ['WJI_AjaxCallback', 'wji_get_jurnal_products_callback'] ); // ajax get select2 products resource

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
            // $pluginlog = plugin_dir_path(__FILE__).'debug.log';
            if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ).PHP_EOL );
            } else {
                error_log( $log.PHP_EOL );
            }
        }
    }
}
