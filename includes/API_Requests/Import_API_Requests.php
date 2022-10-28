<?php

namespace SkyWeb\WC_Iiko\API_Requests;

defined( 'ABSPATH' ) || exit;

use SkyWeb\WC_Iiko\Import;

class Import_API_Requests extends Common_API_Requests {

	/**
	 * Import groups (product categories) to WooCommerce.
	 *
	 * TODO - rewrite. 
	 *
	 * @return array
	 */
	protected function import_groups( $param_groups ) {

		$chosen_groups    = array();
		$processed_groups = array();

		// Get groups from cache.
		$groups = $this->get_cache( 'skyweb_wc_iiko_groups', 'groups' );

		// TODO - return errors on bad checking.
		$this->check_array( $groups, 'Groups array is empty.' );

		$chosen_groups_iiko_ids = $this->check_groups( array_values( $param_groups ) );

		if ( false === $chosen_groups_iiko_ids ) {
			$chosen_groups_iiko_ids = array_values( get_option( 'skyweb_wc_iiko_chosen_groups' ) );
		}

		// [ (int) index => (string) 'iiko_group_id', ... ]
		$groups_iiko_ids = array_column( $groups, 'id' );

		foreach ( $chosen_groups_iiko_ids as $chosen_groups_iiko_id ) {

			$chosen_group = $groups[ array_search( $chosen_groups_iiko_id, $groups_iiko_ids ) ];

			if ( is_array( $chosen_group ) && ! empty( $chosen_group ) ) {

				$chosen_groups[ sanitize_text_field( $chosen_group['name'] ) ] = sanitize_key( $chosen_group['id'] );

				$imported_product_cat = Import::insert_update_product_cat( $chosen_group );

				if ( false !== $imported_product_cat ) {
					// [ (int) term_id => (string) 'iiko_group_id', ... ]
					$processed_groups[ $imported_product_cat['term_id'] ] = $imported_product_cat['iiko_id'];
				}
			}
		}

		// Update chosen groups in the plugin settings if we called the method with parameters (groups) and they are correct.
		if ( ! empty( $param_groups ) && false !== $chosen_groups_iiko_ids ) {

			delete_option( 'skyweb_wc_iiko_chosen_groups' );

			if ( ! update_option( 'skyweb_wc_iiko_chosen_groups', $chosen_groups ) ) {
				$this->logs->add_error( 'Cannot add chosen groups to the plugin settings.' );
			}
		}

		return $processed_groups;
	}

