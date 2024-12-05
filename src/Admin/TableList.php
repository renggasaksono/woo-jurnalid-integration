<?php

namespace Saksono\Woojurnal\Admin;

defined( 'ABSPATH' ) || exit;

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class TableList extends \WP_List_Table {
	
	private $datas;
	private $columns;
	private $perPage = 20;
	private $totalItem;
	private $topTableNav;

	public function setDatas(Array $data) {
		$this->datas = $data;
	}

	public function setColumns(Array $col) {
		$this->columns = $col;
	}

	public function setPerpage($page) {
		$this->perPage = $page;
	}

	public function setTotalItem($total) {
		$this->totalItem = $total;
	}

	public function setTopTableNav($topTableNav) {
		$this->topTableNav = $topTableNav;
	}

	public function getPerpage() {
		return $this->perPage;
	}

	public function get_columns() {
		return $this->columns;
	}

	public function generate() {
		$columns = $this->columns;
		$hidden = [];
		$sortable = [];
		$this->_column_headers = array($columns, $hidden, $sortable);

		$this->set_pagination_args([
		    'total_items' => $this->totalItem,
		    'per_page'    => $this->perPage
		]);

		$this->items = $this->datas;
		$this->display();
	}

	public function column_default( $item, $column_name ) {
		return esc_html($item->$column_name);
	}

	public function column_id($item) {
		
		$user = wp_get_current_user();

		// Create link to run sync manually
		if ( in_array( 'administrator', (array) $user->roles ) ) {

			// Only display links for admin for other than success status
			$retry_statuses = [ 'ERROR', 'PENDING' ];
			
			if( in_array( $item->sync_status, (array) $retry_statuses ) ) {
				
				$paged = isset( $_GET[ 'paged' ] ) ? sanitize_text_field($_GET[ 'paged' ]) : '0';

				// Generate safe links
				$url = sprintf('options-general.php?page=wji_settings&tab=order_options&_syncid=%s&paged=%d',
					$item->id,
					$paged
				);
				
				$nonce_url = add_query_arg( '_wjinonce', wp_create_nonce( 'retry_sync' ), $url );

				return '<a href="'.$nonce_url.'" title="Click to retry sync">'.$item->id.'</a>';
			
			} else {
				return $item->id;
			}
		}
		
		return $item->id;
	}

	public function column_wcproductname($item) {
		
		$product = wc_get_product($item->wc_item_id);
		if (!$product) {
			return '<i>Product not found</i>';
		}
		
		$name = esc_html($product->get_name());
		$sku = $product->get_sku() ? esc_html($product->get_sku()) . ' - ' : '';
		$is_variation = $product->is_type('variation');
		
		$output = $is_variation ? '<i>(Variation)</i> ' : '';
		$output .= $sku . $name;
		
		return $output;
	}

	public function column_jurnal_item_code($item) {
		$html = '';

		$html .= "<button type='button' class='bc-editable-link'>";
		$html .= esc_html($item->jurnal_item_code ?: '(Not Set)');
		$html .= "</button>";
	
		$html .= '<span class="bc-editable-success hidden" style="color:green">&ensp;Saved!</span>';
		$html .= '<div class="bc-editable-input hidden" aria-hidden="true">';
		$html .= '<a class="bc-editable-cancel" href="#"><span class="dashicons dashicons-no-alt"></span></a>';
	
		$html .= '<select name="wcbc_select2_item" class="bc-editable-select2" style="width:100%;max-width:20em;">';
		$html .= '<option value=""></option>';
		$html .= '</select>';
	
		$html .= '<input type="hidden" class="bc-editable-wc_item_id" value="' . esc_attr($item->wc_item_id) . '">';
	
		$html .= '<a class="button bc-editable-submit" href="#">Simpan</a>';
		$html .= '</div>';

		echo $html;
	}

	public function column_wc_order_id($item) {
		return '#'.esc_html($item->wc_order_id);
	}

	public function column_jurnal_entry_id($item) {
		if($item->jurnal_entry_id) {
			return esc_html($item->jurnal_entry_id);
		}
		return '';
	}

	public function column_sync_data($item) {
		return json_encode($item->sync_data);
	}

	public function column_sync_action($item) {
		switch($item->sync_action) {
			case 'JE_CREATE':
			case 'JE_PAID':
			case 'JE_UNPAID':
				$status = __('Create journal entry', 'wji-plugin');
				break;
			case 'JE_DELETE':
				$status = __('Delete journal entry', 'wji-plugin');
				break;
			case 'SA_CREATE':
				$status = __('Create stock adjustment', 'wji-plugin');
				break;
			case 'SA_DELETE':
				$status = __('Delete stock adjustment', 'wji-plugin');
				break;
			default:
				$status = '';
		}
		return $status;
	}

	public function column_sync_status($item) {
		$status = '';
		$label = '';
		switch($item->sync_status) {
			case 'PENDING':
				$status = 'Pending';
				$label = 'primary';
				break;
			case 'SYNCED':
				$status = 'Success';
				$label = 'success';
				break;
			case 'ERROR':
			default:
				$status = 'Failed';
				$label = 'danger';
		}
		return '<span class="bc-label '.$label.'">'.esc_html($status, 'wji-plugin').'</span>';
	}

	public function column_sync_note($item) {
		// If sync_note is already set, return it.
		if ($item->sync_note) {
			return esc_html($item->sync_note);
		}

		$message = '';

		// Define base URL for Jurnal.ID links
		$base_url = 'https://my.jurnal.id/';

		// Generate message based on sync_action and sync_status
		switch ($item->sync_action) {
			case 'JE_CREATE':
			case 'JE_PAID':
			case 'JE_UNPAID':
				if ($item->sync_status == 'SYNCED') {
					$je_id = esc_html($item->jurnal_entry_id);
					$link = '<a href="' . esc_url($base_url . 'journal_entries/' . $je_id) . '" target="_blank" title="' . esc_attr__('View on Jurnal.ID', 'wji-plugin') . '">' . $je_id . '</a>';
					$message = sprintf(
						/* translators: %s: link to the journal entry */
						__('Journal entry successfully created %s', 'wji-plugin'),
						$link
					);
				}
				break;
	
			case 'JE_DELETE':
				if ($item->sync_status == 'SYNCED') {
					$message = __('Journal entry successfully deleted', 'wji-plugin');
				}
				break;
	
			case 'SA_CREATE':
				if ($item->sync_status == 'SYNCED') {
					$sa_id = esc_html($item->stock_adj_id);
					$link = '<a href="' . esc_url($base_url . 'stock_adjustments/' . $sa_id) . '" target="_blank" title="' . esc_attr__('View on Jurnal.ID', 'wji-plugin') . '">' . $sa_id . '</a>';
					$message = sprintf(
						/* translators: %s: link to the stock adjustment */
						__('Stock adjustment successfully created %s', 'wji-plugin'),
						$link
					);
				}
				break;
	
			case 'SA_DELETE':
				if ($item->sync_status == 'SYNCED') {
					$message = __('Stock adjustment successfully deleted', 'wji-plugin');
				}
				break;
	
			default:
				$message = __('An unknown error occurred. Please retry sync.', 'wji-plugin');
		}
	
		return $message;
	}

	public function column_sync_at($item) {
		if($item->sync_at != '0000-00-00 00:00:00') {
			return esc_html($item->sync_at);
		}
		return '';
	}

	/**
	 * Add extra markup in the toolbars before or after the list
	 * @param string $which, helps you decide if you add the markup after (bottom) or before (top) the list
	 */
	public function extra_tablenav($which) {
		// Only show in sync history tab
		if (isset($_GET['tab']) && sanitize_text_field($_GET['tab']) !== 'order_options') {
			return;
		}
	
		// Get the current filter status
		$filter_status = isset($_GET['sync_status']) ? sanitize_text_field($_GET['sync_status']) : '';
	
		if ($which === "top") {
			?>
			<div class="alignleft actions bulkactions">
				<label for="sync_status" class="screen-reader-text"><?php esc_html_e('Filter by sync status', 'wji-plugin'); ?></label>
				<select name="sync_status" id="sync_status">
					<option value=""><?php esc_html_e('All status', 'wji-plugin'); ?></option>
					<option value="SYNCED" <?php selected($filter_status, 'SYNCED'); ?>><?php esc_html_e('Success', 'wji-plugin'); ?></option>
					<option value="PENDING" <?php selected($filter_status, 'PENDING'); ?>><?php esc_html_e('Pending', 'wji-plugin'); ?></option>
					<option value="ERROR" <?php selected($filter_status, 'ERROR'); ?>><?php esc_html_e('Failed', 'wji-plugin'); ?></option>
				</select>
			</div>
			<?php
		}
	}
}