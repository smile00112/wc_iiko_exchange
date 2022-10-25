<?php

namespace SkyWeb\WC_Iiko;

defined( 'ABSPATH' ) || exit;

use SkyWeb\WC_Iiko\API_Requests\Export_API_Requests;
use SkyWeb\WC_Iiko\API_Requests\Common_API_Requests;
use SkyWeb\WC_Iiko\API_Requests\Import_API_Requests;

class Export {

	/**
	 * Constructor.
	 */
	public function __construct() {
		//add_action( 'woocommerce_checkout_order_created', array( $this, 'export_delivery' ) ); // Comment for debug
		// add_action( 'woocommerce_thankyou', array( $this, 'export_delivery_debug' ) ); // Uncomment for debug
		add_action( 'woocommerce_order_status_processing', array( $this, 'update_delivery' ) ); // Comment for debug 
		
		//add_action( 'woocommerce_rest_order_created', array( $this, 'export_delivery_rest' ), 10, 2 ); // Comment for debug
		add_action( 'import_all', array( $this, 'import_all') );
		add_action( 'import', array( $this, 'cron_import') );
			
		add_action( 'stop_list', array( $this, 'cron_get_stop_list') );
		
		add_action( 'alive_terminals', array( $this, 'get_alive_terminals') );
		
		add_action( 'orders_exchange', array( $this, 'orders_status_exchange') );
		
		add_action( 'get_hooks', array( $this, 'get_hooks') );
		
		add_action( 'register_hook', array( $this, 'register_hook') );
		
		add_action( 'status', array( $this, 'get_status') );

		add_action( 'test_order', array( $this, 'send_test_order') );

		add_action( 'check_order', array( $this, 'check_order_status') );
			add_action( 'tt2', array( $this, 'tt2') );
	
		
		add_action( 'DeliveryOrderUpdate', array( $this, 'delivery_order_update'), 10, 2 );


	}

	/**
	 * Export order.
	 *
	 * @param $order
	 *
	 * @return array|bool
	 */
	public function get_status() {

		$cap = new Common_API_Requests();
	   $data = $cap->get_status();
	   /* */
   }	 
	
   public function update_delivery( $order_id ) {

	   $order = wc_get_order( $order_id );

	   return $this->export_delivery( $order );
   }

   /*Заказ из rest запроса*/
   public function export_delivery_rest( $order_id, $wc_order ) {
		/* 		
			if ( ! $is_creating ) {
				return;
			} 
		*/
		//$order_id = $object->get_id();
		//$wc_order = new WC_Order( $order_id );
		
	   	/*Не отсылаем в айку созданный, но неоплаченный заказ */
	   	// if ( 'wc-pending' == $wc_order->get_status() || 'pending' == $wc_order->get_status() ) { 
		// 	Logs::add_wc_error_log( "Order $order_id has status 'wc-pending'. Abort", 'create-delivery' );
		// 	return false;
		// }

		return $this->export_delivery_process( $wc_order, $order_id );
	}
	
   public function export_delivery( $order ) {

	   $order_id = $order->get_id();
		// if(file_exists($_SERVER['DOCUMENT_ROOT'] . '/adeb_iiko.txt')) {
		// 	$file = fopen($_SERVER['DOCUMENT_ROOT']. '/adeb_iiko.txt', 'a+');
		// 	$results = '___order  export_delivery___'; 
		// 	$results.= date('Y-m-d H-i-s');
		// 	$results.= '______';
		// 	$results.= $order->get_status();
		// 			$results.= '______';
		// 	$results.= print_r($order, true);
		// 	$results.= print_r($_HEADER, true);
			
		// 	$results.= $json;
		// 	fwrite($file, $results . PHP_EOL);
		// 	fclose($file);
		// }


	   
	   return $this->export_delivery_process( $order, $order_id );
   }

   /**
	* Export order for debug.
	*
	* @param $order_id
	*
	* @return array|bool
	*/
   public function export_delivery_debug( $order_id ) {

	   $order = wc_get_order( $order_id );

	   return $this->export_delivery_process( $order, $order_id );
   }

   /**
	* Export order manually (from orders list).
	* Action in Admin class.
	*/
   public function export_order_manually() {

	   if (
		   current_user_can( 'edit_shop_orders' )
		   && check_admin_referer( 'skyweb-wc-iiko-export-order' )
		   && isset( $_GET['status'], $_GET['order_id'] )
	   ) {

		   // $status = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		   $order            = wc_get_order( absint( wp_unslash( $_GET['order_id'] ) ) );
		   $created_delivery = $this->export_delivery( $order );

		   $this->print_response( $created_delivery );

	   } else {
		   echo 'You cannot see this page.';
		   $this->print_back_button();
	   }

	   // wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );

	   wp_die();
   }

