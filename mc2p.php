<?php
/**
 * Plugin Name: Gravity Forms MyChoice2Pay Add-on
 * Plugin URI: https://github.com/mc2p/mc2p-gravity-forms-add-on
 * Description: Gravity Forms library for the MyChoice2Pay API.
 * Author: MyChoice2Pay
 * Author URI: https://www.mychoice2pay.com/
 * Version: 0.1.0
 * Text Domain: gravityformsmc2p
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2018 MyChoice2Pay SL. (hola@mychoice2pay.com) and Gravity Forms
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   GF-MC2P-Add-on
 * @author    MyChoice2Pay
 * @category  Admin
 * @copyright Copyright (c) 2018 MyChoice2Pay SL. (hola@mychoice2pay.com) and Gravity Forms
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

define( 'GF_MC2P_VERSION', '0.1.0' );

add_action( 'gform_loaded', array( 'GF_Gateway_MC2P_Bootstrap', 'load' ), 5 );

class GF_Gateway_MC2P_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
			return;
		}

		require_once( 'class_gf_mc2p.php' );

		GFAddOn::register( 'GF_Gateway_MC2P' );
	}
}

function gf_mc2p() {
	return GF_Gateway_MC2P::get_instance();
}
