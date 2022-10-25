<?php

namespace SkyWeb\WC_Iiko;

use JsonSerializable;

defined( 'ABSPATH' ) || exit;

class Delivery implements JsonSerializable {

	protected $id;
	protected $complete_before;
	protected $phone;
	protected $order_type_id;
	protected $delivery_point;
	protected $order_service_type;
	protected $comment;
	protected $customer;
	protected $guests;
	protected $marketing_source_id;
	protected $operator_id;
	protected $items;
	protected $discountsInfo;	
	protected $payments;

	public function __construct( $order_id, $iiko_order_id = null ) {

		$order = wc_get_order( $order_id );

		// string <uuid> Nullable  
		// Order ID. Must be unique.
		// If sent null, it generates automatically on iikoTransport side.
		$this->id = $this->generate_iiko_id( $order_id, $iiko_order_id );

		$this->shipping_method = reset($order->get_items( 'shipping' ))->get_method_id();  //!!! хз как работает reset
		// string <yyyy-MM-dd HH:mm:ss.fff> Nullable
		// Order fulfillment date.
		//// Date and time must be local for delivery terminal, without time zone (take a look at example).
		// If null, order is urgent and time is calculated based on customer settings, i.e. the shortest delivery time possible.
		// Permissible values: from current day and 14 days on.
		$this->complete_before = apply_filters( 'skyweb_wc_iiko_order_complete_before', null, $order_id );

		// Required.
		// string [ 8 .. 40 ] characters
		// Telephone number.
		// Must begin with symbol "+" and must be at least 8 digits.
		$this->phone = apply_filters( 'skyweb_wc_iiko_order_phone', preg_replace( '/\D/', '', get_post_meta($order_id, '_billing_phone', true) ) );
		if ( empty( $this->phone ) ) $this->phone = apply_filters( 'skyweb_wc_iiko_order_phone', preg_replace( '/\D/', '', get_post_meta($order_id, 'billing_order_phone', true) ) );
		if ( empty( $this->phone ) ) $this->phone = apply_filters( 'skyweb_wc_iiko_order_phone', preg_replace( '/\D/', '', get_post_meta($order_id, '_billing_order_phone', true) ) );		
		if ( empty( $this->phone ) ) $this->phone = apply_filters( 'skyweb_wc_iiko_order_phone', preg_replace( '/\D/', '', $order->get_billing_phone() ) );
		if ( empty( $this->phone ) ) $this->phone = '+71111111111'; 
		
		if ( empty( $this->phone ) ) {
			Logs::add_wc_error_log( 'User phone is empty.', 'create-delivery' );
		} else {
			$this->phone = $this->trim_string( $this->phone, 40 );
		}
		// echo $this->phone;
		// exit;
		// string <uuid> Nullable
		// Order type ID.
		// One of the fields required: orderTypeId or orderServiceType.
		$this->order_type_id = null;

		// string Nullable
		// Enum: "DeliveryByCourier" "DeliveryByClient"
		// Order service type.
		// One of the fields required: orderTypeId or orderServiceType.
		$this->order_service_type = $this->is_pickup() ? 'DeliveryByClient' : 'DeliveryByCourier';

		// object Nullable
		// Delivery point details.
		// Not required in case of customer pickup. Otherwise, required.
		$this->delivery_point = $this->delivery_point( $order_id, $order );

		// string Nullable
		// Order comment.
		$this->comment = $this->comment( $order_id, $order );

		// Required.
		// object
		// Customer.
		$this->customer = $this->customer( $order );

		// object Nullable
		// Guest details.
		$this->guests = $this->guests( $order_id );

		// string <uuid> Nullable
		// Marketing source (advertisement) ID.
		$this->marketing_source_id = null;

		// string <uuid> Nullable
		// Operator ID.
		$this->operator_id = null;

		// Required.
		// Array of iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.ProductOrderItem
		// (object) or
		// iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.CompoundOrderItem
		//(object)
		// Order items.
		$this->items = $this->order_items( $order );

		$this->discountsInfo = $this->order_coupons( $order );
		
		// Array of
		// iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.CashPayment
		// (object) or
		// iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.CardPayment
		// (object) or
		// iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.IikoCardPayment
		// (object) or
		// iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.ExternalPayment
		// (object) Nullable
		// Order payment components.
		$this->payments = apply_filters( 'skyweb_wc_iiko_order_payments', null, $order );
	}