   /**
	* Check created delivery (from orders list).
	* Action in Admin class.
	*/
   public function check_created_delivery_manually() {

	   if (
		   current_user_can( 'edit_shop_orders' )
		 //  && check_admin_referer( 'skyweb-wc-iiko-check-created-delivery' )
		   && isset( $_GET['order_id'] )
	   ) {

		   $order_id      = absint( wp_unslash( $_GET['order_id'] ) );
		 	$iiko_order_id = $this->get_iiko_order_id( $order_id );

		   if ( !empty($iiko_order_id) ) {
			   $export_api_requests = new Export_API_Requests();
			   $retrieved_order     = $export_api_requests->retrieve_order_by_id( $iiko_order_id );

				$retrieved_order['result'] = !empty($retrieved_order['responce_body']['orders']) ? 'Заказ доставлен в iiko' : 'Ошибка при доставке заказа';
				if(empty($retrieved_order['responce_body']['orders'])) $retrieved_order['error'] = true;

				update_post_meta( $order_id, '_iiko_order_check', 1 );
				update_post_meta( $order_id, '_iiko_order_check_results', $retrieved_order );


				echo json_encode($retrieved_order);

		   } else {

				update_post_meta( $order_id, '_iiko_order_check', 0 );
				update_post_meta( $order_id, '_iiko_order_check_results', [] );

			   Logs::add_wc_error_log( "Order $order_id doesn't have iiko ID.", 'check-delivery' );
			  // echo "Order $order_id doesn't have iiko ID.";
				echo json_encode(['error' =>  "Order $order_id doesn't have iiko ID."]);

		   }

	   } else {
		   echo 'You cannot see this page.';
		   $this->print_back_button();
	   }

	   wp_die();
   }

   /**
	* Export order process.
	*
	* @param $order
	* @param $order_id
	*/
   protected function export_delivery_process( $order, $order_id ) {

	   if ( 'failed' === $order->get_status() ) {
		   Logs::add_wc_error_log( "Order $order_id has status 'failed'.", 'create-delivery' );
	   }
	   

	   $iiko_order_id = trim($order->get_meta('iiko_id'));
	   
	   if(empty($iiko_order_id)){
		   $iiko_order_id = $this->get_iiko_order_id( $order_id );
		   update_post_meta($order_id, 'iiko_id', $this->get_iiko_order_id( $order_id ));
	   }
	   
	   //Получаем терминал из склада
	   $stock_id = get_post_meta( $order_id, 'stock_id', true );
	   $terminal_id =  get_term_meta($stock_id, 'code_for_1c', true);
	   //$stock_id = get_post_meta($order_id, 'stock_id', true);
	   /*
	   Островцы - 1b715844-76e6-4504-82b6-782dd9f23665
	   
	   Раменское - a8db1666-836e-5d4f-016d-44b58c4700cd
	   */
		// if($stock_id == 119){ // островцы  119
		// 	//$organization_id = '7434328a-76a9-42d3-9d0b-56d4361c22b4';
		// 	$terminal_id = '1b715844-76e6-4504-82b6-782dd9f23665' ;	
		// }else{// раменское 110
		// 	//$organization_id = '8dca9f20-6a0c-4390-b258-4ebdda026b4e';
		// 	$terminal_id = 'a8db1666-836e-5d4f-016d-44b58c4700cd' ;

		// }

				   
	   $delivery      = new Delivery( $order_id, $iiko_order_id );
		// print_r( $delivery );
		// exit;
	   $order->add_order_note( 'Iiko order ID: ' . $delivery->get_id() );

	   $export_api_requests = new Export_API_Requests();
	   $created_delivery    = $export_api_requests->create_delivery( $delivery, null, $terminal_id );

	   debug_to_file('iiko create-delivery-request__order_id='.$order_id);
	   debug_to_file( print_R($created_delivery, true) );		

	   Logs::add_wc_debug_log( $created_delivery, 'create-delivery-response' );
	   
	   if(empty($created_delivery['error'])){
		   debug_to_file('create-delivery-response__order_id='.$order_id);
		   debug_to_file( print_R($created_delivery, true) );			
	   }else{
		   debug_to_file('_____Сбой передачи заказа в Iiko_____'); 
		   $order->update_status('failed', 'Сбой передачи заказа в Iiko');
	   }


	   //do_action( 'skyweb_wc_iiko_created_delivery', $created_delivery, $order_id );

	   return $created_delivery;
   }

