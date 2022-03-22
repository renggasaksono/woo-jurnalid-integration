<?php

class WJI_Helper {

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

	static function calcDiscExp($price, $discexp) {
		$amt = floatval($price);
	    $retval = 0;
	    $tmp;
	    $token;
	    $tokens = [];

	    $tokens = explode('+', $discexp);
	    $countTokens = count($tokens);

	    if ($countTokens > 0) {
	        for ($index = 0; $index < $countTokens; $index++) {
	            $token = $tokens[$index];
	            if (strpos($token, '%') > 0) {
	                $tmp = ($amt * floatval(substr($token, 0, strlen($token)-1))) / 100;
	            } else {
	                $tmp = $token;
	            }

                $amt = floatval($amt) + (floatval($tmp * -1));

	            $retval = floatval($retval) + floatval($tmp);
	        }
	    }
	    return $retval;
	}

	static function getVariationIdFromAttributes($product, $attributes) {
		$wc_product_data_store = new \WC_Product_Data_Store_CPT();

        return $wc_product_data_store->find_matching_product_variation($product, $attributes);
	}

}
?>