	/**
	 * Check if the shipping method is pickup.
	 */
	protected function generate_iiko_id( $order_id, $iiko_order_id ) {

		if ( is_null( $iiko_order_id ) || empty( $iiko_order_id ) ) {

			$iiko_order_id = wp_generate_uuid4();
			wc_add_order_item_meta( $order_id, 'skyweb_wc_iiko_order_id', $iiko_order_id );
			update_post_meta($order_id, 'iiko_id', $iiko_order_id);
			return $iiko_order_id;
		}

		return sanitize_key( $iiko_order_id );
	}

	/**
	 * Check if the shipping method is pickup.
	 */
	protected function is_pickup() {

/* 		$shipping_methods       = WC()->session->get( 'chosen_shipping_methods' ); // chosen_payment_method
		$chosen_shipping_method = $shipping_methods[0];

		return 0 === strpos( $chosen_shipping_method, 'local_pickup' ); */
		//echo $this->shipping_method.'||';
		return ( $this->shipping_method == 'local_pickup' ? 1 : 0 );
	}

   
	protected function get_city_streets($city_name = ''){
		global $wpdb;
	
		if(!empty($city_name)){
			//ищем улицу по городу
			$streets = [];
			$wtitlequery = "SELECT * FROM iiko_cities WHERE iiko_city_name = '{$city_name}' " ;
			$c_results = $wpdb->get_results( $wtitlequery, ARRAY_A ) ;
		
			if(!$c_results){
				$wtitlequery = "SELECT * FROM iiko_cities WHERE iiko_city_name LIKE '%{$city_name}' " ;
				$c_results = $wpdb->get_results( $wtitlequery, ARRAY_A ) ;
				
			}
			foreach($c_results as $city_res){
				$wtitlequery = "SELECT * FROM iiko_streets WHERE iiko_city_id = '{$city_res['id']}' " ;
				$streets[$city_res['iiko_city_name']] = $wpdb->get_results( $wtitlequery, ARRAY_A ) ;
			}

		}else{
			//передаём все улицы
			$wtitlequery = "SELECT * FROM iiko_streets" ;
			$streets = $wpdb->get_results( $wtitlequery, ARRAY_A ) ;
		}


		return $streets;
	}	

