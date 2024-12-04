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
		$pf = new \WC_Product_Factory;
		$p = $pf->get_product($item->wc_item_id);
		$sku = $p->get_sku();
		if( ( $pf = wp_get_post_parent_id( $p->get_id() ) ) !== 0) {
			return '<i>(Variation)</i> ' . ($sku ? esc_html($sku).' - ' : '').esc_html($p->get_name());
		}
		
		return ($sku ? esc_html($sku).' - ' : '').esc_html($p->get_name());	
	}

	public function column_jurnal_item_code($item) {
		$html = '';

		$html .= "<button type='button' class='bc-editable-link'>".esc_html($item->jurnal_item_code ?: '(belum diset)')."</button>";
		$html .= '<span class="bc-editable-success hidden" style="color:green">&ensp;Tersimpan!</span>';
		$html .= '<div class="bc-editable-input hidden">';
		$html .= '<a class="bc-editable-cancel" href="#"><span class="dashicons dashicons-no-alt"></span></a>';
		$html .= '<select name="wcbc_select2_item" class="bc-editable-select2" style="width:50%;max-width:20em;">';
		$html .= '<option></option>';
		$html .= '</select>';
		$html .= '<input type="hidden" class="bc-editable-wc_item_id" value="'.esc_html($item->wc_item_id).'">';
		$html .= '<a class="button bc-editable-submit" href="#" > Simpan </a>';
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
				$status = 'Create journal entry';
				break;
			case 'JE_DELETE':
				$status = 'Delete journal entry';
				break;
			case 'SA_CREATE':
				$status = 'Create stock adjustment';
				break;
			case 'SA_DELETE':
				$status = 'Delete stock adjustment';
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
		return '<span class="bc-label '.$label.'">'.$status.'</span>';
	}

	public function column_sync_note($item) {
		
		if(!$item->sync_note) {
			$message = '';
			switch($item->sync_action) {
				case 'JE_CREATE':
				case 'JE_PAID':
				case 'JE_UNPAID':
					if($item->sync_status == 'SYNCED') {
						$je_id = $item->jurnal_entry_id;
						$link = '<a href="https://my.jurnal.id/journal_entries/'.$je_id.'" target="_blank" title="View on Jurnal.ID">'.$je_id.'</a>';
						$message = 'Journal entry succesfully created '.$link;
						break;
					}
				case 'JE_DELETE':
					if($item->sync_status == 'SYNCED') {
						$message = 'Journal entry succesfully deleted';
						break;
					}
				case 'SA_CREATE':
					if($item->sync_status == 'SYNCED') {
						$sa_id = $item->stock_adj_id;
						$link = '<a href="https://my.jurnal.id/stock_adjustments/'.$sa_id.'" target="_blank" title="View on Jurnal.ID">'.$sa_id.'</a>';
						$message = 'Stock adjustment succesfully created '.$link;
						break;
					}
				case 'SA_DELETE':
					if($item->sync_status == 'SYNCED') {
						$message = 'Stock adjustment succesfully deleted';
						break;
					}
				default:
					$status = 'Gagal tersinkron';
					$link = 'danger';
			}
			return $message;
		}
		
		return $item->sync_note;
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
	public function extra_tablenav($which){

		// Search function reference: https://gist.github.com/wturnerharris/7413971
		// Bulk action reference: https://wordpress.stackexchange.com/questions/364447/passing-search-query-and-custom-filter-to-wp-list-table-grid

		// Only show in sync history tab
		if( isset($_GET['tab']) && sanitize_text_field($_GET['tab']) !== 'order_options' ) {
			return;
		}

		$filter_status = isset($_GET['sync_status']) ? sanitize_text_field( $_GET['sync_status'] ) : '';
	
		// Display on the top of table
		if( $which == "top" ) {?>
			<div class="alignleft actions bulkactions">
				<select name="sync_status" id="sync_status">
					<option value="">All status</option>
					<option value="SYNCED" 	<?php echo $filter_status == 'SYNCED'  ? ' selected' : '' ?>>Success</option>
					<option value="PENDING" <?php echo $filter_status == 'PENDING' ? ' selected' : '' ?>>Pending</option>
					<option value="ERROR" 	<?php echo $filter_status == 'ERROR'   ? ' selected' : '' ?>>Failed</option>
				</select>
			</div>
			<?php
		}
	}
}