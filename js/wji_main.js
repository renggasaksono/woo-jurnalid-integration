jQuery(function($){

	// Show product mapping option on click
	$('.bc-editable-link').click(function() {
		jQuery(this).addClass('hidden').siblings('.bc-editable-input').removeClass('hidden');
	});

	// Hide product mapping option on click
	$('.bc-editable-cancel').click(function() {
		jQuery(this).parent('.bc-editable-input').addClass('hidden').siblings('.bc-editable-link').removeClass('hidden');
	});

	// Select2 product mapping
	$('.bc-editable-select2').select2({
		ajax: {
			url: ajaxurl, // AJAX URL is predefined in WordPress admin
			dataType: 'json',
			data: function (params) {
  				return {
    				q: params.term, // search query,
					page: params.page || 1,
    				action: 'wji_select2_products' // AJAX action for admin-ajax.php
  				};
			},
			cache: true
		},
		placeholder: 'Cari produk',
      	allowClear: true,
		minimumInputLength: 2
	});

	// Save product mapping on submit
	$('.bc-editable-submit').click(function() {
		let thisBtn = $(this);
		let wc_item_id = $(this).siblings('.bc-editable-wc_item_id').val();
		let jurnal_item_id = $(this).siblings('.bc-editable-select2').find(':selected').val();
		let jurnal_item_code = $(this).siblings('.bc-editable-select2').find(':selected').text();

		if(jurnal_item_id) {

			$.post(ajaxurl, 
				{
    				action: 'wji_check_used_item',
    				jurnal_item_id: jurnal_item_id, 
  				},
  				function(resp) {
  					let conf = true;
  					let data = JSON.parse(resp);
  					if(data.status) {
  						let error = "Item ini sudah pernah di translasikan:\n";
  						for (index = 0; index < (data.data).length; ++index) {
  							error += "- "+data.data[index]+"\n";
						}
						error += "\nAnda yakin akan tetap melanjutkan?";
  						conf = confirm(error);
  					}

  					if(conf) {
						$.post(ajaxurl, 
							{
			    				action: 'wji_translasi_item_save',
			    				wc_item_id: wc_item_id, 
			    				jurnal_item_id: jurnal_item_id, 
			    				jurnal_item_code: jurnal_item_code, 
			  				},
			  				function(resp) {
			  					if(resp) {
			  						thisBtn.parents('.bc-editable-input').siblings('.bc-editable-link').text(resp);
			  						thisBtn.siblings('.bc-editable-cancel').trigger('click');
			  						thisBtn.parent().siblings('.bc-editable-success').fadeIn(100).fadeOut(1250);
			  					}
			  					else {
			  						alert('Error!');
			  					}
			  				}
						);
  					}
  				}
			);
		}
		else {
			alert('Item Jurnal.ID harus diisi');
		}
	});

	// Account mapping
	$('.wj-accounts-select2').select2({
		placeholder: 'Pilih Akun Jurnal.ID',
      	allowClear: true
	});

	// Warehouse mapping
	$('.wj-warehouses-select2').select2({
		placeholder: 'Pilih Gudang Jurnal.ID',
      	allowClear: true,
      	width : '200px'
	});

	// Submit form on sync_status filter changed
	$('#sync_status').on( "change", function() {
		var filter = $(this).val();
		document.location.href = 'admin.php?page=wji_settings&tab=order_options&sync_status='+filter;
	});

});