	/**
	 * Delivery point.
	 */
	protected function delivery_point( $order_id, $order ) {

		if ( 'DeliveryByClient' === $this->order_service_type ) {

			return null;

		} else {


			$lat = get_post_meta( $order_id, 'lat', true );
			$long = get_post_meta( $order_id, 'long', true );

			// Required.
			// object
			// Street.
			$address   = array();
		//	$street_id = sanitize_key( get_post_meta( $order_id, '_billing_iiko_street_id', true ) );
			$city_name = !empty( $order->get_billing_city() ) ? $this->trim_string( wp_slash( $order->get_billing_city() ), 60 ) : null;
			// It's required specify only "classifierId" or "id" or "name" and "city".
			if ( ! empty( $street_id ) ) {

				// string <uuid> Nullable
				// ID.
				$address['street']['id'] = $street_id; // Already sanitized

			} else {

				$street_name = ! empty( $order->get_billing_address_1() ) ? wp_slash( $order->get_billing_address_1() ) : null;
				$street_name = trim($street_name);
				$street_name = trim($street_name, ',');
				$street_parce_data = $this->prepare_order_street($city_name, $street_name);
				// If user set an arbitrary street (we don't have street ID) use default street.
				//$default_street = get_option( 'skyweb_wc_iiko_default_street' );

				// Add the arbitrary street name to the order comment.
				$this->comment .= $street_name . PHP_EOL;

				$address['street'] = array(

					// string [ 0 .. 60 ] characters Nullable
					// Name.
					'name' => $this->trim_string( $street_name, 60 ),

					// string [ 0 .. 60 ] characters Nullable
					// City name.
					'city' => $city_name,
				);
			}
		if ( 'yes' === get_option( 'skyweb_wc_iiko_street_id_search_by_name' ) ) {
			/* Ищем улицу в айке */
			$street = [];
			$city_name = str_replace(['рабочий посёлок ', 'коттеджный посёлок ', 'посёлок ', 'деревня ', 'село '], '', $city_name);
			$city_streets =  $this->get_city_streets($city_name);
			$search_street = $street_parce_data['street'];
			$search_street = trim( str_replace(['переулок', 'улица', 'площадь', ', кв.', 'сквер', 'пер.', 'пеерулок', 'дом'], '', $search_street) );
			$search_street = trim( str_replace(['садоводческое некоммерческое товарищество', 'садовое некоммерческое товарищество'], 'снт', $search_street) );


			if(count($city_streets) == 1){
				$street = $this->search_street($search_street . ' (' . $city_name.')', array_shift($city_streets));
			
			}elseif(count($city_streets) > 1){
				//Если совпадение по нескольким городам
				$search_city = array_reduce($city_streets, function($accumulator, $item) {
					$adr1 = $accumulator['source_adress'];
					$adr2 = str_replace(['Россия, '], '', $item['address']);
					$p = -1;
					similar_text( $adr2, $adr1, $p );
					if($p > $accumulator['p']){
						$item['p'] = $p;
						$item['source_adress'] = $accumulator['source_adress'];			
						return $item;
					}
					return $accumulator;
				}, ['p' => 0, 'source_adress' => $city_name] );
			
				if( $search_city['p'] !== -1 ){
					$street = $this->search_street($search_street, array_values($city_streets)[$search_city['p']]);
				};


			}elseif(empty($city_streets)){
				$all_streets =  $this->get_city_streets();

				$street = $this->search_street_similar($search_street . ' ' . $city_name, array_values($all_streets));
			}

			if( !empty($street['iiko_street_id']) ){
				$address['street'] = array(

					'id' => $street['iiko_street_id'],

					//'name' => $street['iiko_street_name'],

					'city' => $city_name,
				);
			}
<<<<<<< HEAD

=======
		}
>>>>>>> misky
			// string [ 0 .. 10 ] characters Nullable
			// Postcode.
			$address['index'] = ! empty( $order->get_billing_postcode() ) ? $this->trim_string( wp_slash( $order->get_billing_postcode() ), 10 ) : null;

			// Required.
			// string [ 0 .. 100 ] characters
			// House.
			// In case useUaeAddressingSystem enabled max length - 100, otherwise - 10
			//$address['house'] = ! empty( $order->get_billing_address_2() ) ? $this->trim_string( wp_slash( $order->get_billing_address_2() ), 10 ) : 'NOT SET';
			$address['house'] = $street_parce_data['house'];
			if(empty($address['house'])) 
				$address['house'] = ! empty( $order->get_billing_address_2() ) ? $this->trim_string( wp_slash( $order->get_billing_address_2() ), 10 ) : 'NOT SET';
			// string [ 0 .. 10 ] characters Nullable
			// Building.
			$address['building'] = null;

			// string [ 0 .. 100 ] characters Nullable
			// Apartment.
			// In case useUaeAddressingSystem enabled max length - 100, otherwise - 10
			$address['flat'] = null;
			if(!empty($street_parce_data['apart'])) 	
				$address['flat'] = $street_parce_data['apart'];
			else $address['flat'] = get_post_meta( $order_id, 'billing_flat', true ) ? : get_post_meta( $order_id, '_billing_address_2', true );

			// string [ 0 .. 10 ] characters Nullable
			// Entrance.
			$address['entrance'] = get_post_meta( $order_id, 'billing_entrance', true ) ? : get_post_meta( $order_id, 'entrance', true );

			// string [ 0 .. 10 ] characters Nullable
			// Floor.
			$address['floor'] =  get_post_meta( $order_id, '_billing_floor', true ) ? :  get_post_meta( $order_id, '_billing_company', true );

			// string [ 0 .. 10 ] characters Nullable
			// Intercom.
			$address['doorphone'] = get_post_meta( $order_id, 'billing_flat', true ) ? : get_post_meta( $order_id, 'enter_code', true );

			// string <uuid> Nullable
			// Delivery area ID.
			$address['regionId'] = null;

			$delivery_point = array(

				// object Nullable
				// Delivery address coordinates.
				
				'coordinates'           => (!$lat || !$long) ? null : array( 'latitude'  => $lat, 'longitude' => $long ),

				// 'coordinates'           => array(
				// 	// Required.
				// 	// number <double>
				// 	// Latitude
				// 	'latitude'  => '',
				// 	// Required.
				// 	// number <double>
				// 	// Longitude
				// 	'longitude' => '',
				// ), 

				// object Nullable
				// Order delivery address.
				'address'               => $address,

				// string [ 0 .. 100 ] characters Nullable
				// Delivery location custom code in customer's API system.
				'externalCartographyId' => null,

				// string [ 0 .. 500 ] characters Nullable
				// Additional information.
				'comment'               => null,
			);

			return apply_filters( 'skyweb_wc_iiko_order_delivery_point', $delivery_point, $order_id );
		}
	}

