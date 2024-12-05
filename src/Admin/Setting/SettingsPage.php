<?php

namespace Saksono\Woojurnal\Admin\Setting;

defined( 'ABSPATH' ) || exit;

use Saksono\Woojurnal\Api\Profile as ProfileApi;
use Saksono\Woojurnal\Api\Warehouse as WarehouseApi;

class SettingsPage {

    public function __construct()
    {
        add_filter('plugin_action_links', [$this, 'plugin_settings_link'], 10, 2);
        add_action('admin_menu', [$this, 'create_settings_menu']);
        add_action('admin_init', [$this, 'register_settings']);
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
            'WooCommerce Jurnal.ID Sync',
            'WooCommerce Jurnal.ID Sync',
            'manage_woocommerce',
            'wji_settings',
            [$this, 'settings_display']
        );
    }

    /**
     * Renders a page to display for the plugin settings
     */
    public function settings_display()
    {
        ?>
        <div class="wrap">
            <h2>Jurnal.ID Sync for WooCommerce</h2>
            
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
                    submit_button('Save Changes');
                    echo '</form>';
                } elseif( $active_tab == 'account_options' ) {
                    echo '<form method="post" action="options.php">';
                    settings_fields( 'wji_account_mapping_options' );
                    do_settings_sections( 'wji_plugin_account_options' );
                    submit_button('Save Changes');
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
    public function register_settings()
    {
        if( false == get_option( 'wji_plugin_general_options' ) ) {  
            add_option( 'wji_plugin_general_options', array() );
        }

        $this->register_general_settings();
        $this->register_tax_settings();
        $this->register_stock_settings();

        register_setting(
            'wji_plugin_general_options',
            'wji_plugin_general_options',
            [$this, 'validate_general_options']
        );
    }

    private function register_general_settings()
    {
        add_settings_section(
            'general_settings_section',
            __('API Setting', 'wji-plugin'),
            [$this, 'general_section_callback'],
            'wji_plugin_general_options'
        );

        add_settings_field(
            'client_id',
            __('Client ID', 'wji-plugin'),
            [$this, 'client_id_callback'],
            'wji_plugin_general_options',
            'general_settings_section'
        );

        add_settings_field(
            'client_secret',
            __('Client Secret', 'wji-plugin'),
            [$this, 'client_secret_callback'],
            'wji_plugin_general_options',
            'general_settings_section'
        );
    }

    private function register_tax_settings()
    {
        add_settings_section(
            'tax_settings_section',
            __('Tax Setting', 'wji-plugin'),
            [$this, 'tax_section_callback'],
            'wji_plugin_general_options'
        );

        add_settings_field(
            'include_tax',
            __('Tax Syncronization', 'wji-plugin'),
            [$this, 'include_tax_callback'],
            'wji_plugin_general_options',
            'tax_settings_section'
        );
    }

    private function register_stock_settings()
    {
        add_settings_section(
            'stock_settings_section',
            __('Stock Setting', 'wji-plugin'),
            [$this, 'stock_section_callback'],
            'wji_plugin_general_options'
        );

        add_settings_field(
            'sync_stock',
            __('Stock Synchronization', 'wji-plugin'),
            [$this, 'sync_stock_callback'],
            'wji_plugin_general_options',
            'stock_settings_section'
        );

        add_settings_field(
            'wh_id',
            __('Warehouse', 'wji-plugin'),
            [$this, 'wh_id_callback'],
            'wji_plugin_general_options',
            'stock_settings_section'
        );
    }

    public function general_section_callback()
    {
        if ( get_option( 'wji_plugin_api_valid', false ) && $profile_name = get_option( 'wji_plugin_profile_full_name', false ) ) {
            printf(
                /* translators: %s is the profile name */
                '<p>' . esc_html__( 'Successfully connected to Jurnal.ID. Welcome, %s &#128075;', 'wji-plugin' ) . '</p>',
                '<b>' . esc_html( $profile_name ) . '</b>'
            );
        } else {
            echo '<p>';
            echo wp_kses(
                sprintf(
                    /* translators: %s is the URL to Mekari Developer */
                    __( 'Enter the credentials from the application registered at <a href="%s" title="Mekari Developer" target="_blank">Mekari Developer</a>', 'wji-plugin' ),
                    esc_url( 'https://developers.mekari.com/dashboard/applications' )
                ),
                [ 'a' => [ 'href' => [], 'title' => [], 'target' => [] ] ]
            );
            echo '</p>';
        }
    }

    public function tax_section_callback()
    {
        echo '<p>' . esc_html__('Configure tax synchronization settings.', 'wji-plugin') . '</p>';
    }

    public function stock_section_callback()
    {
        $this->set_warehouses_cache();
        echo '<p>' . esc_html__('Configure stock synchronization and warehouse settings.', 'wji-plugin') . '</p>';
    }

    public function client_id_callback()
    {
        $options = get_option('wji_plugin_general_options', []);
        $this->render_input_field('text', 'client_id', $options['client_id'] ?? '');
    }

    public function client_secret_callback()
    {
        $options = get_option('wji_plugin_general_options', []);
        $this->render_input_field('password', 'client_secret', $options['client_secret'] ?? '');
    }
    
    public function include_tax_callback($args)
    {
        $options = get_option('wji_plugin_general_options', []);
        $this->render_checkbox_field('include_tax', $options['include_tax'] ?? '', __('Enable tax synchronization', 'wji-plugin'));
    }
    
    public function sync_stock_callback($args)
    {
        $options = get_option('wji_plugin_general_options', []);
        $this->render_checkbox_field('sync_stock', $options['sync_stock'] ?? '', __('Enable stock synchronization', 'wji-plugin'));
    }

    public function wh_id_callback($args)
    {
        $options = get_option('wji_plugin_general_options', []);
        $selected_value = $options['wh_id'] ?? '';
        $warehouses = get_transient('wji_cached_journal_warehouses') ?: [];
        
        $this->render_dropdown_field(
            'wh_id',
            $selected_value,
            $warehouses,
            __('Select a warehouse', 'wji-plugin')
        );
    }

    public function validate_general_options($input)
    {
        $validated = [];

        // Validate and sanitize Client ID
        if (!empty($input['client_id'])) {
            $validated['client_id'] = sanitize_text_field($input['client_id']);
        }

        // Validate and sanitize Client Secret
        if (!empty($input['client_secret'])) {
            $validated['client_secret'] = sanitize_text_field($input['client_secret']);
        }

        // Validate include_tax checkbox
        $validated['include_tax'] = !empty($input['include_tax']) ? 1 : 0;

        // Validate sync_stock checkbox
        $validated['sync_stock'] = !empty($input['sync_stock']) ? 1 : 0;

        // Validate Warehouse ID dropdown
        if (!empty($input['wh_id'])) {
            $validated['wh_id'] = sanitize_text_field($input['wh_id']);
        }

        return $validated;
    }

    public function on_option_update($old_value, $new_value)
    {
        delete_transient( 'wji_cached_journal_products' );
        delete_transient( 'wji_cached_journal_account' );
        delete_transient( 'wji_cached_journal_warehouses' );

        update_option( 'wji_plugin_api_valid', false );
        update_option( 'wji_plugin_profile_full_name', false );

        $api = new ProfileApi();
        $validApi = $api->getProfile();
    }

    private function render_input_field($type, $field_name, $value, $attributes = [])
    {
        $attr_string = '';
        foreach ($attributes as $key => $val) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($val));
        }

        printf(
            '<input type="%s" id="wji_%s" name="wji_plugin_general_options[%s]" value="%s"%s />',
            esc_attr($type),
            esc_attr($field_name),
            esc_attr($field_name),
            esc_attr($value),
            $attr_string
        );
    }

    private function render_checkbox_field($field_name, $value, $label)
    {
        printf(
            '<label for="wji_%s"><input type="checkbox" id="wji_%s" name="wji_plugin_general_options[%s]" value="1" %s /> %s</label>',
            esc_attr($field_name),
            esc_attr($field_name),
            esc_attr($field_name),
            checked(1, $value, false),
            esc_html($label)
        );
    }

    private function render_dropdown_field($field_name, $selected_value, $options, $default_label = '')
    {
        $html = sprintf(
            '<select id="wji_%s" name="wji_plugin_general_options[%s]">',
            esc_attr($field_name),
            esc_attr($field_name)
        );

        $html .= sprintf('<option value="">%s</option>', esc_html($default_label));

        foreach ($options as $option) {
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option['id']),
                selected($selected_value, $option['id'], false),
                esc_html($option['text'])
            );
        }

        $html .= '</select>';
        echo $html;
    }

    private function set_warehouses_cache() {
        if( false === ( get_transient( 'wji_cached_journal_warehouses' ) ) ) {
            $warehouse_api = new WarehouseApi();
            $warehouses = $warehouse_api->getAll();
            if( $warehouses && count($warehouses)>0 ) {
                set_transient( 'wji_cached_journal_warehouses', $warehouses, 7 * DAY_IN_SECONDS );
            }
        }
    }

}