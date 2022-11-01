<?php

namespace SkyWeb\WC_Iiko\Admin;

defined( 'ABSPATH' ) || exit;

class Order {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'export_order_to_iiko_button' ), 10, 2 );
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'check_created_delivery_from_iiko_button' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box_iico_order_check') );

		// add
		add_filter('manage_edit-shop_order_columns', array( $this, 'column_order_check_1'), 20);
		// populate
		add_action('manage_shop_order_posts_custom_column', array( $this, 'column_order_check_2') );
		// make sortable
		//add_filter('manage_edit-shop_order_sortable_columns', 'mx_ready_time_3');
	}



	// how to sort
	//add_action('pre_get_posts', 'mx_ready_time_4');

	function column_order_check_1($col_th) {
		//добавление колонки/название
		return wp_parse_args(array('iiko_order_check' => 'Отправлен в iiko'), $col_th);
	}

	function column_order_check_2($column_id) {
		//задать название meta для вывода в колонку
		if ($column_id == 'iiko_order_check') {
			$ch = get_post_meta(get_the_ID(), '_iiko_order_check', true);

			if($ch == 1) echo '<span style="color:green">Да</span>';
				else 
			if($ch == -1) echo '<span style="color:red">Нет</span>' ;
		}
		
	}

	// function column_order_check_3($a) {
	// 	return wp_parse_args(array('ready_time' => 'by_ready_time'), $a);

	// }



	/**
	 * Export button in admin orders list.
	 *
	 * @param $actions
	 * @param $order
	 *
	 * @return mixed
	 */
	function export_order_to_iiko_button( $actions, $order ) {

		if ( ! $order->has_status( array( 'completed' ) ) ) {

			$status   = method_exists( $order, 'get_status' ) ? $order->get_status() : $order->status;
			$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

			$actions['skyweb-wc-iiko-export-order'] = array(
				'url'    => wp_nonce_url(
					admin_url( 'admin-ajax.php?action=skyweb_wc_iiko_export_order&status=' . $status . '&order_id=' . $order_id ),
					'skyweb-wc-iiko-export-order'
				),
				'name'   => esc_attr__( 'Export order to iiko', 'skyweb-wc-iiko' ),
				'action' => 'skyweb-wc-iiko-export-order',
			);
		}

		return $actions;
	}

	/**
	 * Check created delivery button in admin orders list.
	 *
	 * @param $actions
	 * @param $order
	 *
	 * @return mixed
	 */
	function check_created_delivery_from_iiko_button( $actions, $order ) {

		$status   = method_exists( $order, 'get_status' ) ? $order->get_status() : $order->status;
		$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

		$actions['skyweb-wc-iiko-check-created-delivery'] = array(
			'url'    => wp_nonce_url(
				admin_url( 'admin-ajax.php?action=skyweb_wc_iiko_check_created_delivery&status=' . $status . '&order_id=' . $order_id ),
				'skyweb-wc-iiko-check-created-delivery'
			),
			'name'   => esc_attr__( 'Check created delivery from iiko', 'skyweb-wc-iiko' ),
			'action' => 'skyweb-wc-iiko-check-created-delivery',
		);

		return $actions;
	}

	function add_meta_box_iico_order_check(){
		add_meta_box( 'couriers_box', __('Проверка заказа','woocommerce'), [ $this, 'mv_add_other_fields_for_iiko_order_check' ], 'shop_order', 'side', 'core' );
	}

	function mv_add_other_fields_for_iiko_order_check()
	{
		global $post;

		// $meta_field_data = get_post_meta( $post->ID, '_my_field_slug', true ) ? get_post_meta( $post->ID, '_my_field_slug', true ) : '';
			//$iiko_order_check = get_post_meta( $post->ID, '_iiko_order_check', true );
			$iiko_order_check_results = get_post_meta( $post->ID, '_iiko_order_check_results', true );
			//print_r($iiko_order_check_results);
			//$order = new WC_Order($post->ID);
			
			$order_sended =  empty($s) ? true : false;
			$check_result_area = '<div id="check_result_area"></div>';
			$btn = '<button type="button" class="button send_order_to_courier button-primary" onclick="check_iiko_order('.$post->ID.');">Проверить заказ</button>';

		// 	if($order_sended)
		// 	$btn = '<button type="button" class="button send_order_to_courier button-primary" onclick="send_order_to_couriers('.$post->ID.');">Отправить курьерам</button>';
		// else 
		// 	$btn = '<button type="button" class="button button-primary" onclick="alert(\'Заказ уже отправлен курьерам\');">Уже оправлено</button>';
		echo 	'
			<div class="order_check_data_contener">
				'.$btn.'
				<div id="order_check_result_text">'.(!empty($iiko_order_check_results['result']) ? $iiko_order_check_results['result'] : "").'</div>
				<span id="show_order_check_results" onclick=show_order_check_request_results()>Результаты запроса</span>
				<div id = "order_check_results" style="display:none">
				<p>Запрос</p>
				<textarea id="check_request_body" placeholder="тело запроса">'.(!empty($iiko_order_check_results['request_body']) ? json_encode($iiko_order_check_results['request_body'], true) : "").'</textarea>
				<p>Ответ</p>
				<textarea id="check_responce_body" placeholder="тело ответа" style="height: 122px;">'.(!empty($iiko_order_check_results['responce_body']) ? json_encode($iiko_order_check_results['responce_body'], true) : "").'</textarea>
				</div>
			</div>
		<style>
			#couriers_box .inside{
				text-align: center;
			}
			.order_check_data_contener{
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 15px;

			}
			#show_order_check_results{
				text-decoration: underline;
				cursor: pointer;
			}
			#order_check_results{
				width: 100%;
				text-align: left;

			}
		</style>
		<script>
			function show_order_check_request_results(){
				jQuery("#order_check_results").fadeIn();
				jQuery("#show_order_check_results").hide();
			}

			function check_iiko_order(order_id){
				jQuery.ajax({
					type : "GET",
					url : "/wp-admin/admin-ajax.php",
					async: true,
					data : {action: "skyweb_wc_iiko_check_created_delivery", order_id: order_id},
					dataType: "json",
					beforeSend: function (xhr) {
					},
					complete: function() {
					},
					success: function (data) {
						console.log(data);
						if( typeof(data.error) !== \'undefined\') alert(\'Произошла ошибка\');
						else{ 
							alert(\'Заказ проверен\');
							jQuery("#order_check_result_text").text(data.result);
							jQuery("#check_request_body").text( JSON.stringify(data.request_body) );
							jQuery("#check_responce_body").text( JSON.stringify(data.responce_body) );
							//jQuery(\'.send_order_to_courier\').prop("disabled", true);
						}
					},
					});
			}
		</script>

		';

	}

}