	/**
	 * Customer.
	 */
	protected function comment( $order_id, $order ) {

		$comment_strings[] = 'Заказ №'.$order_id;
		//$comment_strings[] = ! empty( $this->comment ) ? $this->comment : '';
		$comment_strings[] = ! empty( $order->get_customer_note() ) ? 'Коментарий пользователя: '.$order->get_customer_note() : '';
		$comment_strings[] = $order->get_shipping_method();
		$comment_strings   = array_filter( $comment_strings );

		$i       = 1;
		$j       = count( $comment_strings );
		$comment = '';

		foreach ( $comment_strings as $comment_string ) {
			$string_end = $i ++ === $j ? '' : PHP_EOL;
			$comment    .= $comment_string . $string_end;
		}

		return wp_slash( apply_filters( 'skyweb_wc_iiko_order_comment',
			$comment,
			$order_id
		) );
	}

	/**
	 * Customer.
	 */
	protected function customer( $order ) {

		return array(
			// string <uuid> Nullable
			// Existing customer ID in RMS.
			'id'                            => null,

			// string [ 0 .. 60 ] characters Nullable
			// Name of customer.
			// Required for new customers (i.e. if "id" == null) Not required if "id" specified.
			'name'                          => ! empty( $order->get_billing_first_name() ) ? $this->trim_string( wp_slash( $order->get_billing_first_name() ), 60 ) : 'NOT SET',

			// string [ 0 .. 60 ] characters Nullable
			// Last name.
			'surname'                       => ! empty( $order->get_billing_last_name() ) ? $this->trim_string( wp_slash( $order->get_billing_last_name() ), 60 ) : null,

			// string [ 0 .. 60 ] characters Nullable
			// Comment.
			'comment'                       => null,

			// string <yyyy-MM-dd HH:mm:ss.fff> Nullable
			// Date of birth.
			'birthdate'                     => null,

			// string Nullable
			// Email.
			'email'                         => ! empty( $order->get_billing_email() ) ? sanitize_email( $order->get_billing_email() ) : null,

			// boolean
			// Whether user is included in promotional mailing list.
			'shouldReceivePromoActionsInfo' => false,

			// string
			// Enum: "NotSpecified" "Male" "Female"
			// Gender.
			'gender'                        => 'NotSpecified',
		);
	}