	/**
	 * Import products to WooCommerce.
	 *
	 * @return int
	 */
	protected function import_products( $processed_groups ) {
		// ini_set('display_errors', 'On'); //отключение ошибок на фронте
		// ini_set('log_errors', 'On'); //запись ошибок в логи
		// Get other nomenclature info from cache.

		$simple_groups = $this->get_cache( 'skyweb_wc_iiko_simple_groups', 'groups list' );
		$dishes        = $this->get_cache( 'skyweb_wc_iiko_dishes', 'dishes' );
		$goods         = $this->get_cache( 'skyweb_wc_iiko_goods', 'goods' );
		$modifiers     = $this->get_cache( 'skyweb_wc_iiko_modifiers', 'modifiers' );
		$sizes         = $this->get_cache( 'skyweb_wc_iiko_sizes', 'sizes' );

		// Check nomenclature.
		$simple_groups = is_array( $simple_groups ) && ! empty( $simple_groups ) ? $simple_groups : array();
		$dishes        = is_array( $dishes ) && ! empty( $dishes ) ? $dishes : array();
		$goods         = is_array( $goods ) && ! empty( $goods ) ? $goods : array();
		// Change keys onto iiko IDs and remove doubled IDs.
		$modifiers = is_array( $modifiers ) && ! empty( $modifiers ) ? array_column( $modifiers, null, 'id' ) : array();
		$sizes     = is_array( $sizes ) && ! empty( $sizes ) ? array_column( $sizes, null, 'id' ) : array();

		// Import only dishes and goods.
		$products = array_merge( $dishes, $goods );

		// TODO - return errors on bad checking.
		$this->check_array( $products, 'Products array is empty.' );

		// Successful processed products.
		$processed_products = 0;

		// [ (string) 'iiko_product_id' => (string) 'iiko_group_id', ... ]
		$product_group_iiko_ids = array_column( $products, 'parentGroup', 'id' );
		// [ (string) 'iiko_product_id' => (array), ... ]
		$products_reindexed_iiko_ids = array_column( $products, null, 'id' );

		// Find related to the group products and added it to WooCommerce.
//print_R($products_reindexed_iiko_ids);

		//$terminal_id =  get_option( 'skyweb_wc_iiko_terminal_id' );
		$stock = get_terms([ 'taxonomy'=> ['location'], 'get' => 'all', 'meta_key' => 'code_for_1c', 'meta_value' => $this->terminal_id]); // , 'meta_value' => $terminal_id пока прикрепляем товар ко всем складам

// //echo json_encode($modifiers);
// print_r($modifiers);
// echo 77777;
// exit;
//print_R($product_group_iiko_ids);

		foreach ( $processed_groups as $product_cat_term_id => $product_cat_iiko_id ) {

			// [ (int) index => (string) 'iiko_product_id', ... ]
			$product_cat_related_products_ids = array_keys( $product_group_iiko_ids, $product_cat_iiko_id );
			$updated_modifiers = [];

			foreach ( $product_cat_related_products_ids as $product_cat_related_product_id ) {

//echo "-".$product_cat_related_product_id."-";
				// if(
				// 	$product_cat_related_product_id == '9cce8269-a9ae-488a-af1d-05cf04166896'
				// 	|| $product_cat_related_product_id == '2f489588-aa7b-490d-849b-c7c16b2a4f76'
				// )
				// continue;
// print_R($modifiers);
// echo 66666666;
				$related_product = $products_reindexed_iiko_ids[ $product_cat_related_product_id ];
// print_R($simple_groups);
// echo 77777;
// print_r($related_product);

// echo 1;
// exit;

				foreach($related_product['groupModifiers'] as $gr_mod){
					foreach($gr_mod['childModifiers'] as $mod){
						//echo '____modifier____';
						$modifier = $modifiers[$mod['id']];
						//print_R($modifier);
					}
				}


				$imported_product = Import::insert_update_product( $related_product, $product_cat_term_id, $modifiers, $sizes, $simple_groups, $stock );
						
				
				/*!!! fix меню. Перенесём допы из modifiers в groupModifiers !!!*/
						foreach($related_product['modifiers'] as $modifier){
							//$related_product['groupModifiers'][0]['childModifiers'][]=$modifier;
							$dopGroup_minAmount = $modifier['minAmount'];
							$dopGroup_maxAmount = $modifier['maxAmount'];
							$dopGroup_required = $modifier['required'];
							$dopGroup_id = $modifier['id'];
							$dopGroup_name = 'Дополнительно';

							if(empty($related_product['groupModifiers'][$dopGroup_id])){
								$related_product['groupModifiers'][$dopGroup_id]= [
									'id' => '',
									'name' => $dopGroup_name,
									'minAmount' => $dopGroup_minAmount,
									'maxAmount' => $dopGroup_maxAmount,
									'required' => $dopGroup_required,
									'childModifiers' => []
								];
							}
							$related_product['groupModifiers'][$dopGroup_id]['childModifiers'][]=$modifier;

						}

						
						/* обрабатываем модификаторы (допы) */
						$product_dops_array = []; 
						$dops_cat_term_id = 92; //Категория куда будем кидать допы

						//для wok лапши модификаторы в конструктор, а не допы
						//if(!in_array($product_cat_iiko_id, ['b88c720a-a6b8-49e1-af05-5a27aad1523b', '78227033-a0c3-4bb1-b507-971d627b8580'])){
						if(0){
							if(!empty($related_product['groupModifiers'])){

								foreach($related_product['groupModifiers'] as $gr_mod){
									foreach($gr_mod['childModifiers'] as $mod){

									$modifier = $modifiers[$mod['id']];
									//	if( $modifier['count'] == 0 ) {  continue; }
									//echo $modifier['name'].'__';
									if($modifier['name'] != 'Допы'){
										$modifier['modifier_parent_id'] = $imported_product;
										/* Импортируем доп */
										$imported_product_dop = Import::insert_update_product( $modifier, $dops_cat_term_id, [], [], [], $stock ); 
											$product_dops_array[]=$imported_product_dop;
										}
									}
								}
								/* Привязываем допы к товару  */
								{
									
								}
							}
						}

						update_post_meta( $imported_product, '_upsell_ids', $product_dops_array );

						// echo $imported_product;
						// update_post_meta( $imported_product, '_upsell_ids', $product_dops_array );
						// exit;

						/*************************************************** */
						// $zero_price_groups_ids = [
						// 	'67493fdb-2d34-431a-84bf-cd699934c246',
						// 	'f86f4e11-678b-4171-9c81-7d1a36b6cc83',
						// 	'2e39d690-be0a-4b86-8b52-a6082ef644e9',
						// 	'955770fe-9ef4-46c6-a5cd-1c80c0af235b'
						// ];



						if(!empty($related_product['groupModifiers'])){

						/*Модификаторы в конструктор*/

						//Суём модификаторы в констрруктор только для wok лапши (upd. теперь для всех])
						//if(in_array($product_cat_iiko_id, ['b88c720a-a6b8-49e1-af05-5a27aad1523b', '78227033-a0c3-4bb1-b507-971d627b8580']))
						{
							$constructor_data = [];
							foreach($related_product['groupModifiers'] as $modifGroup){
								$group_data = [];
								$zero_price = false;
								if(!empty($modifGroup['childModifiers'])){
									$maxAmount = $modifGroup['maxAmount'] ;
									$minAmount = $modifGroup['minAmount'] ;
									$required = $modifGroup['required'] ;
									$group_id = $modifGroup['id'];
									$modifier_group_name = 'модификатор_'.$simple_groups[$group_id];
		
									if(!empty($modifGroup['name'])){
										$modifier_group_name = 'модификатор_'.$modifGroup['name'];
									}
									// if(in_array( $group_id, $zero_price_groups_ids ) )
									// 	$zero_price = true;
		
									// Проверяем / добавляем категорию для модификаторов
									$term = term_exists( $product_cat_name = $modifier_group_name, 'product_cat' );
									if ( $term !== 0 && $term !== null ) {
										$term_id = absint( $term['term_id'] );
									}else{
										$term_id = Import::insert_update_product_cat( ['name' => $modifier_group_name, 'id'=> $group_id, 'isIncludedInMenu' => true, 'isGroupModifier' => false, 'isDeleted'=>false ], $parent_id = 15 );
									}

									$group_data = [ 'id' => $group_id, 'title' => (!empty($modifGroup['name']) ? $modifGroup['name'] : $simple_groups[$group_id]), 'maxAmount' => $maxAmount, 'minAmount' => $minAmount,'required' => $required, 'modifiers'=>[] ];
		
									//Проходим по модификаторам товара
									foreach($modifGroup['childModifiers'] as $mod){
										$modifier_info = $modifiers[$mod['id']];
		
										//создаём товары для них 
										if(!empty($modifier_info)){

											$modifier_id = empty($updated_modifiers[$mod['id']] ) 
												? $this->import_update_modifier($zero_price, $modifier_info, $term_id, $stock) 
												: $updated_modifiers[$mod['id']];
											//	$modifier_id =  	$this->import_update_modifier($zero_price, $modifier_info, $term_id, $stock) ;

											
											//Заносим данные для мета поля основного товара
											if(!empty($modifier_id)){
												$updated_modifiers[$mod['id']] = $modifier_id;
												$group_data['modifiers'][] = $modifier_id;
											}
										}
									}
									$constructor_data[]=$group_data;
									
								}
							}

							//Данные по группам модификаторов заносим в мета поле group_modifiers_data с привязкой к текущему терминалу
							//$group_modifiers_data = [];
							$group_modifiers_data = get_post_meta( $imported_product, 'group_modifiers_data', true ) ?: [];
							$group_modifiers_data[$this->terminal_id] = $constructor_data;
							update_post_meta( $imported_product, 'group_modifiers_data',  $group_modifiers_data);
						}

						//   print_r( $constructor_data);
						// //	echo 77777;
						// print_r($related_product);
						// echo 2;
						//exit;
					
					}


						/*************************************************** */
					if( 'yes' === get_option( 'skyweb_wc_iiko_make_virtual_pizza_by_size' ) ){
						//ВИРТУАЛЬНЫЕ ВАРИАЦИИ  30 и 40 см

						//Правим кривой размер в имени товара
						$related_product['name'] = str_replace(['30см'], '30 см', $related_product['name']);
						/* Смотрим на вариативность и создаём вирт. вариативный товар */
						$virt_prod_size = '';
						$virt_prod_size_term_id = 0;
						$product_id = 0;
						$take_main_product_info = false;

						if (strpos(mb_strtolower($related_product['name']), '30 см') !== false ) {
							$virt_prod_size = '30 см';
							$virt_prod_size_term_id = 31;
							$take_main_product_info = true;
						}
						if (strpos(mb_strtolower($related_product['name']), '40 см') !== false ) {
							$virt_prod_size = '40 см';
							$virt_prod_size_term_id = 30;
						}	

						if($virt_prod_size){
							//$counter++;
							$virtual_product_name = str_replace([' 30 см', ' 30 СМ', ' 40 см', ' 40 СМ', ], '', $related_product['name']);
							$virtual_product_variation_name = $virtual_product_name . ' - ' . $virt_prod_size; 
							$args = array(
								'meta_key' => 'virtual_variative_product',
								'meta_value' => $virtual_product_name,
								'post_type' => 'product',
								'post_status' => 'any',
								'posts_per_page' => -1
							);
							$search_posts = get_posts($args)[0];
							$post = $search_posts ? $search_posts->ID : 0;

							// echo $virtual_product_name;	
							// echo $post;							
							// print_R($search_posts);
							// echo '____';
							// exit;

							//$post = post_exists( $virtual_product_name, '', '', 'product' );

							//Добавляем основной товар, который сединит в себе два вариативных товара с размерами 
							$variation_price = $related_product['sizePrices'][0]['price']['currentPrice'];
							if ( $post !== 0 ) {
								//update
								$product_id = $this->import_make_virtual_product(
									[
										'product_name' => $virtual_product_name,
										'price' => $variation_price,
										'stoks_terms' => $stock,
										'count' => 999,
										'post_id' => absint( $post )
									]
								);

								$take_main_product_info = false;
							}else{
								//insert
								$product_id = $this->import_make_virtual_product(
									[
										'product_name' => $virtual_product_name,
										'price' => $variation_price,
										'stoks_terms' => $stock,
										'count' => 999
									]
								);
							}


							//Добавляем вариацию
							if($product_id)
							{
								// echo 'add__'. $virtual_product_variation_name;
								$variation_id = add_variation_product_sbis( $product_id , "size", [ '30 см', '40 см' ], [$virt_prod_size_term_id], [ $virt_prod_size ], [ $virt_prod_size => $variation_price ]);
								//Привязываем вариацию к складам
								$this->product_to_stock($variation_id, $stock);

																/* Берём картинку и допы из основного товара (30 см) (при создании товара)*/
								if($take_main_product_info = true){
								 	//Картинка
									$thumb_id = get_post_thumbnail_id( $imported_product );
									if($thumb_id)
										set_post_thumbnail( $product_id, $thumb_id );

								 	//Допы
									update_post_meta( $product_id, '_upsell_ids', $product_dops_array );
								}


								// if( $variation_id ){
								// 	update_post_meta( $variation_id, '_upsell_ids', $product_dops_array );
								// }
								// if($variation_id){
								// 	$this->product_to_stock($variation_id, $stock);
								// 	update_post_meta( $product_id, '_stock_status', 'instock'); 
								// 	update_post_meta( $variation_id, '_stock_status', 'instock'); 

								update_post_meta( $variation_id, 'parent_origin_product_id', $imported_product); 
								// 	update_post_meta( $variation_id, '_sku', $imported_product['post_id']);				
								// }

							}
						}
					}

//print_r($imported_product);
//echo '___';
//print_r($product_id);	
//echo '___';
//print_r($constructor_data);
//exit;

// echo $post.'__';
// if($post == 31367)
// exit;

						/* цепляем конструктор к товару */
						$constructor_to_product_id = !empty($product_id) ? $product_id : $imported_product;
						// //открепляем конструктор 
						delete_field('supplements', $constructor_to_product_id);
						if( !empty($constructor_data)){//&& $new_product
							//если создавался виртуальный товар, цепляем конструктор к нему
							wp_set_object_terms($constructor_to_product_id, "supplements", 'product_type');
							$constr_arr = Import::modifiers_to_constructor($constructor_to_product_id, $constructor_data);
						}
// echo $constructor_to_product_id;						
// exit;

				if ( false !== $imported_product ) {
					$processed_products ++;
				}
			}
		}
// echo '$mmm='.$mmm;		
// echo '$updated_modifiers--';		
// print_R($updated_modifiers);

		return $processed_products;
	}



