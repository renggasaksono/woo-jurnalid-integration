<?php

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
if( ! class_exists( 'WJI_IntegrationAPI' ) ) {
    require_once( 'WJI_IntegrationAPI.php' );
}

class WJI_TableList extends WP_List_Table {
	
	private $datas;
	private $columns;
	private $perPage = 10;
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
		return $item->id;
	}

	public function column_wcproductname($item) {
		$pf = new WC_Product_Factory;
		$p = $pf->get_product($item->wc_item_id);
		$sku = $p->get_sku();
		if( ( $pf = wp_get_post_parent_id( $p->get_id() ) ) !== 0) {
			return '<i>(Variation)</i> ' . ($sku ? esc_html($sku).' - ' : '').esc_html($p->get_name());
		} else {
			return ($sku ? esc_html($sku).' - ' : '').esc_html($p->get_name());	
		}
	}

	public function column_jurnal_item_code($item) {
		$html = '';

		$html .= "<button type='button' class='bc-editable-link'>".esc_html($item->jurnal_item_code ?: '(belum diset)')."</button>";
		$html .= '<span class="bc-editable-success hidden" style="color:green">&ensp;Tersimpan!</span>';
		$html .= '<div class="bc-editable-input hidden">';
		$html .= '<a class="bc-editable-cancel" href="#"><span class="dashicons dashicons-no-alt"></span></a>';
		$html .= '<select name="wcbc_select2_item" class="bc-editable-select2" style="width:50%;max-width:20em;">';
		$html .= '<option></option>';
				$api = new WJI_IntegrationAPI;
				if($jurnal_products = $api->getJournalProducts()) {
			 		foreach ($jurnal_products as $product) {
			 				$html .= '<option value="' . esc_html($product['id']) . '">' . esc_html($product['text']) . '</option>';
			 		}
			 	}
		$html .= '</select>';
		$html .= '<input type="hidden" class="bc-editable-wc_item_id" value="'.esc_html($item->wc_item_id).'">';
		$html .= '<a class="button bc-editable-submit" href="#" > Simpan </a>';
		$html .= '</div>';
	 
		echo $html;
	}

	public function column_wc_order_id($item) {
		return '<a href="'.get_edit_post_link($item->wc_order_id).'">'.esc_html($item->wc_order_id).'</a>';
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
				$status = 'Create journal entry';
				break;
			case 'JE_UPDATE':
				$status = 'Update journal entry';
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
			case 'UNSYNCED':
				$status = 'Dalam antrian';
				$label = 'primary';
				break;
			case 'SYNCED':
				$status = 'Berhasil tersinkron';
				$label = 'success';
				break;
			case 'ERROR':
			default:
				$status = 'Gagal tersinkron';
				$label = 'danger';
		}
		return '<span class="bc-label '.$label.'">'.$status.'</span>';
	}

	public function column_sync_note($item) {
		
		if(!$item->sync_note) {
			$message = '';
			switch($item->sync_action) {
				case 'JE_CREATE':
					if($item->sync_status == 'SYNCED') {
						$je_id = $item->jurnal_entry_id;
						$link = '<a href="https://my.jurnal.id/journal_entries/'.$je_id.'" target="_blank">'.$je_id.'</a>';
						$message = 'Order on hold. Jurnal entry berhasil dibuat ID '.$link;
						break;
					}
				case 'JE_UPDATE':
					if($item->sync_status == 'SYNCED') {
						$je_id = $item->jurnal_entry_id;
						$link = '<a href="https://my.jurnal.id/journal_entries/'.$je_id.'" target="_blank">'.$je_id.'</a>';
						$message = 'Order processing. Jurnal entry berhasil di update ID '.$link;
						break;
					}
				case 'JE_DELETE':
					if($item->sync_status == 'SYNCED') {
						$message = 'Order cancelled. Journal entry berhasil dihapus';
						break;
					}
				case 'SA_CREATE':
					if($item->sync_status == 'SYNCED') {
						$sa_id = $item->stock_adj_id;
						$link = '<a href="https://my.jurnal.id/stock_adjustments/'.$sa_id.'" target="_blank">'.$sa_id.'</a>';
						$message = 'Order processing. Stock adjustment berhasil dibuat ID '.$link;
						break;
					}
				case 'SA_DELETE':
					if($item->sync_status == 'SYNCED') {
						$message = 'Order cancelled. Stock adjustment berhasil dihapus';
						break;
					}
				default:
					$status = 'Gagal tersinkron';
					$link = 'danger';
			}
			return $message;
		} else {
			return $item->sync_note;
		}
	}

	public function column_sync_at($item) {
		if($item->sync_at != '0000-00-00 00:00:00') {
			return esc_html($item->sync_at);
		}
		return '';
	}
}
?>