	/**
	 * Guests.
	 */
	protected function guests( $order_id ) {

		return apply_filters( 'skyweb_wc_iiko_order_guests',
			array(
				// Required.
				// integer <int32>
				// Number of persons in order. This field defines the number of cutlery sets
				'count'               => 1,

				// Required.
				// boolean
				// Attribute that shows whether order must be split among guests.
				'splitBetweenPersons' => false,
			),
			$order_id
		);
	}

	/**
	 * Create order items array.
	 *
	 * @param $order
	 *
	 * @return array
	 */
	protected function order_coupons( $order ){
		$coupons = $discounts = [];
		$products = $order->get_items('coupon');
		$free_discount_id = 'dca51988-bae8-4b10-a8f9-fd11fe6aae50'; //свободная скидка iiko id

		foreach ( $products as $item_id =>$product_obj ) {
			// Retrieving the coupon ID reference
			$coupon_post_obj = get_page_by_title( $product_obj->get_name(), OBJECT, 'shop_coupon' );
			//if(!empty($coupon_post_obj->ID))
			{
				//echo $product_obj->get_name().'--';
				$coupon_id = $coupon_post_obj->ID;
				//echo '$coupon_id='.$coupon_id.'|';
				// Get an instance of WC_Coupon object (necessary to use WC_Coupon methods)
				if($coupon_id){
					$coupon = new \WC_Coupon($coupon_id);
					## Filtering with your coupon custom types
					if( $coupon->is_type( 'fixed_cart' ) || $coupon->is_type( 'percent' ) ){
						$order_discount_iiko_id = get_post_meta($coupon_id, 'iiko_discount_id', true) ?: '';
						// Get the Coupon discount amounts in the order
						$order_discount_amount = wc_get_order_item_meta( $item_id, 'discount_amount', true );
						//$order_discount_tax_amount = wc_get_order_item_meta( $item_id, 'discount_amount_tax', true );
						if($order_discount_amount)
							$coupons[] = [
								'name' => $product_obj->get_name(),
								'discount' => $order_discount_amount,
								'discount_iiko_id' => $order_discount_iiko_id,
							];
						## Or get the coupon amount object
						//$coupons_amount = $coupon->get_amount();
						
						//echo '$coupons_amount2='.$order_discount_tax_amount.'|';
					}
				}
			}
		}
		if(empty($coupons)) return null;

		foreach($coupons as $coupon){
			$discounts[]=[
				'discountTypeId' => $coupon['discount_iiko_id'] ? : $free_discount_id,
				'sum' => $coupon['discount'],
				'selectivePositions' => null,
				'type' => 'RMS',
			];
		}
		
		return [
			'card' => null,
			'discounts' => $discounts
		];
	}
	 