	public function import_make_virtual_product( $data = [] ) {
		/*
		[
			'product_name'
			'price'
		]
		*/
		//global $wpdb;
		$pizza_cat_term_id = $this->virtual_pizza_category_id; //Категория куда будем кидать виртуальные (сборные товары)

		$post = array(
			'post_author' => 1,
			'post_content' => '', //Описание товара	
			'post_status' => "publish",
			'post_title' => $data['product_name'], // Название товара
			'post_type' => "product",
		);

		if(empty($data['post_id'])){
			//echo '_Create___'.$data['product_name'];
			$post_id = wp_insert_post($post); //Создаем запись
			//wp_set_object_terms($post_id, "simple", 'product_type');
			wp_set_object_terms($post_id, $pizza_cat_term_id, 'product_cat'); //Задаем категорию товара
			$this->product_to_stock($post_id, $data['stoks_terms']);
		}else{
			$post_id = $data['post_id'];
		}
		
		update_post_meta($post_id, 'virtual_variative_product', $data['product_name']); //Артикул		
		update_post_meta($post_id, '_sku', 'virtual_variative_product'.$post_id); //Артикул
		update_post_meta( $post_id, '_visibility', 'visible' ); // Видимость: открыто
		//update_post_meta( $post_id, 'total_sales', '0');   //Создается произвольное поле
		update_post_meta( $post_id, '_downloadable', 'no'); //Не скачиваемый


		update_post_meta( $post_id, '_regular_price', $data['price']); //Базовая цена
		update_post_meta( $post_id, '_stock_status', 'instock'); 
		update_post_meta( $post_id, 'stock_status', 'instock'); 
		wp_set_object_terms($post_id, "variable", 'product_type');



		//update_post_meta( $post_id, '_sale_price', 500); //Цена распродажи

		/* Склады и остатки */
		
			
		return $post_id;
	}

