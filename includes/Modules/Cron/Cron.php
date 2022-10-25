<?php
namespace SkyWeb\WC_Iiko\Modules\Cron;
use SkyWeb\WC_Iiko\API_Requests\Export_API_Requests;

defined( 'ABSPATH' ) || exit;

class Cron {

	public static function init() {

		
		if ( ! defined( 'SKYWEB_WC_IIKO_FILE' ) ) {
			define( 'SKYWEB_WC_IIKO_FILE', __FILE__ );
		}


		// register_activation_hook( SKYWEB_WC_IIKO_FILE, array( __CLASS__, 'activation' ) );
		// register_deactivation_hook( SKYWEB_WC_IIKO_FILE, array( __CLASS__, 'deactivation' ) );
		// add_action( 'init', array( __CLASS__, 'create_wp_cron_hook' ) );


		add_action( 'wp', array( __CLASS__, 'create_wp_cron_hook' ) );
		add_action( 'import_nomenclature_by_cron_schedule', array( __CLASS__, 'import_nomenclature_by_cron') );



	}



	public static function activation() {

		//add_filter('cron_schedules',[ $this, 'iiko_1min_cron_schedules' ]);
		//add_filter('cron_schedules','iiko_1min_cron_schedules');

		if ( ! wp_next_scheduled( 'skyweb_wc_iiko_nomenclature_cron_update' ) ) {
			echo 111;
			wp_schedule_event( time(), '1min', 'skyweb_wc_iiko_nomenclature_cron_update' );
		}else  echo 222;

		die();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivation() {
		wp_clear_scheduled_hook( 'skyweb_wc_iiko_nomenclature_cron_update' );
	}

	/**
	 * Add hooks for iiko CRON functions.
	 */
	public static function create_wp_cron_hook() {


		if ( ! wp_next_scheduled( 'import_nomenclature_by_cron_schedule' ) ) 
		{
			//wp_schedule_event( time(), 'hourly',array( __CLASS__,  'expiry_schedule77' )); //hourly можно заменить one_min для теста 
			wp_schedule_event( time(), '1min' , 'import_nomenclature_by_cron_schedule' );
	
		}

	}
	
	/**
	 * Import nomenclature by CRON.
	 */
	public static function import_nomenclature_by_cron() {

		$args = array(
			'status' => ['wc-processing'],				// новый заказы
			//'date_created' => '<' . ( time() - 3600 ), // за последний час
		);

		$orders = wc_get_orders($args);
		foreach ($orders as $item) {

			$order_id = $item->get_id();
			$iiko_order_check = get_post_meta( $order_id, '_iiko_order_check', true );
	
			if( $iiko_order_check == '' ){
				//echo 	$order_id.'||';	
				//$iiko_order_check_data = get_post_meta( $order_id, '_iiko_order_check_results', true );
				$iiko_order_id = get_post_meta( $order_id, 'iiko_id', true );
				if ( ! empty( $iiko_order_id ) ) {
					$export_api_requests = new Export_API_Requests();
					$retrieved_order     = $export_api_requests->retrieve_order_by_id( $iiko_order_id );
					
					$retrieved_order['result'] = !empty($retrieved_order['responce_body']['orders']) ? 'Заказ доставлен в iiko' : 'Ошибка при доставке заказа';
					if(empty($retrieved_order['responce_body']['orders'])){
						$retrieved_order['error'] = true;
						update_post_meta( $order_id, '_iiko_order_check', -1 );
					}else
						update_post_meta( $order_id, '_iiko_order_check', 1 );

					update_post_meta( $order_id, '_iiko_order_check_results', $retrieved_order );
	
					
				}else update_post_meta( $order_id, '_iiko_order_check', 0 ); //
			}else{
				
			}
		}

		// TODO call update nomenclature function.
		// 1. Get chosen groups from options.
		// 2. Get nomenclature from iiko.
		// 3. Check if chosen group is persists in iiko and import in and products (use transients as usual).
		// 4. Optional check revision.
		// 5. Send email if something wrong.
	}
}

//Cron::init();