	protected function order_items( $order ) {

		$i           = 0;
		$products    = $order->get_items();
		$order_items = array();
		$all_dops = [];
		$all_groups = [];
		$ids_map = []; // product_id -> cart_item_id
		if ( empty( $products ) ) {
			Logs::add_wc_error_log( 'No products in cart.', 'create-delivery' );

			return null;
		}
		
		//Получаем терминал из склада
		$stock_id = get_post_meta( $order->get_id(), 'stock_id', true );
		$terminal_id =  get_term_meta($stock_id, 'code_for_1c', true);


		foreach ( $products as $item_id =>$product_obj ) {

			$size_iiko_id     = null;
			$product_modifier = null;

			$product      = $product_obj->get_product();
			$product_id   = $product_obj->get_product_id();
			$product_id_tmp = $product_id ; //Сохраним product_id для связей, т.к. может поменяться из за вирт. товаров

			$ids_map[$item_id] = $product_id_tmp;
			// echo'_product=';
			// echo $product_id ;
			//echo '_v=';
			$variation_id   = $product_obj->get_variation_id();

			//если позиция - вариация, смотрим родителя (вирт. товары)
			if(!empty($variation_id)){
				$parent_origin_product_id = get_post_meta( $variation_id, 'parent_origin_product_id', true );
				if(!empty($parent_origin_product_id)){
					$product_id = $parent_origin_product_id;
					//echo '_parent_origin_product_id='.$product_id; modifiers_parent
				}
			}
		
			//echo '_name=';
			$product_name = $product_obj->get_name();
			//echo '__';
			// Required parameters.
			$product_iiko_id = sanitize_key( get_post_meta( $product_id, 'skyweb_wc_iiko_product_id', true ) );
			$product_amount  = $product_obj->get_quantity();


			/* Если есть modifiers_parent, то это модификатор, заносим его в массив модификаторов*/
			$modifiers_parent = wc_get_order_item_meta( $item_id, 'modifiers_parent', true );
			//echo 'product_id_tmp='.$product_id_tmp.'____';
			//echo 'modifiers_parent='.$modifiers_parent.'____';
			if(!empty($modifiers_parent)){
				$all_dops[$modifiers_parent][$product_id] = array(
					// Required.
					'productId'      => $product_iiko_id, // Already sanitized
					// Required.
					'amount'         => $product_amount,
					'productGroupId' => null, // Already sanitized
				);
				//Прерываем цикл
 
				continue;
			}
			

			/* Группы модификаторов товара*/
			$product_groups_data = get_post_meta( $product_id, 'group_modifiers_data', true );
			//сгруппированные по терминалам
			$all_groups[$terminal_id][$item_id]= $product_groups_data;


			// Exclude products from export without iiko ID.
			if ( empty( $product_iiko_id ) ) {
				Logs::add_wc_notice_log( "Product $product_name does not have iiko ID.", 'create-delivery' );

				continue;
			}

			// Variation.
			if ( $product->is_type( 'variation' ) ) {
				/*
				$variation_id                   = $product_obj->get_variation_id();
				$size_iiko_id                   = sanitize_key( get_post_meta( $variation_id, 'skyweb_wc_iiko_product_size_id', true ) );
				$size_iiko_id                   = ! empty( $size_iiko_id ) ? $size_iiko_id : null;
				$product_modifier_iiko_id       = sanitize_key( get_post_meta( $variation_id, 'skyweb_wc_iiko_product_modifier_id', true ) );
				$product_modifier_iiko_id       = ! empty( $product_modifier_iiko_id ) ? $product_modifier_iiko_id : null;
				$product_modifier_group_iiko_id = sanitize_key( get_post_meta( $variation_id, 'skyweb_wc_iiko_product_modifier_group_id', true ) );
				$product_modifier_group_iiko_id = ! empty( $product_modifier_group_iiko_id ) ? $product_modifier_group_iiko_id : null;

				echo $product_obj->get_product_id().'--';
				// Exclude variations from export without iiko size and modifier IDs.
				if ( empty( $size_iiko_id ) && empty( $product_modifier_iiko_id ) ) {
					Logs::add_wc_notice_log( "Variation $product_name does not have iiko size ID and iiko modifier ID.", 'create-delivery' );

					continue;
				}

				if ( ! empty( $product_modifier_iiko_id ) ) {

					$product_modifier = array(
						// Required.
						'productId'      => $product_modifier_iiko_id, // Already sanitized
						// Required.
						'amount'         => 1,
						'productGroupId' => $product_modifier_group_iiko_id, // Already sanitized
					);

				}
				*/
			}

			$order_items[$item_id] = array(
				// Required.
				// string <uuid>
				// ID of menu item.
				'productId'        => $product_iiko_id, // Already sanitized

				// Array of objects
				// (iikoTransport.PublicApi.Contracts.Deliveries.Request.CreateOrder.Modifier) Nullable
				// Modifiers.
				'modifiers'        => ! is_null( $product_modifier ) ? array( $product_modifier ) : array(),

				// number <double> Nullable
				// Price per item unit.
				'price'            => null,

				// string <uuid> Nullable
				// Unique identifier of the item in the order. MUST be unique for the whole system.
				//Therefore it must be generated with Guid.NewGuid().
				// If sent null, it generates automatically on iikoTransport side.
				'positionId'       => null,

				// string
				'type'             => 'Product',

				// Required.
				// number <double> [ 0 .. 999.999 ]
				// Quantity.
				'amount'           => floatval( $product_amount ),
				// TODO - from PHP 7.4
				/*'amount'                    => filter_var( $product_amount,
					FILTER_VALIDATE_FLOAT,
					array(
						'options' => array(
							'min_range' => 0,
							'max_range' => 999.999,
						),
					) ),*/

				// string <uuid> Nullable
				// Size ID. Required if a stock list item has a size scale.
				'productSizeId'    => $size_iiko_id, // Already sanitized

				// object Nullable
				// Combo details if combo includes order item.
				'comboInformation' => null,

				// string [ 0 .. 255 ] characters Nullable
				// Comment.
				'comment'          => null,
			);

			$i ++;
		}

		/* Если есть платная доставка, добавляем к заказу товар "доставка" */
		if($order->get_shipping_total()){
			$order_items[0] = [
				'productId'        => 'dc38bfde-ba59-4cf4-847c-3988aee2a05c', // Already sanitized
				'modifiers'        => null,
				'price'            => null,
				'positionId'       => null,
				'type'             => 'Product',
				'amount'           => 1,
				'productSizeId'    => null, // Already sanitized
				'comboInformation' => null,
				'comment'          => null,
			];
		}
		// print_r($ids_map);
 		// print_r($all_dops);
 		// print_r($all_groups);
		// exit;
		//совмещаем товары и модификаторы
		foreach($order_items as $prod_id=>$order_item){
			if(!empty($all_dops[$prod_id])){
				//echo $prod_id.'||';
				$groups = $all_groups[$prod_id];
				// echo $prod_id;
				// print_R($groups);

				//назначаем модификаторам группы, берём из товара
				foreach ($all_dops[$prod_id] as $dop_product_id=>$dop){
					if($groups){
						foreach($groups as $gr){
							if(in_array( $dop_product_id, $gr['modifiers'])){
								//	if( $gr['id'] != 'b3b25328-5f22-4186-b90e-7049d2af81fa' )
								$dop['productGroupId'] = $gr['id'];
							}
						}
					}
					$order_items[$prod_id]['modifiers'][]=$dop;
				}
				//$order_items[$prod_id]['modifiers']=$all_dops[$prod_id];

				//Если есть допы и количество товара в заказе > 1, разбиваем товар на несколько, а то айка отдаёт ошибку допов (что их не может быть более.., при ограниченном количестве)
				if($order_items[$prod_id]['amount'] > 1){
					$prod_amount = $order_items[$prod_id]['amount'];
					$order_items[$prod_id]['amount'] = 1;
					foreach($order_items[$prod_id]['modifiers'] as &$_modifier){
						$_modifier['amount'] = $_modifier['amount'] / $prod_amount;
					}
					for($ii = 1; $ii < $prod_amount; $ii++){
						$order_items[$prod_id.'_'.$ii] = $order_items[$prod_id];
					}
				}
			}
		}
		// print_R($order_items);
		// exit;

		//разделяем товары т.к. айка ругается на модификатор ограниченный колл 1, если товара больше 1 (то и модификатора передаётся больше)
		

		// print_R(array_values($order_items));
		// exit;
		//Уберём ключи у товаров
		return array_values($order_items);
	}