	public function product_to_stock( $productId, $stoks_terms, $count = 99 ) {
		$count = 99;
		 /* Отвязываем */
		foreach($stoks_terms as $st){
			wp_remove_object_terms( $productId, $st->term_id, 'location' );
			delete_post_meta($productId, '_stock_at_' . $st->term_id);
			delete_post_meta($productId, '_stock_status-' . $st->term_id);
		}
		
		/* Сновы привязываем */
		$terms = [];
		update_post_meta($productId, '_stock_at_' . intval($st->term_id), $count);
		update_post_meta( $post_id, '_stock_status', 'instock' );
		//foreach($stockData['_separate_warehouse_stock'] as $_stock => $_count){
		foreach($stoks_terms as $st){

				$terms[]=intval($st->term_id);
				//SlwProductHelper::update_wc_stock_status( $id, array_sum($input_amounts) );
				update_post_meta($productId, '_stock_at_' . intval($st->term_id), $count);
				update_post_meta($productId, '_stock_status-' . intval($st->term_id), $count ? 1 : 0);     
			
		   // exit;
		}
		if(count($terms)){
			wp_set_object_terms( $productId, $terms, 'location' );
		}

		return false;
	}

	/**
	 * Import nomenclature (groups and products) to WooCommerce.
	 *
	 * @return array
	 */
	public function import_nomenclature( $param_groups = array() ) {

		// Import iiko groups (WC product categories).
		$processed_groups = $this->import_groups( $param_groups );

		// TODO - return errors on bad checking.
		$this->check_array( $processed_groups, 'No imported groups.' );

		// Import iiko dishes and goods (WC products).
		$processed_products = $this->import_products( $processed_groups );

		return array(
			'importedGroups'   => count( $processed_groups ),
			'importedProducts' => $processed_products,
		);
	}

