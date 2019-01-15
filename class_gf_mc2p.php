<?php
/**
 *
 * @package   GF-MC2P-Add-on
 * @author    MyChoice2Pay
 * @category  Admin
 * @copyright Copyright (c) 2018 MyChoice2Pay SL. (hola@mychoice2pay.com) and Gravity Forms
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

add_action('wp', array('GF_Gateway_MC2P', 'maybe_thankyou_page'), 5);

GFForms::include_payment_addon_framework();

/**
 * MC2P Payment Gateway
 *
 * Provides an MC2P Payment Gateway.
 *
 * @class 		GF_Gateway_MC2P
 * @extends		WC_Payment_Gateway
 * @version		0.1.1
 * @author 		MyChoice2Pay
 */

$autoloader_param = __DIR__ . '/lib/MC2P/MC2PClient.php';
// Load up the MC2P library
try {
    require_once $autoloader_param;
} catch (\Exception $e) {
    throw new \Exception('The MC2P payment plugin was not installed correctly or the files are corrupt. Please reinstall the plugin. If this message persists after a reinstall, contact hola@mychoice2pay.com with this message.');
}

class GF_Gateway_MC2P extends GFPaymentAddOn {

    protected $_version                  = GF_MC2P_VERSION;
    protected $_min_gravityforms_version = '1.9.3';
    protected $_slug                     = 'gravityformsmc2p';
    protected $_path                     = 'gravityformsmc2p/mc2p.php';
    protected $_full_path                = __FILE__;
    protected $_url                      = 'https://www.mychoice2pay.com';
    protected $_title                    = 'MyChoice2Pay Gravity Forms Add-On';
    protected $_short_title              = 'MyChoice2Pay';
    protected $_supports_callbacks       = true;
    protected $_requires_credit_card     = false;

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_mc2p', 'gravityforms_mc2p_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_mc2p';
	protected $_capabilities_form_settings = 'gravityforms_mc2p';
	protected $_capabilities_uninstall = 'gravityforms_mc2p_uninstall';