	/**
	 * Convert variable to string and trim it.
	 */
	public function trim_string( $val, $max ) {

		return mb_strimwidth( strval( $val ), 0, $max );
	}

	/**
	 * Get ID.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set ID.
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * Return JSON object representation.
	 */
	public function jsonSerialize() {
		return array(
			'id'                => $this->id,
			'completeBefore'    => $this->complete_before,
			'phone'             => $this->phone,
			'orderTypeId'       => $this->order_type_id,
			'orderServiceType'  => $this->order_service_type,
			'deliveryPoint'     => $this->delivery_point,
			'comment'           => $this->comment,
			'customer'          => $this->customer,
			'guests'            => $this->guests,
			'marketingSourceId' => $this->marketing_source_id,
			'operatorId'        => $this->operator_id,
			'items'             => $this->items,
			'discountsInfo'		=> $this->discountsInfo,
			'payments'          => $this->payments,
		);
	}

	protected function prepare_order_street($city, $street, $adres = ['street'=>'', 'house'=>'', 'apart'=>''] ) {
		if(!empty($street)){
			 $parts_1 = explode(', ', $street);

			// //ищем квартиру
			// if(count($parts_1) > 1)
			// 	if(!empty($parts_1[array_key_last($parts_1)])){
			// 		$adress['apart'] = trim(str_replace('кв. ', '', $parts_1[array_key_last($parts_1)] ));
			// 	};
			//ищем дом
			$parts_2 = explode(' ', $parts_1[0]); 
			$pattern = "/[0-9]+/";
			if(count($parts_1) > 1)
				if(!empty($parts_1[array_key_last($parts_1)])){
					$adress['house'] = trim(str_replace(['дом', 'д.'], '', $parts_1[array_key_last($parts_1)] ));
				};

			// if(preg_match($pattern, $parts_2[array_key_last( $parts_2 )])){
			// 	$adress['house'] = trim( array_pop( $parts_2 ) );
			// };
			
			//Записываем улицу
			$adress['street'] = trim( str_replace('улица ', '', implode(' ', $parts_2) ) );
			
		}
		
		$adress['city'] = $city;
		return $adress;
	}