   /**
	* Print back button.
	*/
   protected function print_back_button() {
	   printf( '%1$s%2$s%3$s%4$s', '<hr>', '<a href="' . admin_url( 'edit.php?post_type=shop_order' ) . '">', 'Back', '</a>' );
   }

   /**
	* Print back button.
	*/
   protected function print_response( $data ) {

	   printf( '%1$s%2$s%3$s%4$s', '<pre>', wc_print_r( $data, true ), '</pre>', '<hr>' );

	   echo json_encode( $data, JSON_UNESCAPED_UNICODE );

	   $this->print_back_button();
   }

   /**
	* Get iiko order ID.
	*/
   protected function get_iiko_order_id( $order_id ) {

	   $iiko_order_id = wc_get_order_item_meta( $order_id, 'skyweb_wc_iiko_order_id' );

	   if ( is_string( $iiko_order_id ) ) {
		   return $iiko_order_id;
	   }

	   return null;
   }

   
	   /*Получаем "Живые" точки*/
   public function get_alive_terminals(){
	   
	   $export_api_requests = new Export_API_Requests();
	   
	   $data = $export_api_requests->get_alive_terminals();
	   
	   if(!empty($_GET['debug'])){
		   echo '<pre>';
		   print_r($data);
	   }
	   
	   return $data;
	   /*Заказ*/
	   //$this->export_delivery_debug( 118198 );  
   } 	
	

   /*Регистрируем хук*/
   public function register_hook() {

		$cap = new Common_API_Requests();
	   $data = $cap->register_hook();
	   /* */
   }
   /*Получаем хуки*/
   public function get_hooks() {

	$cap = new Common_API_Requests();
	$data = $cap->get_hooks();
	   /* */
   }		

	public function import_all() {
		echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><pre>';
		echo '___';
		$cap = new Import_API_Requests();
		$data = $cap->import_products_all_terminals();

	}

	public function send_test_order() {

		$order_id = $_GET['order_id'];
	
		$order = wc_get_order( absint( $order_id ) );
	
		echo 'test_order start:'.$order_id;
	
		ini_set('display_errors', 'Off'); //отключение ошибок на фронте
		ini_set('log_errors', 'On'); //запись ошибок в логи
	
		   $iiko_order_id = $order->get_meta('iiko_id');
			//echo   ' $iiko_order_id='. $iiko_order_id.'__';
	
			$stock_id = get_post_meta($order_id, 'stock_id', true);
			/*
			Островцы - 1b715844-76e6-4504-82b6-782dd9f23665

			Раменское - a8db1666-836e-5d4f-016d-44b58c4700cd
			*/
			// if($stock_id == 119){ // островцы  119
			// 	 //$organization_id = '7434328a-76a9-42d3-9d0b-56d4361c22b4';
			// 	 $terminal_id = '1b715844-76e6-4504-82b6-782dd9f23665' ;	
			// }else{// раменское 110
			// 	 //$organization_id = '8dca9f20-6a0c-4390-b258-4ebdda026b4e';
			// 	 $terminal_id = 'a8db1666-836e-5d4f-016d-44b58c4700cd' ;
	 
			// }

			//Получаем терминал из склада
			$stock_id = get_post_meta( $order_id, 'stock_id', true );
			$terminal_id =  get_term_meta($stock_id, 'code_for_1c', true);


			// 			echo	$stock_id.'|';
			// 			echo	$terminal_id.'|';

			// exit;

			if(empty($iiko_order_id))
			{
				$iiko_order_id = $this->get_iiko_order_id( $order_id );
				update_post_meta($order_id, 'iiko_id', $this->get_iiko_order_id( $order_id ));
				update_post_meta($order_id, 'iiko_id', $this->get_iiko_order_id( $order_id ));
			}
		   
			$delivery      = new Delivery( $order_id, $iiko_order_id );
			echo ' $delivery=';
	// print_R( $delivery);
	// exit;
			//   $order->add_order_note( 'Iiko order ID: ' . $delivery->get_id() );
	
		   $export_api_requests = new Export_API_Requests();
		   $created_delivery    = $export_api_requests->create_delivery( $delivery, null, $terminal_id );
		   echo '_______ $created_delivery';
		   print_r($created_delivery);
		   $check_delivery    = $export_api_requests->check_delivery( $created_delivery['orderInfo']['id'], null);
			echo '_______ $check_delivery_______';
			print_r( $check_delivery);
			 //  Logs::add_wc_debug_log( $created_delivery, 'create-delivery-response' );
	
		   //do_action( 'skyweb_wc_iiko_created_delivery', $created_delivery, $order_id );
	
		   return $created_delivery;
	}

