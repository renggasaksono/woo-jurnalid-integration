<?php

namespace Saksono\Woojurnal;

defined( 'ABSPATH' ) || exit;

class Helper {

	static function sanitize_int($value) {
		if(intval($value)) {
			return sanitize_text_field($value);
		}
		else {
			return '';
			return new WP_Error( 'bc_value_invalid', __( 'An invalid value was passed. Integer expected.', 'wooxbeecloud' ), array( 'status' => 400 ) );
		}
	}

	static function sanitize($value) {
		return sanitize_text_field($value);
	}

	static function sanitize_email($value) {
		return sanitize_email($value);
	}

	static function sanitize_array($arrays) {
		if(is_array($arrays)) {
			$result = [];
			foreach($arrays as $key => $value) {
				$result[sanitize_text_field($key)] = sanitize_text_field($value);
			}
			return $result;
		}
		else {
			return '';
			return new WP_Error( 'bc_value_invalid', __( 'An invalid value was passed. Array expected.', 'wooxbeecloud' ), array( 'status' => 400 ) );
		}
	}

}
?>