	protected function search_street($search_street, $streets){
		$street = [];
		// $search_res = array_filter( $streets , function($v) use ( $search_street ){
		// 	//echo $v['name'] .'__'. $search.'__'.strpos(strtolower($v['name']), strtolower($search)).'<br>';
		// 	//echo $v['iiko_street_name'].'____'.$search_street.'----';
		// 	return strpos(strtolower($v['iiko_street_name']), strtolower($search_street)) !== false;
	   	// });

		$search_res = array_reduce($streets, function($accumulator, $item) {
			//print_R($accumulator);
			$street1 = $accumulator['source_street'];
			$street2 = $item['iiko_street_name'];
			$p = -1;
			
			similar_text( $street1, $street2, $p );
			
			//echo $street1.'___'.$street2.'___p='.$p.' | ';
			if($p > $accumulator['p']){
				$item['p'] = $p;
				$item['source_street'] = $accumulator['source_street'];
				return $item;
			}
			return $accumulator;
		}, ['p' => 0, 'source_street' => $search_street] );
		

		//$street = array_shift($search_res);
		return $search_res;
	}

	protected function search_street_similar($search_street, $streets){
		$search = [];
		if($search_street == 'ДНП Ясное') $search_street = 'ДНП Ясное тер';
		if($search_street == 'снт Исток садовое некоммерческое товарищество Исток') $search_street = 'Исток территория снт';

		// echo '$search_street='.$search_street;
		// print_R($streets);
		$search = array_reduce($streets, function($accumulator, $item) {
			//print_R($accumulator);
			$street1 = $accumulator['source_street'];
			$street2 = $item['iiko_street_name'];
			$p = -1;
			
			similar_text( $street1, $street2, $p );
			
			//echo $street1.'___'.$street2.'___p='.$p.' | ';
			if($p > $accumulator['p']){
				$item['p'] = $p;
				$item['source_street'] = $accumulator['source_street'];
				return $item;
			}
			return $accumulator;
		}, ['p' => 0, 'source_street' => $search_street] );


	   
		//$street = array_shift($search_res);
		return $search;
	}
	
}