	/*Обновление статуса заказа*/
	public function delivery_order_update($order_iiko_id, $new_status) {
 		if(!empty($_GET['debug'])) echo "delivery_order_update $order_iiko_id, $new_status ";
		//echo $new_status.'___';
		$wc_order_status = $this->prepare_order_status($new_status);
		/*Ищем id поста по id  заказа в iiko*/
		global $wpdb ;
		$table = $wpdb->prefix . "postmeta" ;
		$wc_order_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM `wp_postmeta` WHERE `meta_key` = 'iiko_id' AND meta_value = '%s'", $order_iiko_id)  );
		if(!empty($wc_order_id) && !empty($wc_order_status)){
			//echo 222;
			//do_action('iiko_update_order_status', $wc_order_id, $wc_order_status);
			$order = new \WC_Order($wc_order_id);
			if($wc_order_status)
				$order->update_status($wc_order_status, 'Изменение статуса из IIKO.');
		}

		//$order_ids = array_column( $result, null, 'meta_value' );
	}
	
	protected function prepare_order_status( $iiko_order_status ) {
		$wc_status = '';
		switch($iiko_order_status){
			case "Unconfirmed":
				$wc_status = 'wc-pending';
			break;
			case "CookingStarted":
				$wc_status = 'wc-making';
			break;			
			case "WaitCooking":
				$wc_status = '';
			break;		
			case "Waiting":
				$wc_status = 'wc-on-hold';
			break;		
			case "CookingCompleted":
				$wc_status = 'wc-done';
			break;	
			case "OnWay":
				$wc_status = 'wc-kurier';
			break;	
			case "Delivered":
				$wc_status = 'wc-completed';
			break;
			case "Closed":
				$wc_status = 'wc-completed';
			break;			
			case "Cancelled":
				$wc_status = 'wc-cancelled';
			break;	
			
		}
		//"Unconfirmed" "WaitCooking" "ReadyForCooking" "CookingStarted" "CookingCompleted" "Waiting" "OnWay" "Delivered" "Closed" "Cancelled"

		return $wc_status;
	}
	public function check_order_status() {
			$order_id = $_GET['order_id'];
		$order = wc_get_order( absint( $order_id ) );
		$order_iiko_id = get_post_meta($order_id, 'iiko_id', true);
		//Получаем терминал из склада
		$stock_id = get_post_meta( $order_id, 'stock_id', true );
		$terminal_id =  get_term_meta($stock_id, 'code_for_1c', true);
		$organization_id = 'a2d17b3b-9395-48f7-ad0a-e4db339ab01a';// get_term_meta($stock_id, 'organization_code', true) ?: null;
		echo '___'.$order_iiko_id .'__'. $organization_id.'_';
		if($order_iiko_id && $organization_id){
			echo 4444;
				$export_api_requests = new Export_API_Requests();
				$res = $export_api_requests->check_delivery($order_iiko_id, $organization_id);
				print_r($res);
		}

			
	}


	public function tt2() {

		$args = array(
			'status' => ['wc-processing'],				// новый заказы
			//'date_created' => '<' . ( time() - 3600 ), // за последний час
		);

		$orders = wc_get_orders($args);
		foreach ($orders as $item) {

			$order_id = $item->get_id();
			$iiko_order_check = get_post_meta( $order_id, '_iiko_order_check', true );
	
			if($iiko_order_check == ''){
				//echo 	$order_id.'||';	
				//$iiko_order_check_data = get_post_meta( $order_id, '_iiko_order_check_results', true );
				$iiko_order_id = get_post_meta( $order_id, 'iiko_id', true );
				if ( ! empty( $iiko_order_id ) ) {
					$export_api_requests = new Export_API_Requests();
					$retrieved_order     = $export_api_requests->retrieve_order_by_id( $iiko_order_id );
					
					$retrieved_order['result'] = !empty($retrieved_order['responce_body']['orders']) ? 'Заказ доставлен в iiko' : 'Ошибка при доставке заказа';
					if(empty($retrieved_order['responce_body']['orders'])) $retrieved_order['error'] = true;
	
					update_post_meta( $order_id, '_iiko_order_check', 1 );
					update_post_meta( $order_id, '_iiko_order_check_results', $retrieved_order );
	
					
				}else update_post_meta( $order_id, '_iiko_order_check', 0 );
			}else{
				
			}
		}


	}

}