	public function import_update_modifier($zero_price, $modifier_info = array(), $term_id, $stock ) {
		if($zero_price)
			$modifier_info['price'] = 0;
		$modifier_insert_data = Import::insert_update_product( $modifier_info, $term_id, [], [], [], $stock );
		//$updated_modifiers[$mod['id']] = $modifier_insert_data;
		$modifier_id = $modifier_insert_data;

		return $modifier_insert_data;
	}

	public function import_products_all_terminals( ) { 

		//шава
		//$this->import_products( [ 86 => 'b7a6e82a-ecb6-49d6-9440-b7acbd544f94' ] );
		
		//Пиццы 30 см
		//$this->import_products( [ 162 => 'f65a5e6a-53e0-413f-aabd-9cf6f0283f4a'  ] );

		//Пиццы 40 см
		//$this->import_products( [ 166 => '9a18fd03-ee76-4405-b038-149de863db96'  ] );


		//Поке
		$this->import_products( [ 170 => 'af8a960f-e471-48c8-9356-b88554e338c7' ] );

		//лапша WOK
		//$this->import_products( [ 165 => '78227033-a0c3-4bb1-b507-971d627b8580' ] );

		//Сендвичи
		//$this->import_products( [ 139 => '52b72a27-6d65-448e-bc8b-ba9a60d6d68f']);
		die(111);

	}
}