	// Automatic upgrade enabled
	protected $_enable_rg_autoupgrade = true;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GF_Gateway_MC2P();
		}

		return self::$_instance;
	}

	private function __clone() {
	} /* do nothing */

	public function init_frontend() {
		parent::init_frontend();

		add_filter( 'gform_disable_post_creation', array( $this, 'delay_post' ), 10, 3 );
		add_filter( 'gform_disable_notification', array( $this, 'delay_notification' ), 10, 4 );
	}

	//----- SETTINGS PAGES ----------//

	public function plugin_settings_fields() {
		$description = '
			<p style="text-align: left;">' .
			esc_html__( 'Allows to receive payments from several payment gateways while offering the possibility of dividing payments between several people.', 'gravityformsmc2p' ) .
			'<br/>';

        return array(
            array(
                'description' => $description,
                'fields'      => array(
                    array(
                        'name'  => 'gf_mc2p_key',
                        'label' => esc_html__('Key', 'gravityformsmc2p'),
                        'type'  => 'text',
                        'class' => 'medium'
                    ),
                    array(
                        'name'  => 'gf_mc2p_secret_key',
                        'label' => esc_html__('Secret Key', 'gravityformsmc2p'),
                        'type'  => 'text',
                        'class' => 'medium',
                    ),
                    array(
                        'name'          => 'gf_mc2p_way',
                        'label'         => esc_html__('Way of integration', 'gravityformsmc2p'),
                        'type'          => 'radio',
                        'default_value' => 'redirect',
                        'choices'       => array(
                            array(
                                'label' => esc_html__('Redirect', 'gravityformsmc2p'),
                                'value' => 'redirect',
                            ),
                            array(
                                'label' => esc_html__('iFrame', 'gravityformsmc2p'),
                                'value' => 'iframe',
                            ),
                        ),
                        'horizontal'    => true,
                    ),
                ),
            ),
        );
	}

	public function feed_list_no_item_message() {
		$settings = $this->get_plugin_settings();
		if ( ! rgar( $settings, 'gf_mc2p_configured' ) ) {
			return sprintf( esc_html__( 'To get started, please configure your %sMC2P Settings%s!', 'gravityformsmc2p' ), '<a href="' . admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) . '">', '</a>' );
		} else {
			return parent::feed_list_no_item_message();
		}
	}

	public function feed_settings_fields() {
		$default_settings = parent::feed_settings_fields();

		// Cancel URL
		$fields = array(
			array(
				'name'     => 'cancelUrl',
				'label'    => esc_html__( 'Cancel URL', 'gravityformsmc2p' ),
				'type'     => 'text',
				'class'    => 'medium',
				'required' => false,
				'tooltip'  => '<h6>' . esc_html__( 'Cancel URL', 'gravityformsmc2p' ) . '</h6>' . esc_html__( 'Enter the URL the user should be sent to should they cancel before completing their MyChoice2Pay payment.', 'gravityformsmc2p' )
			),
		);

		if ( $this->get_setting( 'delayNotification' ) || ! $this->is_gravityforms_supported( '1.9.12' ) ) {
			$fields[] = array(
				'name'    => 'notifications',
				'label'   => esc_html__( 'Notifications', 'gravityformsmc2p' ),
				'type'    => 'notifications',
				'tooltip' => '<h6>' . esc_html__( 'Notifications', 'gravityformsmc2p' ) . '</h6>' . esc_html__( "Enable this option if you would like to only send out this form's notifications for the 'Form is submitted' event after payment has been received. Leaving this option disabled will send these notifications immediately after the form is submitted. Notifications which are configured for other events will not be affected by this option.", 'gravityformsmc2p' )
			);
		}

		// Add post fields if form has a post
		$form = $this->get_current_form();
		if ( GFCommon::has_post_field( $form['fields'] ) ) {
			$post_settings = array(
				'name'    => 'post_checkboxes',
				'label'   => esc_html__( 'Posts', 'gravityformsmc2p' ),
				'type'    => 'checkbox',
				'tooltip' => '<h6>' . esc_html__( 'Posts', 'gravityformsmc2p' ) . '</h6>' . esc_html__( 'Enable this option if you would like to only create the post after payment has been received.', 'gravityformsmc2p' ),
				'choices' => array(
					array( 'label' => esc_html__( 'Create post only when payment is received.', 'gravityformsmc2p' ), 'name' => 'delayPost' ),
				),
			);

			$fields[] = $post_settings;
		}

		// Adding custom settings for backwards compatibility with hook 'gform_mc2p_add_option_group'
		$fields[] = array(
			'name'  => 'custom_options',
			'label' => '',
			'type'  => 'custom',
		);

		$default_settings = $this->add_field_after( 'billingInformation', $fields, $default_settings );
		//-----------------------------------------------------------------------------------------

		/**
		 * Filter through the feed settings fields for the MC2P feed
		 *
		 * @param array $default_settings The Default feed settings
		 * @param array $form The Form object to filter through
		 */
		return apply_filters( 'gform_mc2p_feed_settings_fields', $default_settings, $form );
	}

	public function field_map_title() {
		return esc_html__( 'MC2P Field', 'gravityformsmc2p' );
	}

	public function settings_options( $field, $echo = true ) {
		$html = $this->settings_checkbox( $field, false );

		//--------------------------------------------------------
		//For backwards compatibility.
		ob_start();
		do_action( 'gform_mc2p_action_fields', $this->get_current_feed(), $this->get_current_form() );
		$html .= ob_get_clean();
		//--------------------------------------------------------

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function settings_custom( $field, $echo = true ) {

		ob_start();
		?>
		<div id='gf_mc2p_custom_settings'>
			<?php
			do_action( 'gform_mc2p_add_option_group', $this->get_current_feed(), $this->get_current_form() );
			?>
		</div>

		<script type='text/javascript'>
			jQuery(document).ready(function () {
				jQuery('#gf_mc2p_custom_settings label.left_header').css('margin-left', '-200px');
			});
		</script>

		<?php

		$html = ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function settings_notifications( $field, $echo = true ) {
		$checkboxes = array(
			'name'    => 'delay_notification',
			'type'    => 'checkboxes',
			'onclick' => 'ToggleNotifications();',
			'choices' => array(
				array(
					'label' => esc_html__( "Send notifications for the 'Form is submitted' event only when payment is received.", 'gravityformsmc2p' ),
					'name'  => 'delayNotification',
				),
			)
		);

		$html = $this->settings_checkbox( $checkboxes, false );

		$html .= $this->settings_hidden( array( 'name' => 'selectedNotifications', 'id' => 'selectedNotifications' ), false );

		$form                      = $this->get_current_form();
		$has_delayed_notifications = $this->get_setting( 'delayNotification' );
		ob_start();
		?>
		<ul id="gf_mc2p_notification_container" style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;' ?>">
			<?php
			if ( ! empty( $form ) && is_array( $form['notifications'] ) ) {
				$selected_notifications = $this->get_setting( 'selectedNotifications' );
				if ( ! is_array( $selected_notifications ) ) {
					$selected_notifications = array();
				}

				//$selected_notifications = empty($selected_notifications) ? array() : json_decode($selected_notifications);

				$notifications = GFCommon::get_notifications( 'form_submission', $form );

				foreach ( $notifications as $notification ) {
					?>
					<li class="gf_mc2p_notification">
						<input type="checkbox" class="notification_checkbox" value="<?php echo $notification['id'] ?>" onclick="SaveNotifications();" <?php checked( true, in_array( $notification['id'], $selected_notifications ) ) ?> />
						<label class="inline" for="gf_mc2p_selected_notifications"><?php echo $notification['name']; ?></label>
					</li>
				<?php
				}
			}
			?>
		</ul>
		<script type='text/javascript'>
			function SaveNotifications() {
				var notifications = [];
				jQuery('.notification_checkbox').each(function () {
					if (jQuery(this).is(':checked')) {
						notifications.push(jQuery(this).val());
					}
				});
				jQuery('#selectedNotifications').val(jQuery.toJSON(notifications));
			}

			function ToggleNotifications() {

				var container = jQuery('#gf_mc2p_notification_container');
				var isChecked = jQuery('#delaynotification').is(':checked');

				if (isChecked) {
					container.slideDown();
					jQuery('.gf_mc2p_notification input').prop('checked', true);
				}
				else {
					container.slideUp();
					jQuery('.gf_mc2p_notification input').prop('checked', false);
				}

				SaveNotifications();
			}
		</script>
		<?php

		$html .= ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function checkbox_input_change_post_status( $choice, $attributes, $value, $tooltip ) {
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		$dropdown_field = array(
			'name'     => 'update_post_action',
			'choices'  => array(
				array( 'label' => '' ),
				array( 'label' => esc_html__( 'Mark Post as Draft', 'gravityformsmc2p' ), 'value' => 'draft' ),
				array( 'label' => esc_html__( 'Delete Post', 'gravityformsmc2p' ), 'value' => 'delete' ),

			),
			'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
		);
		$markup .= '&nbsp;&nbsp;' . $this->settings_select( $dropdown_field, false );

		return $markup;
	}

	/**
	 * Prevent the GFPaymentAddOn version of the options field being added to the feed settings.
	 *
	 * @return bool
	 */
	public function option_choices() {

		return false;
	}

	public function save_feed_settings( $feed_id, $form_id, $settings ) {

		//--------------------------------------------------------
		//For backwards compatibility
		$feed = $this->get_feed( $feed_id );

		//Saving new fields into old field names to maintain backwards compatibility for delayed payments
		$settings['type'] = $settings['transactionType'];

		$feed['meta'] = $settings;
		$feed         = apply_filters( 'gform_mc2p_save_config', $feed );

		//call hook to validate custom settings/meta added using gform_mc2p_action_fields or gform_mc2p_add_option_group action hooks
		$is_validation_error = apply_filters( 'gform_mc2p_config_validation', false, $feed );
		if ( $is_validation_error ) {
			//fail save
			return false;
		}

		$settings = $feed['meta'];

		//--------------------------------------------------------

		return parent::save_feed_settings( $feed_id, $form_id, $settings );
	}

	//------ SENDING TO MC2P -----------//

	public function redirect_url( $feed, $submission_data, $form, $entry ) {

		//Don't process redirect url if request is a MC2P return
		if ( ! rgempty( 'gf_mc2p_return', $_GET ) ) {
			return false;
		}

		//updating lead's payment_status to Processing
		GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );

        //Save form fields in session
        $settings = $this->get_plugin_settings();
        $order_id = $entry["id"];
        $amount = round(rgar($submission_data, 'payment_amount') * 100);
        $currency = rgar($entry, 'currency');
        $key = rgar($settings, 'gf_mc2p_key');
        $secret_key = rgar($settings, 'gf_mc2p_secret_key');
        $way = rgar($settings, 'gf_mc2p_way');
        $notify_url = get_bloginfo( 'url' ) . '/?page=gf_mc2p_ipn';
        $return_url = $this->return_url($form["id"], $entry["id"]);
        $cancel_url = $feed['meta']['cancelUrl'];

        $mc2p = new MC2P\MC2PClient($key, $secret_key);

		$line_items     = rgar( $submission_data, 'line_items' );
		$discounts      = rgar( $submission_data, 'discounts' );

		$shipping      = 0;
		$discount_amt  = 0;

		$products = [];

		//work on products
		if ( is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				$product_name = $item['name'];
				$quantity     = $item['quantity'];
				$unit_price   = $item['unit_price'];
				$product_id   = $item['id'];
				$is_shipping  = rgar( $item, 'is_shipping' );

				if ( $is_shipping ) {
					//populate shipping info
					$shipping += $unit_price;
				} else {
                    array_push($products, array(
                        "amount" => $quantity,
                        "product" => array(
                            "product_id" => $product_id,
                            "name" => $product_name,
                            "price" => $unit_price
                        )
                    ));
				}
			}
		}

		$email_input_id = rgar( $feed['meta'], "billingInformation_email" );
		$email = $this->get_field_value( $form, $entry, $email_input_id );

        $data = array(
            "order_id" => $order_id,
            "currency" => $currency,
            "return_url"  => $return_url,
            "cancel_url" => $cancel_url,
            "notify_url" => $notify_url,
            "products" => $products,
            "extra" => array(
                "email" => $email
            )
        );

        //look for discounts
		if ( is_array( $discounts ) ) {
			foreach ( $discounts as $discount ) {
				$discount_full = abs( $discount['unit_price'] ) * $discount['quantity'];
				$discount_amt += $discount_full;
			}
			if ( $discount_amt > 0 ) {
				$data['coupon_value'] = $discount_amt;
			}
		}

		if ( $shipping > 0) {
            $data['shipping_value'] = $discount_amt;
        }

        // Create transaction
        $transaction = $mc2p->Transaction($data);
        $transaction->save();

        if ( !$way || $way == 'redirect' ) {
            return $transaction->getPayUrl();
        } else  {
            return $notify_url = get_bloginfo( 'url' ) . '/?page=gf_mc2p_ipn&pay=1&token=' . $transaction->token;
        }
	}

	public function return_url( $form_id, $lead_id ) {
		$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

		$server_port = apply_filters( 'gform_mc2p_return_url_port', $_SERVER['SERVER_PORT'] );

		if ( $server_port != '80' ) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		$ids_query = "ids={$form_id}|{$lead_id}";
		$ids_query .= '&hash=' . wp_hash( $ids_query );

		$url = add_query_arg( 'gf_mc2p_return', base64_encode( $ids_query ), $pageURL );

		$query = 'gf_mc2p_return=' . base64_encode( $ids_query );
		return apply_filters( 'gform_mc2p_return_url', $url, $form_id, $lead_id, $query  );

	}

	public static function maybe_thankyou_page() {
		$instance = self::get_instance();

		if ( ! $instance->is_gravityforms_supported() ) {
			return;
		}

		if ( $str = rgget( 'gf_mc2p_return' ) ) {
			$str = base64_decode( $str );

			parse_str( $str, $query );
			if ( wp_hash( 'ids=' . $query['ids'] ) == $query['hash'] ) {
				list( $form_id, $lead_id ) = explode( '|', $query['ids'] );

				$form = GFAPI::get_form( $form_id );
				$lead = GFAPI::get_entry( $lead_id );

				if ( ! class_exists( 'GFFormDisplay' ) ) {
					require_once( GFCommon::get_base_path() . '/form_display.php' );
				}

				$confirmation = GFFormDisplay::handle_confirmation( $form, $lead, false );

				if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
					header( "Location: {$confirmation['redirect']}" );
					exit;
				}

				GFFormDisplay::$submission[ $form_id ] = array( 'is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead );
			}
		}
	}

	public function delay_post( $is_disabled, $form, $entry ) {

		$feed            = $this->get_payment_feed( $entry );
		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		if ( ! $feed || empty( $submission_data['payment_amount'] ) ) {
			return $is_disabled;
		}

		return ! rgempty( 'delayPost', $feed['meta'] );
	}

	public function delay_notification( $is_disabled, $notification, $form, $entry ) {
		if ( rgar( $notification, 'event' ) != 'form_submission' ) {
			return $is_disabled;
		}

		$feed            = $this->get_payment_feed( $entry );
		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		if ( ! $feed || empty( $submission_data['payment_amount'] ) ) {
			return $is_disabled;
		}

		$selected_notifications = is_array( rgar( $feed['meta'], 'selectedNotifications' ) ) ? rgar( $feed['meta'], 'selectedNotifications' ) : array();

		return isset( $feed['meta']['delayNotification'] ) && in_array( $notification['id'], $selected_notifications ) ? true : $is_disabled;
	}

	//------- PROCESSING MC2P (Callback) -----------//

	public function callback() {

		if ( ! $this->is_gravityforms_supported() ) {
			return false;
		}

		$this->log_debug( __METHOD__ . '(): IPN request received. Starting to process => ' . print_r( $_POST, true ) );

        $settings = $this->get_plugin_settings();
        $key = rgar($settings, 'gf_mc2p_key');
        $secret_key = rgar($settings, 'gf_mc2p_secret_key');

		$this->renderPay();

        $action = array();

        if ( !empty( $_REQUEST ) ) {
            $json = (array)json_decode(file_get_contents('php://input'));

            if ( !empty( $json ) ) {

                $mc2p = new MC2P\MC2PClient($key, $secret_key);

                @ob_clean();

                $notification_data = $mc2p->NotificationData($json, $mc2p);
                $transaction = $notification_data->getTransaction();
                $transaction_id = $transaction->id;
                $amount = $transaction->total_price;
                $action['status'] = $notification_data->getStatus();

                if ( $notification_data->getStatus() == 'D' ) {
                    $entry = $this->get_entry( $notification_data->getOrderId() );

                    //Ignore orphan IPN messages (ones without an entry)
                    if ( ! $entry ) {
                        $this->log_error( __METHOD__ . '(): Entry could not be found. Aborting.' );

                        return false;
                    }
                    $this->log_debug( __METHOD__ . '(): Entry has been found => ' . print_r( $entry, true ) );

                    if ( $entry['status'] == 'spam' ) {
                        $this->log_error( __METHOD__ . '(): Entry is marked as spam. Aborting.' );

                        return false;
                    }

                    //------ Getting feed related to this IPN ------------------------------------------//
                    $feed = $this->get_payment_feed( $entry );

                    //Ignore IPN messages from forms that are no longer configured with the MC2P add-on
                    if ( ! $feed || ! rgar( $feed, 'is_active' ) ) {
                        $this->log_error( __METHOD__ . "(): Form no longer is configured with MC2P Addon. Form ID: {$entry['form_id']}. Aborting." );

                        return false;
                    }
                    $this->log_debug( __METHOD__ . "(): Form {$entry['form_id']} is properly configured." );

                    $sale = $notification_data->getSale();
                    $sale_id = $sale->id;
                    $amount = $sale->amount;

                    //creates transaction
                    $action['id']               = $sale_id . '_' . $transaction_id;
                    $action['type']             = 'complete_payment';
                    $action['transaction_id']   = $sale_id;
                    $action['amount']           = $amount;
                    $action['entry_id']         = $entry['id'];
                    $action['payment_date']     = gmdate( 'y-m-d H:i:s' );
                    $action['payment_method']	= 'MyChoice2Pay';
                    $action['ready_to_fulfill'] = ! $entry['is_fulfilled'] ? true : false;

                    if ( ! $this->is_valid_initial_payment_amount( $entry['id'], $amount ) ){
                        //create note and transaction
                        $this->log_debug( __METHOD__ . '(): Payment amount does not match product price. Entry will not be marked as Approved.' );
                        GFPaymentAddOn::add_note( $entry['id'], sprintf( __( 'Payment amount (%s) does not match product price. Entry will not be marked as Approved. Transaction ID: %s', 'gravityformsmc2p' ), GFCommon::to_money( $amount, $entry['currency'] ), $sale_id ) );
                        GFPaymentAddOn::insert_transaction( $entry['id'], 'payment', $sale_id, $amount );

                        $action['abort_callback'] = true;
                    }
                } else if ( $notification_data->getStatus() == 'C' ) {
                    $action['id']             = $transaction_id;
                    $action['type']           = 'fail_payment';
                    $action['transaction_id'] = $transaction_id;
                    $action['entry_id']       = $entry['id'];
                    $action['amount']         = $amount;
                }
            }
        }

        if ( rgempty( 'entry_id', $action ) ) {
			return false;
		}

        return $action;
	}

	public function renderPay()
    {
        if ( rgget('pay') == '1' ) {
            $settings = $this->get_plugin_settings();
		    $way = rgar($settings, 'gf_mc2p_way');

		    $token = rgget('token');

		    if ( $way == 'iframe' ) {
                ob_start();
                ?>
                <?php get_header(); ?>
                <iframe src="https://pay.mychoice2pay.com/#/<?php echo $token; ?>/iframe" frameBorder="0" style="width: 100%; height: 700px"></iframe>
                <?php get_footer(); ?>
                <?php

                $html = ob_get_clean();

                die($html);
            }
        }
    }

	public function get_payment_feed( $entry, $form = false ) {

		$feed = parent::get_payment_feed( $entry, $form );

		if ( empty( $feed ) && ! empty( $entry['id'] ) ) {
			//looking for feed created by legacy versions
			$feed = $this->get_mc2p_feed_by_entry( $entry['id'] );
		}

		$feed = apply_filters( 'gform_mc2p_get_payment_feed', $feed, $entry, $form ? $form : GFAPI::get_form( $entry['form_id'] ) );

		return $feed;
	}

	private function get_mc2p_feed_by_entry( $entry_id ) {

		$feed_id = gform_get_meta( $entry_id, 'mc2p_feed_id' );
		$feed    = $this->get_feed( $feed_id );

		return ! empty( $feed ) ? $feed : false;
	}

	public function post_callback( $callback_action, $callback_result ) {
		if ( is_wp_error( $callback_action ) || ! $callback_action ) {
			return false;
		}

		//run the necessary hooks
		$entry          = GFAPI::get_entry( $callback_action['entry_id'] );
		$feed           = $this->get_payment_feed( $entry );
		$transaction_id = rgar( $callback_action, 'transaction_id' );
		$amount         = rgar( $callback_action, 'amount' );
		$subscriber_id  = rgar( $callback_action, 'subscriber_id' );
		$status         = rgar( $callback_action, 'status' );

		//run gform_mc2p_fulfillment only in certain conditions
		if ( rgar( $callback_action, 'ready_to_fulfill' ) && ! rgar( $callback_action, 'abort_callback' ) ) {
			$this->fulfill_order( $entry, $transaction_id, $amount, $feed );
		} else {
			if ( rgar( $callback_action, 'abort_callback' ) ) {
				$this->log_debug( __METHOD__ . '(): Callback processing was aborted. Not fulfilling entry.' );
			} else {
				$this->log_debug( __METHOD__ . '(): Entry is already fulfilled or not ready to be fulfilled, not running gform_mc2p_fulfillment hook.' );
			}
		}

		do_action( 'gform_post_payment_status', $feed, $entry, $status, $transaction_id, $subscriber_id, $amount );
		if ( has_filter( 'gform_post_payment_status' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_status.' );
		}
	}

	public function get_entry( $entry_id ) {

		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) ) {
			$this->log_error( __METHOD__ . '(): ' . $entry->get_error_message() );

			return false;
		}

		return $entry;
	}

	public function modify_post( $post_id, $action ) {

		$result = false;

		if ( ! $post_id ) {
			return $result;
		}

		switch ( $action ) {
			case 'draft':
				$post = get_post( $post_id );
				$post->post_status = 'draft';
				$result = wp_update_post( $post );
				$this->log_debug( __METHOD__ . "(): Set post (#{$post_id}) status to \"draft\"." );
				break;
			case 'delete':
				$result = wp_delete_post( $post_id );
				$this->log_debug( __METHOD__ . "(): Deleted post (#{$post_id})." );
				break;
		}

		return $result;
	}

	public function is_callback_valid() {
		if ( rgget( 'page' ) != 'gf_mc2p_ipn' ) {
			return false;
		}

		return true;
	}

	//------- AJAX FUNCTIONS ------------------//

	public function init_ajax() {

		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_mc2p_menu', array( $this, 'ajax_dismiss_menu' ) );

	}

	//------- ADMIN FUNCTIONS/HOOKS -----------//

	public function init_admin() {

		parent::init_admin();

		//add actions to allow the payment status to be modified
		add_action( 'gform_payment_status', array( $this, 'admin_edit_payment_status' ), 3, 3 );
		add_action( 'gform_payment_date', array( $this, 'admin_edit_payment_date' ), 3, 3 );
		add_action( 'gform_payment_transaction_id', array( $this, 'admin_edit_payment_transaction_id' ), 3, 3 );
		add_action( 'gform_payment_amount', array( $this, 'admin_edit_payment_amount' ), 3, 3 );
		add_action( 'gform_after_update_entry', array( $this, 'admin_update_payment' ), 4, 2 );

		add_filter( 'gform_addon_navigation', array( $this, 'maybe_create_menu' ) );
	}

	/**
	 * Add supported notification events.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array
	 */
	public function supported_notification_events( $form ) {
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		return array(
				'complete_payment'          => esc_html__( 'Payment Completed', 'gravityformsmc2p' ),
				'fail_payment'              => esc_html__( 'Payment Failed', 'gravityformsmc2p' )
		);
	}

	public function maybe_create_menu( $menus ) {
		$current_user = wp_get_current_user();
		$dismiss_mc2p_menu = get_metadata( 'user', $current_user->ID, 'dismiss_mc2p_menu', true );
		if ( $dismiss_mc2p_menu != '1' ) {
			$menus[] = array( 'name' => $this->_slug, 'label' => $this->get_short_title(), 'callback' => array( $this, 'temporary_plugin_page' ), 'permission' => $this->_capabilities_form_settings );
		}

		return $menus;
	}

	public function ajax_dismiss_menu() {

		$current_user = wp_get_current_user();
		update_metadata( 'user', $current_user->ID, 'dismiss_mc2p_menu', '1' );
	}

	public function temporary_plugin_page() {
		$current_user = wp_get_current_user();
		?>
		<script type="text/javascript">
			function dismissMenu(){
				jQuery('#gf_spinner').show();
				jQuery.post(ajaxurl, {
						action : "gf_dismiss_mc2p_menu"
					},
					function (response) {
						document.location.href='?page=gf_edit_forms';
						jQuery('#gf_spinner').hide();
					}
				);

			}
		</script>
		<?php
	}

	public function admin_edit_payment_status( $payment_status, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_status;
		}

		//create drop down for payment status
		$payment_string = gform_tooltip( 'mc2p_edit_payment_status', '', true );
		$payment_string .= '<select id="payment_status" name="payment_status">';
		$payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
		$payment_string .= '<option value="Paid">Paid</option>';
		$payment_string .= '</select>';

		return $payment_string;
	}

	public function admin_edit_payment_date( $payment_date, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_date;
		}

		$payment_date = $entry['payment_date'];
		if ( empty( $payment_date ) ) {
			$payment_date = gmdate( 'y-m-d H:i:s' );
		}

		$input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

		return $input;
	}

	public function admin_edit_payment_transaction_id( $transaction_id, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $transaction_id;
		}

		$input = '<input type="text" id="mc2p_transaction_id" name="mc2p_transaction_id" value="' . $transaction_id . '">';

		return $input;
	}

	public function admin_edit_payment_amount( $payment_amount, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_amount;
		}

		if ( empty( $payment_amount ) ) {
			$payment_amount = GFCommon::get_order_total( $form, $entry );
		}

		$input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

		return $input;
	}

	public function admin_update_payment( $form, $entry_id ) {
		check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

		//update payment information in admin, need to use this function so the lead data is updated before displayed in the sidebar info section
		$entry = GFFormsModel::get_lead( $entry_id );

		if ( $this->payment_details_editing_disabled( $entry, 'update' ) ) {
			return;
		}

		//get payment fields to update
		$payment_status = rgpost( 'payment_status' );
		//when updating, payment status may not be editable, if no value in post, set to lead payment status
		if ( empty( $payment_status ) ) {
			$payment_status = $entry['payment_status'];
		}

		$payment_amount      = GFCommon::to_number( rgpost( 'payment_amount' ) );
		$payment_transaction = rgpost( 'mc2p_transaction_id' );
		$payment_date        = rgpost( 'payment_date' );

		$status_unchanged = $entry['payment_status'] == $payment_status;
		$amount_unchanged = $entry['payment_amount'] == $payment_amount;
		$id_unchanged     = $entry['transaction_id'] == $payment_transaction;
		$date_unchanged   = $entry['payment_date'] == $payment_date;

		if ( $status_unchanged && $amount_unchanged && $id_unchanged && $date_unchanged ) {
			return;
		}

		if ( empty( $payment_date ) ) {
			$payment_date = gmdate( 'y-m-d H:i:s' );
		} else {
			//format date entered by user
			$payment_date = date( 'Y-m-d H:i:s', strtotime( $payment_date ) );
		}

		global $current_user;
		$user_id   = 0;
		$user_name = 'System';
		if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
			$user_id   = $current_user->ID;
			$user_name = $user_data->display_name;
		}

		$entry['payment_status'] = $payment_status;
		$entry['payment_amount'] = $payment_amount;
		$entry['payment_date']   = $payment_date;
		$entry['transaction_id'] = $payment_transaction;

		// if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
		if ( ( $payment_status == 'Approved' || $payment_status == 'Paid' ) && ! $entry['is_fulfilled'] ) {
			$action['id']             = $payment_transaction;
			$action['type']           = 'complete_payment';
			$action['transaction_id'] = $payment_transaction;
			$action['amount']         = $payment_amount;
			$action['entry_id']       = $entry['id'];

			$this->complete_payment( $entry, $action );
			$this->fulfill_order( $entry, $payment_transaction, $payment_amount );
		}
		//update lead, add a note
		GFAPI::update_entry( $entry );
		GFFormsModel::add_note( $entry['id'], $user_id, $user_name, sprintf( esc_html__( 'Payment information was manually updated. Status: %s. Amount: %s. Transaction ID: %s. Date: %s', 'gravityformsmc2p' ), $entry['payment_status'], GFCommon::to_money( $entry['payment_amount'], $entry['currency'] ), $payment_transaction, $entry['payment_date'] ) );
	}

	public function fulfill_order( &$entry, $transaction_id, $amount, $feed = null ) {

		if ( ! $feed ) {
			$feed = $this->get_payment_feed( $entry );
		}

		$form = GFFormsModel::get_form_meta( $entry['form_id'] );
		if ( rgars( $feed, 'meta/delayPost' ) ) {
			$this->log_debug( __METHOD__ . '(): Creating post.' );
			$entry['post_id'] = GFFormsModel::create_post( $form, $entry );
			$this->log_debug( __METHOD__ . '(): Post created.' );
		}

		if ( rgars( $feed, 'meta/delayNotification' ) ) {
			//sending delayed notifications
			$notifications = $this->get_notifications_to_send( $form, $feed );
			GFCommon::send_notifications( $notifications, $form, $entry, true, 'form_submission' );
		}

		do_action( 'gform_mc2p_fulfillment', $entry, $feed, $transaction_id, $amount );
		if ( has_filter( 'gform_mc2p_fulfillment' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_mc2p_fulfillment.' );
		}

	}

	/**
	 * Retrieve the IDs of the notifications to be sent.
	 *
	 * @param array $form The form which created the entry being processed.
	 * @param array $feed The feed which processed the entry.
	 *
	 * @return array
	 */
	public function get_notifications_to_send( $form, $feed ) {
		$notifications_to_send  = array();
		$selected_notifications = rgars( $feed, 'meta/selectedNotifications' );

		if ( is_array( $selected_notifications ) ) {
			// Make sure that the notifications being sent belong to the form submission event, just in case the notification event was changed after the feed was configured.
			foreach ( $form['notifications'] as $notification ) {
				if ( rgar( $notification, 'event' ) != 'form_submission' || ! in_array( $notification['id'], $selected_notifications ) ) {
					continue;
				}

				$notifications_to_send[] = $notification['id'];
			}
		}

		return $notifications_to_send;
	}

	private function is_valid_initial_payment_amount( $entry_id, $amount_paid ) {

		//get amount initially sent to mc2p
		$amount_sent = gform_get_meta( $entry_id, 'payment_amount' );
		if ( empty( $amount_sent ) ) {
			return true;
		}

		$epsilon    = 0.00001;
		$is_equal   = abs( floatval( $amount_paid ) - floatval( $amount_sent ) ) < $epsilon;
		$is_greater = floatval( $amount_paid ) > floatval( $amount_sent );

		//initial payment is valid if it is equal to or greater than product/subscription amount
		if ( $is_equal || $is_greater ) {
			return true;
		}

		return false;

	}

	public function mc2p_fulfillment( $entry, $mc2p_config, $transaction_id, $amount ) {
		//no need to do anything for mc2p when it runs this function, ignore
		return false;
	}

	/**
	 * Editing of the payment details should only be possible if the entry was processed by MC2P, if the payment status is Pending or Processing, and the transaction was not a subscription.
	 *
	 * @param array $entry The current entry
	 * @param string $action The entry detail page action, edit or update.
	 *
	 * @return bool
	 */
	public function payment_details_editing_disabled( $entry, $action = 'edit' ) {
		if ( ! $this->is_payment_gateway( $entry['id'] ) ) {
			// Entry was not processed by this add-on, don't allow editing.
			return true;
		}

		$payment_status = rgar( $entry, 'payment_status' );
		if ( $payment_status == 'Approved' || $payment_status == 'Paid' || rgar( $entry, 'transaction_type' ) == 2 ) {
			// Editing not allowed for this entries transaction type or payment status.
			return true;
		}

		if ( $action == 'edit' && rgpost( 'screen_mode' ) == 'edit' ) {
			// Editing is allowed for this entry.
			return false;
		}

		if ( $action == 'update' && rgpost( 'screen_mode' ) == 'view' && rgpost( 'action' ) == 'update' ) {
			// Updating the payment details for this entry is allowed.
			return false;
		}

		// In all other cases editing is not allowed.

		return true;
	}

	/**
	 * Activate sslverify by default for new installations.
	 *
	 * Transform data when upgrading from legacy mc2p.
	 *
	 * @param $previous_version
	 */
	public function upgrade( $previous_version ) {

		if ( empty( $previous_version ) ) {
			$previous_version = get_option( 'gf_mc2p_version' );
		}
	}

	public function uninstall(){
		parent::uninstall();
	}

	//------ FOR BACKWARDS COMPATIBILITY ----------------------//

	//This function kept static for backwards compatibility
	public static function get_config_by_entry( $entry ) {

		$mc2p = GF_Gateway_MC2P::get_instance();

		$feed = $mc2p->get_payment_feed( $entry );

		if ( empty( $feed ) ) {
			return false;
		}

		return $feed['addon_slug'] == $mc2p->_slug ? $feed : false;
	}

	//This function kept static for backwards compatibility
	//This needs to be here until all add-ons are on the framework, otherwise they look for this function
	public static function get_config( $form_id ) {

		$mc2p = GF_Gateway_MC2P::get_instance();
		$feed   = $mc2p->get_feeds( $form_id );

		//Ignore IPN messages from forms that are no longer configured with the MC2P add-on
		if ( ! $feed ) {
			return false;
		}

		return $feed[0]; //only one feed per form is supported (left for backwards compatibility)
	}

	//------------------------------------------------------

}
