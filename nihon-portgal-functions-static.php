<?php

class nihon_portgal_functions_static {
	
	public static function ReadFromCache($main, $begin) {
		global	$wpdb;
		if($main[0]['advanced_usecache']=='0') {
			return	array( 'havecache' => false );
		}
		$sql_query	=	" 
		DELETE 
		FROM	`".$wpdb->prefix."nihon_portgal_cache` 
		WHERE	`parentid` = '".$main[0]['id']."' AND `datetimemake` <= '".date("Y-m-d H:i:s", time()-$main[0]['advanced_cache_max_time'])."' 
		";
		$wpdb->query($sql_query);
		$sql_query	=	" SELECT `data` FROM `".$wpdb->prefix."nihon_portgal_cache` WHERE `parentid` = '".$main[0]['id']."' AND `beginfrom` = '".$begin."' LIMIT 1 ";
		$dane		=	$wpdb->get_results($sql_query, ARRAY_A);
		if(is_array($dane) and count($dane)) {
			return	array( 'havecache' => true , 'data' => $dane[0]['data'] );
		}
		return	array( 'havecache' => false );
	}
	
	public static function parse_query($var) {
		$var  = parse_url($var, PHP_URL_QUERY);
		$var  = html_entity_decode($var);
		$var  = explode('&', $var);
		$arr  = array();

		foreach($var as $val)
		{
		  $x          = explode('=', $val);
		  $arr[$x[0]] = $x[1];
		}
		unset($val, $x, $var);
		return $arr;
	}
	
	public static function PrepareData4Send_GetItemsHtml($main, $beginfrom, $addclassnewitems=false) {
		
		$items		=	nihon_portgal_functions_static::GetItemsFor($main[0], $beginfrom);
		$havemore	=	$items['havemore'];
		//	POBRANIE NAZW KATEGORII -- START
		$own_names_cats	=	nihon_portgal_functions_static::PrepareData4Send_GetNamesOfCategory($items['relations'], $main[0]['sourcedata']);
		//	POBRANIE NAZW KATEGORII -- KONIEC
		
		//	POBRANIE NAZW UŻYTKOWNIKOW -- BEGIN
		$users_names_ar	=	nihon_portgal_functions_static::PrepareData4Send_GetNamesOfUsersFromItems($items['items']);
		//	POBRANIE NAZW UŻYTKOWNIKOW -- END
		
		$sortby_conf	=	unserialize($main[0]['navigation_sortby_conf']);
		
		$available_sort	=	array();
		foreach($sortby_conf as $key => $sortby_conf_item) {
			if($sortby_conf_item['show']=='1') {
				$available_sort[$key]	=	$key;
			}
		}
		
		
		$html_items	=	'';
		foreach($items['items'] as $item) {
			
			$item['woocommerce_id']	=	$item['id'];
			if($main[0]['sourcedata']=='0') {
				$item['woocommerce_id']	=	$item['woocommerceproductid'];
			}
			
			$author_name	=	'';
			if(isset($users_names_ar[$item['author']])) {
				$author_name	=	$users_names_ar[$item['author']];
			}
			
			$categoryset	=	array();
			if(isset($items['relations'][$item['id']])) {
				$categoryset	=	$items['relations'][$item['id']];
			}
			
			$categorylinkset	=	array();
			foreach($categoryset as $categoryset_item) {
				if(isset($own_names_cats[$categoryset_item])) {
					if(in_array($own_names_cats[$categoryset_item]['taxonomy'], array('product_cat' , 'category'))) {
						$categorylinkset[$own_names_cats[$categoryset_item]['href']]	=	$own_names_cats[$categoryset_item]['name'];
					}
				}
			}
			
			$set_order	=	array();
			$xset		=	0;
			
			//	ZROBIĆ POBIERANIE CENY RAITINGU i DOBRY URL W SKINIE + PRZEGLĄD SKINÓW
			
			
			foreach($available_sort as $av_sort) {
				$xset++;
				switch($av_sort) {
					case '0':	//	Title
						$set_order[$xset]	=	$item['title'];
					break;
					case '1':	//	Date publish
						$set_order[$xset]	=	strtotime($item['dateymd']);
					break;
					case '2':	//	Price
						if(!isset($item['price_value']) or (isset($item['price_value']) and $item['price_value']=='')) {
							$item['price_value']	=	0;
						}
						$set_order[$xset]	=	str_replace('.', '', str_replace(',', '', $item['price_value']));
					break;
					case '3':	//	Raiting
						if(!isset($item['raiting']) or (isset($item['raiting']) and $item['raiting']=='')) {
							$item['raiting']	=	0;
						}
						$set_order[$xset]	=	str_replace('.', '', str_replace(',', '', $item['raiting']));
						// $set_order[$xset]	=	$item['raiting'];
					break;
				}
			}
					
			
			$havevideo	=	false;
			$setvideotype	=	'';
			$setvideourls	=	array();
			if(isset($item['youtubeurl']) and $item['youtubeurl']!='') {
				$setvideotype	=	'youtube';
				$r	=	self::parse_query($item['youtubeurl']);
				if(isset($r['v']) and $r['v']!='') {
					$item['youtubeurl']	=	'https://www.youtube.com/embed/'.$r['v'];
				}
				$setvideourls	=	array($item['youtubeurl']);
			} else {
				if(isset($item['vimmeourl']) and $item['vimmeourl']!='') {
					$setvideotype	=	'vimmeo';
					$setvideourls	=	array($item['vimmeourl']);
				} else {
					$setvideotype	=	'html5';
					foreach(array( 'mp4file' => 'mp4' , 'webmfile' => 'webm' , 'oggfile' => 'ogg' ) as $key => $setkey) {
						if(isset($item[$key]) and $item[$key]!='') {
							$setvideourls[$setkey]	=	$item[$key];
						}
					}
				}
			}
			
			//	image_imdata
			//	secondimage_imdata
			//	alternativeimage_imdata
			//	videothumbanil_imdata
			
			$thumb_noimage	=	true;
			$thumb1	=	plugin_dir_url(__FILE__).'/out/noimage.png';
			$thumb2	=	'';
			$full1	=	plugin_dir_url(__FILE__).'/out/noimagefull.png';
			
			$arrcheck	=	array( );
			switch($main[0]['dlanimation_type']) {
				case '18':
				case '17':
				case '16':
					array_push($arrcheck, 'videothumbanil_imdata');
				break;
			}
			
			array_push($arrcheck, 'alternativeimage_imdata');
			array_push($arrcheck, 'image_imdata');
			foreach($arrcheck as $check) {
				if(isset($item[$check]) and is_array($item[$check]) and count($item[$check])) {
					$thumb_noimage	=	false;
					$thumb1		=	$item[$check]['thumb'];
					$full1		=	$item[$check]['large'];
				}
			}
			
			if(isset($item['secondimage_imdata']) and is_array($item['secondimage_imdata'])) {
				$thumb2	=	$item['secondimage_imdata']['thumb'];	// ??????????????? Może second thumb mniejszy od rozmiaru maksymalnego
			}
			
			$item_set	=	array(
				'title'		=>	$item['title'],
				'description'	=>	$item['description'],
				'thumb'		=>	$thumb1, // 'http://localhost/patti/wp-content/uploads/2014/12/8.jpg',
				'thumb_noimage'	=>	$thumb_noimage, 
				'thumb_second'	=>	$thumb2, // 'http://localhost/untitled.png',
				'fullimage'	=>	$full1, // 'http://www.joomlaworks.net/images/documentation/SIGPro_Editor_XTD_Button.jpg',
				'category'		=>	$categoryset,
				'category_links'	=>	$categorylinkset,
				'dateymd'	=>	substr($item['dateymd'], 0, 10),
				'author'	=>	$author_name,
				'href'		=>	$item['url'],
				'order'		=>	$set_order,
				// 'videourls'		=>	array( 'http://player.vimeo.com/video/92112616?color=bc9b6a&title=0&byline=0&portrait=0' ), // for vimmeo youtube
				'videourls'		=>	$setvideourls,
				'videtype'		=>	$setvideotype, // vimmeo , youtube
				'dlbgcolor'	=>	$item['darklayerbackgroundcolor'] ,
				
				'price_label'	=>	$item['price_label'] ,
				'price_value'	=>	$item['price_value'] ,
				'raiting'	=>	$item['raiting'] ,
				'id'			=>	$item['id'] ,
				'woocommerce_id'	=>	$item['woocommerce_id']
			);
			
			
			
			
			
			$html_items	.=	nihon_portgal_functions_static::ShowInStructure($item_set, $main[0], array('addclassnewitems' => $addclassnewitems) );
			
		}
		
		return	array( 'items' => $items , 'html_items' => $html_items , 'havemore' => $havemore ) ;
	}
	
	public static function GetItemsFor_SUB($main, $getrelateds, $ad_limit_items) {
		
		global	$wpdb;
		
		
		$havenext		=	false;
		$items			=	array(); // Items to output
		
		$get_output_items_begin_from		=	0;
		$get_output_items_begin_from_pominieto	=	0;
		
		$get_begin	=	0;
		$get_limit	=	100;
		if($ad_limit_items<$get_limit) {
			$get_limit	=	$ad_limit_items;
		}
		
		while(true) {
			$breakall	=	false;
			$sql_query	=	" 
			SELECT 
				`".$wpdb->prefix."posts`.`ID` as `id` , 
				`".$wpdb->prefix."posts`.`post_author` ,
				`".$wpdb->prefix."posts`.`post_title` ,
				`".$wpdb->prefix."posts`.`post_content` ,
				`".$wpdb->prefix."posts`.`post_date` 
			FROM	`".$wpdb->prefix."posts` 
			INNER JOIN `".$wpdb->prefix."term_relationships` ON `".$wpdb->prefix."term_relationships`.`object_id` = `".$wpdb->prefix."posts`.`id`
			WHERE
				`".$wpdb->prefix."term_relationships`.`term_taxonomy_id` IN (".implode(' , ', $getrelateds).") AND 
				`".$wpdb->prefix."posts`.`post_status` = 'publish' 
			GROUP BY	`".$wpdb->prefix."posts`.`id`
			ORDER BY `".$wpdb->prefix."posts`.`ID`
			LIMIT	".$get_begin." , ".$get_limit;
			$relateditems	=	$wpdb->get_results($sql_query, ARRAY_A);
			
			if(count($relateditems)<$get_limit) {
				$breakall	=	true;
			}
			
			$get_begin	=	$get_begin + $get_limit;
			
			$wpisy		=	array();
			$array		=	array();
			foreach($relateditems as $k => $item) {
				array_push($wpisy, $item);
				array_push($array, $item['id']);
				unset($relateditems[$k]);
			}
			
			if(is_array($array) and count($array)) {
				
				/* relacje -- begin */
				$sql_query	=	" 
					SELECT	
						`object_id` as `oid` , 
						`term_taxonomy_id` as `tid`
					FROM	`".$wpdb->prefix."term_relationships` 
					WHERE	`object_id` IN (".implode(' , ', $array).") 
				";
				$objects	=	$wpdb->get_results($sql_query, ARRAY_A);
				
				foreach($objects as $item) {
					if(!isset($relations[$item['oid']])) {
						$relations[$item['oid']]	=	array();
					}
					array_push($relations[$item['oid']], $item['tid']);
				}
				/* relacje -- end */
				
				/* uzupełnienie o filmy -- begin */
				$sql_query	=	" 
					SELECT `post_id` , `meta_key` , `meta_value` 
					FROM `".$wpdb->prefix."postmeta` 
					WHERE  `post_id` IN (".implode(" , ", $array).") AND `meta_key` IN ('portgal-mp4url' , 'portgal-webm' , 'portgal-ogg' , 'portgal-vimmeo' , 'portgal-youtube' , 'portgal-secondlayer' , 'portgal-alternativeimage', 'portgal-videothumbanil', 'portgal-dl-bgcolor') 
				";
				$postmeta	=	$wpdb->get_results($sql_query, ARRAY_A);
				
				
				$arfromto	=	array(
					'portgal-mp4url'	=>	'mp4file' ,
					'portgal-webm'		=>	'webmfile' ,
					'portgal-ogg'		=>	'oggfile' ,
					'portgal-vimmeo'	=>	'vimmeourl' ,
					'portgal-youtube'	=>	'youtubeurl' ,
					
					'portgal-secondlayer'		=>	'secondimage' ,
					'portgal-alternativeimage'	=>	'alternativeimage' ,
					'portgal-videothumbanil'	=>	'videothumbanil' ,
					
					'portgal-dl-bgcolor'		=>	'darklayerbackgroundcolor'
				);
				
				$grouppostmeta	=	array();
				foreach($postmeta as $value) {
					if(!isset($grouppostmeta[$value['post_id']])) {
						$grouppostmeta[$value['post_id']]	=	array();
					}
					if(isset($arfromto[$value['meta_key']])) {
						$grouppostmeta[$value['post_id']][$arfromto[$value['meta_key']]]	=	$value['meta_value'];
					}
				}
				/* uzupełnienie o filmy -- start */
				
				
				$thumbs	=	array();
				if($main['sourcedata']=='2') { // woocommerce
					$sql_query	=	" SELECT `post_id`, `meta_value` FROM `".$wpdb->prefix."postmeta` WHERE `post_id` IN ( ".implode(' , ', $array)." ) AND `meta_key` = '_product_image_gallery' ";
					$thumbs		=	$wpdb->get_results($sql_query, ARRAY_A);
					foreach($thumbs as $k => $v) {
						$v['meta_value']	=	trim($v['meta_value']);
						$value	=	0;
						if(strlen($v['meta_value'])>0) {
							if(strlen($v['meta_value'])==1) {
								$value	=	$v['meta_value'];
							}
							if(strlen($v['meta_value'])>1) {
								$tt	=	explode(',', $v['meta_value']);
								if(isset($tt[0])) {
									$value	=	$tt[0];
								}
							}
						}
						if($value>0) {
							$thumbs[$k]['meta_value']	=	$value;
							unset($array[$v['post_id']]);
						} else {
							unset($thumbs[$k]);
						}
					}
				}
				$thumbs2	=	array();
				if(is_array($array) and count($array)) {
					$sql_query	=	" SELECT `post_id`, `meta_value` FROM `".$wpdb->prefix."postmeta` WHERE `post_id` IN ( ".implode(' , ', $array)." ) AND `meta_key` = '_thumbnail_id' ";
					$thumbs2	=	$wpdb->get_results($sql_query, ARRAY_A);
				}
				$thumbs		=	array_merge($thumbs, $thumbs2);
				$thumbbypostid	=	array();
				
				foreach($thumbs as $thumbs_item) {
					$thumbbypostid[$thumbs_item['post_id']]	=	$thumbs_item['meta_value'];
				}
				unset($thumbs);
				
				
				foreach($wpisy as $wpis) {
					$getitem	=	false;
					switch($main['postswith']) {
						case '0':
							$getitem	=	true;
						break;
						case '1':
						
							if( (isset($thumbbypostid[$wpis['id']]) and $thumbbypostid[$wpis['id']]!='') or (isset($grouppostmeta[$wpis['id']]['alternativeimage']) and $grouppostmeta[$wpis['id']]['alternativeimage']!='')) {
								$getitem	=	true;
							}
						break;
						case '2':
							foreach(array('youtubeurl', 'vimmeourl', 'mp4file' , 'webmfile', 'oggfile') as $tt) {
								if(isset($grouppostmeta[$wpis['id']][$tt]) and $grouppostmeta[$wpis['id']][$tt]!='') {
									$getitem	=	true;
									break;
								}
							}
						break;
						case '3':
							if( (isset($thumbbypostid[$wpis['id']]) and $thumbbypostid[$wpis['id']]!='') or (isset($grouppostmeta[$wpis['id']]['alternativeimage']) and $grouppostmeta[$wpis['id']]['alternativeimage']!='')) {
								foreach(array('youtubeurl', 'vimmeourl', 'mp4file' , 'webmfile', 'oggfile') as $tt) {
									if(isset($grouppostmeta[$wpis['id']][$tt]) and $grouppostmeta[$wpis['id']][$tt]!='') {
										$getitem	=	true;
										break;
									}
								}
							}
						break;
					}
					
					if($getitem) {
						
						if($get_output_items_begin_from_pominieto<$get_output_items_begin_from) {
							$get_output_items_begin_from_pominieto++;
							continue;
						}
						
						
						if(count($items)==$ad_limit_items) {
							$breakall	=	true;
							$havenext	=	true;
							break;
						}
						
						
						$price_label		=	0;
						$price_value		=	0;
						$raiting		=	0;
						
						if($main['sourcedata']=='2') {
							$product	= new WC_Product($wpis['id']);
							$raiting		=	$product->get_average_rating();
							$set_raiting	=	0;
							$t_array	=	array(
								'0.25'	=>	0.5,
								'0.75'	=>	1,
								'1.25'	=>	1.5,
								'1.75'	=>	2,
								'2.25'	=>	2.5,
								'2.75'	=>	3,
								'3.25'	=>	3.5,
								'3.75'	=>	4,
								'4.25'	=>	4.5,
								'4.75'	=>	5
							);
							foreach($t_array as $k => $tv) {
								$k	=	$k * 1;
								if($raiting>$k) {
									$set_raiting	=	$tv;
								}
							}
							$raiting	=	$set_raiting;
							$price_label	=	$product->get_price_html();
							$price_value	=	$product->price;
							if($price_value=='') {
								$price_value	=	0;
							}
						}
						
						$set_mp4file	=	'';
						$set_webmfile	=	'';
						$set_oggfile	=	'';
						$set_vimmeourl	=	'';
						$set_youtubeurl	=	'';
						$set_secondimage	=	'';
						$set_alternativeimage	=	'';
						$set_videothumbanil	=	'';
						$set_darklayerbackgroundcolor	=	'';
						
						if(isset($grouppostmeta[$wpis['id']]) and is_array($grouppostmeta[$wpis['id']]) and count($grouppostmeta[$wpis['id']])) {
							foreach($grouppostmeta[$wpis['id']] as $tkd => $tkv) {
								$tkdd	=	'set_'.$tkd;
								$$tkdd	=	$tkv;
							}
						}
						
						$imageid	=	'';
						if(isset($thumbbypostid[$wpis['id']])) {
							$imageid	=	$thumbbypostid[$wpis['id']];
						}
						
						$wpis['post_content']	=	strip_tags($wpis['post_content']);
						$wpis['post_content']	=	wp_trim_words($wpis['post_content'], 25);
						
						$url			=	get_permalink( $wpis['id'] );
						array_push($items, array(
							'url'			=>	$url,
							'id'			=>	$wpis['id'],
							'title'			=>	$wpis['post_title'],
							'description'		=>	$wpis['post_content'],
							'image'			=>	$imageid,
							'author'		=>	$wpis['post_author'],
							'dateymd'		=>	$wpis['post_date'],
							
							'mp4file'	=>	$set_mp4file,
							'webmfile'	=>	$set_webmfile,
							'oggfile'	=>	$set_oggfile,
							'vimmeourl'	=>	$set_vimmeourl,
							'youtubeurl'	=>	$set_youtubeurl,
							
							'secondimage'		=>	$set_secondimage,
							'alternativeimage'	=>	$set_alternativeimage,
							'videothumbanil'	=>	$set_videothumbanil,
							
							'darklayerbackgroundcolor'	=>	$set_darklayerbackgroundcolor,
							
							'raiting'		=>	$set_raiting,
							'price_label'		=>	$price_label,
							'price_value'		=>	$price_value
						));
						
						
						
						
					}
					
				}
				
				
			}
			
			if($breakall) {
				break;
			}
			
			
		}
		
		$relationsout	=	array();
		$ids		=	array();
		foreach($items as $item) {
			array_push($ids, $item['id']);
		}
		if(is_array($ids) and count($ids)) {
			$sql_query	=	" SELECT `object_id` as `i` , `term_taxonomy_id` as `tid` FROM `".$wpdb->prefix."term_relationships` WHERE `object_id` IN (".implode(' , ', $ids).") AND `term_taxonomy_id` IN (".implode(' , ' , $getrelateds).") ";
			$rel		=	$wpdb->get_results($sql_query, ARRAY_A);
			foreach($rel as $rel_item) {
				if(!isset($relationsout[$rel_item['i']])) {
					$relationsout[$rel_item['i']]	=	array();
				}
				array_push( $relationsout[$rel_item['i']] , $rel_item['tid']);
			}
		}
		
		return	array(
			'items'		=>	$items,
			'havenext'	=>	$havenext,
			'relations'	=>	$relationsout
		);
		
		
		
		
	}
	
	public static function GetItemsFor_SUB__old($main, $getrelateds, $ad_limit_items) {
		
		global	$wpdb;
		
		
		$havenext		=	false;
		$items			=	array(); // Items to output
		// $ad_limit_items	=	1;
		
		$get_output_items_begin_from		=	0;
		$get_output_items_begin_from_pominieto	=	0;
		
		$get_begin	=	0;
		$get_limit	=	100;
		if($ad_limit_items<$get_limit) {
			$get_limit	=	$ad_limit_items;
		}
		
		// if($get_limit<10) { $get_limit = 10; }
		
		while(true) {
			$breakall	=	false;
			$sql_query	=	" 
			SELECT 
				`".$wpdb->prefix."posts`.`ID` as `id` , 
				`".$wpdb->prefix."posts`.`post_author` ,
				`".$wpdb->prefix."posts`.`post_title` ,
				`".$wpdb->prefix."posts`.`post_content` ,
				`".$wpdb->prefix."posts`.`post_date` 
			FROM	`".$wpdb->prefix."posts` 
			INNER JOIN `".$wpdb->prefix."term_relationships` ON `".$wpdb->prefix."term_relationships`.`object_id` = `".$wpdb->prefix."posts`.`id`
			WHERE
				`".$wpdb->prefix."term_relationships`.`term_taxonomy_id` IN (".implode(' , ', $getrelateds).") AND 
				`".$wpdb->prefix."posts`.`post_status` = 'publish' 
			GROUP BY	`".$wpdb->prefix."posts`.`id`
			ORDER BY `".$wpdb->prefix."posts`.`ID`
			LIMIT	".$get_begin." , ".$get_limit;
			$relateditems	=	$wpdb->get_results($sql_query, ARRAY_A);
			
			if(count($relateditems)<$get_limit) {
				$breakall	=	true;
			}
			
			$get_begin	=	$get_begin + $get_limit;
			
			$wpisy		=	array();
			$array		=	array();
			foreach($relateditems as $k => $item) {
				array_push($wpisy, $item);
				array_push($array, $item['id']);
				unset($relateditems[$k]);
			}
			
			
				
			
			if(is_array($array) and count($array)) {
				
				$sql_query	=	" 
					SELECT	
						`object_id` as `oid` , 
						`term_taxonomy_id` as `tid`
					FROM	`".$wpdb->prefix."term_relationships` 
					WHERE	`object_id` IN (".implode(' , ', $array).") 
				";
				$objects	=	$wpdb->get_results($sql_query, ARRAY_A);
				
				foreach($objects as $item) {
					if(!isset($relations[$item['oid']])) {
						$relations[$item['oid']]	=	array();
					}
					array_push($relations[$item['oid']], $item['tid']);
				}
				
				$array	=	array();
				foreach($wpisy as $wpis) {
					$array[$wpis['id']]	=	$wpis['id'];
				}
				
				if(is_array($array) and count($array)) {
					
					// TUTAJ SKOŃCZYŁEM UWZGLĘDNIANIE	postswith
					
					
					
					$thumbs	=	array();
					if($main['sourcedata']=='2') { // woocommerce
						$sql_query	=	" SELECT `post_id`, `meta_value` FROM `".$wpdb->prefix."postmeta` WHERE `post_id` IN ( ".implode(' , ', $array)." ) AND `meta_key` = '_product_image_gallery' ";
						$thumbs		=	$wpdb->get_results($sql_query, ARRAY_A);
						foreach($thumbs as $k => $v) {
							$v['meta_value']	=	trim($v['meta_value']);
							$value	=	0;
							if(strlen($v['meta_value'])>0) {
								if(strlen($v['meta_value'])==1) {
									$value	=	$v['meta_value'];
								}
								if(strlen($v['meta_value'])>1) {
									$tt	=	explode(',', $v['meta_value']);
									if(isset($tt[0])) {
										$value	=	$tt[0];
									}
								}
							}
							if($value>0) {
								$thumbs[$k]['meta_value']	=	$value;
								unset($array[$v['post_id']]);
							} else {
								unset($thumbs[$k]);
							}
						}
					}
					$thumbs2	=	array();
					if(is_array($array) and count($array)) {
						$sql_query	=	" SELECT `post_id`, `meta_value` FROM `".$wpdb->prefix."postmeta` WHERE `post_id` IN ( ".implode(' , ', $array)." ) AND `meta_key` = '_thumbnail_id' ";
						$thumbs2	=	$wpdb->get_results($sql_query, ARRAY_A);
					}
					$thumbs		=	array_merge($thumbs, $thumbs2);
					$array		=	array();
					foreach($thumbs as $thumb) {
						
						if($get_output_items_begin_from_pominieto<$get_output_items_begin_from) {
							$get_output_items_begin_from_pominieto++;
							continue;
						}
						
						
						
						$url			=	get_permalink( $thumb['post_id'] );
						$title			=	'';
						$post_description	=	'';
						$post_author		=	'';
						$post_date		=	'';
						
						foreach($wpisy as $wpis) {
							if($wpis['id']==$thumb['post_id']) {
								$title			=	$wpis['post_title'];
								$post_description	=	$wpis['post_content'];
								$post_author		=	$wpis['post_author'];
								$post_date		=	$wpis['post_date'];
								break;
							}
						}
						
						if(count($items)==$ad_limit_items) {
							// and have nexit
							$breakall	=	true;
							$havenext	=	true;
							break;
						}
						
						$post_description	=	strip_tags($post_description);
						$post_description	=	wp_trim_words($post_description, 25);
						
						
						
						
						array_push($items, array(
							'url'			=>	$url,
							'id'			=>	$thumb['post_id'],
							'title'			=>	$title,
							'description'		=>	$post_description,
							'image'			=>	$thumb['meta_value'],
							'author'		=>	$post_author,
							'dateymd'		=>	$post_date,
							
						
						));
						
					}
					
					
				}
				
				
			}
			
			if($breakall) {
				break;
			}
			
			
		}
		
		print_r($items); exit;
		
		$relationsout	=	array();
		$ids		=	array();
		foreach($items as $item) {
			array_push($ids, $item['id']);
		}
		if(is_array($ids) and count($ids)) {
			$sql_query	=	" SELECT `object_id` as `i` , `term_taxonomy_id` as `tid` FROM `".$wpdb->prefix."term_relationships` WHERE `object_id` IN (".implode(' , ', $ids).") AND `term_taxonomy_id` IN (".implode(' , ' , $getrelateds).") ";
			$rel		=	$wpdb->get_results($sql_query, ARRAY_A);
			foreach($rel as $rel_item) {
				if(!isset($relationsout[$rel_item['i']])) {
					$relationsout[$rel_item['i']]	=	array();
				}
				array_push( $relationsout[$rel_item['i']] , $rel_item['tid']);
			}
		}
		
		return	array(
			'items'		=>	$items,
			'havenext'	=>	$havenext,
			'relations'	=>	$relationsout
		);
		
		
		
		/*
						$price_label		=	0;
						$price_value		=	0;
						$raiting		=	0;
						
						if($main['sourcedata']=='2') {
							
							$product	= new WC_Product($item['id']);
							$raiting		=	$product->get_average_rating();
							$set_raiting	=	0;
							$t_array	=	array(
								'0.25'	=>	0.5,
								'0.75'	=>	1,
								'1.25'	=>	1.5,
								'1.75'	=>	2,
								'2.25'	=>	2.5,
								'2.75'	=>	3,
								'3.25'	=>	3.5,
								'3.75'	=>	4,
								'4.25'	=>	4.5,
								'4.75'	=>	5
							);
							foreach($t_array as $k => $tv) {
								$k	=	$k * 1;
								if($raiting>$k) {
									$set_raiting	=	$tv;
								}
							}
							$raiting	=	$set_raiting;
							$price_label	=	$product->get_price_html();
							$price_value	=	$product->price;
							if($price_value=='') {
								$price_value	=	0;
							}
							
						}
						
							'raiting'		=>	$set_raiting,
							'price_label'		=>	$price_label,
							'price_value'		=>	$price_value
						
						*/
		
		
		
		/*
		echo	' -- ['.$havenext.'] -- ';
		print_r($items);
		exit;
		*/
	}
	
	public static function GetItemsFor($main, $begin) {
		global	$wpdb;
		
		$have_more	=	true;
		$ad_limit_items	=	'99999999999';
		if($main['navigation_pagination_more']!='0') {
			$ad_limit_items	=	self::GetValueFromVA(self::GetPagination(), $main['navigation_pagination_more']);
		} else {
			$have_more	=	false;
		}
		
		$sql_query	=	" SELECT * FROM `".$wpdb->prefix."nihon_portgal_categories` WHERE `parent_id` = '".$main['id']."' ORDER BY `lp` ASC ";
		$categories	=	$wpdb->get_results($sql_query, ARRAY_A);
		
		switch($main['sourcedata']) {
			case '0':
				// own items
				$sql_query	=	"SELECT * FROM `".$wpdb->prefix."nihon_portgal_items` WHERE `parentid` = '".$main['id']."' ORDER BY `lp` ASC LIMIT ".$begin." , ".$ad_limit_items;
				$items		=	$wpdb->get_results($sql_query, ARRAY_A);
				foreach($items as $key => $value) {
					$value['alternativeimage']	=	$value['image'];
					$value['videothumbanil']	=	$value['image'];
				}
				$relations	=	array();
				foreach($categories as $category) {
					$sql_query	=	" SELECT * FROM `".$wpdb->prefix."nihon_portgal_categories_relations` WHERE `category_id` = '".$category['id']."' ";
					$relation	=	$wpdb->get_results($sql_query, ARRAY_A);
					foreach($relation as $item) {
						if(!isset($relations[$item['item_id']])) {
							$relations[$item['item_id']]	=	array();
						}
						array_push($relations[$item['item_id']], $item['category_id']);
					}
				}
				if($have_more) {
					$sql_query	=	"SELECT `id` FROM `".$wpdb->prefix."nihon_portgal_items` WHERE `parentid` = '".$main['id']."' LIMIT ".( $begin + $ad_limit_items )." , 1";
					$tar		=	$wpdb->get_results($sql_query, ARRAY_A);
					if(is_array($tar) and count($tar)) {
						$have_more	=	true;
					} else {
						$have_more	=	false;
					}
				}
			break;
			case '1':
			case '2':
				
				$relations	=	array();
				$items		=	array();
				
				$getrelateds	=	array();
				foreach($categories as $categorie) {
					array_push($getrelateds, $categorie['name']);
				}
				
				$getrelateds	=	array_unique($getrelateds);
				if(is_array($getrelateds) and count($getrelateds)) {
					
					//	$ad_limit_items	- ile ma mieć danych
					
					
					$sql_query	=	" 
					SELECT	
						`".$wpdb->prefix."term_taxonomy`.`term_taxonomy_id` as `id` , 
						`".$wpdb->prefix."terms`.`name`
					FROM	`".$wpdb->prefix."term_taxonomy` 
					INNER JOIN `".$wpdb->prefix."terms` ON `".$wpdb->prefix."term_taxonomy`.`term_id` = `".$wpdb->prefix."terms`.`term_id`
					WHERE	`".$wpdb->prefix."term_taxonomy`.`term_taxonomy_id` IN (".implode(" , ", $getrelateds).") ";
					$categories	=	$wpdb->get_results($sql_query, ARRAY_A);
					
					$tar	=	self::GetItemsFor_SUB($main, $getrelateds, $ad_limit_items);
					$relations	=	$tar['relations'];
					$items		=	$tar['items'];
					$have_more	=	$tar['havenext'];
					
					
					/*
					if($have_more) {
						$ad_limit_items++;
					}
					
					$sql_query	=	" 
					SELECT	
						`".$wpdb->prefix."term_taxonomy`.`term_taxonomy_id` as `id` , 
						`".$wpdb->prefix."terms`.`name`
					FROM	`".$wpdb->prefix."term_taxonomy` 
					INNER JOIN `".$wpdb->prefix."terms` ON `".$wpdb->prefix."term_taxonomy`.`term_id` = `".$wpdb->prefix."terms`.`term_id`
					WHERE	`".$wpdb->prefix."term_taxonomy`.`term_taxonomy_id` IN (".implode(" , ", $getrelateds).") ";
					$categories	=	$wpdb->get_results($sql_query, ARRAY_A);
					
					$sql_query	=	" 
					SELECT 
						`".$wpdb->prefix."posts`.`ID` as `id` , 
						`".$wpdb->prefix."posts`.`post_author` ,
						`".$wpdb->prefix."posts`.`post_title` ,
						`".$wpdb->prefix."posts`.`post_content` ,
						`".$wpdb->prefix."posts`.`post_date` 
					FROM	`".$wpdb->prefix."posts` 
					INNER JOIN `".$wpdb->prefix."term_relationships` ON `".$wpdb->prefix."term_relationships`.`object_id` = `".$wpdb->prefix."posts`.`id`
					WHERE
						`".$wpdb->prefix."term_relationships`.`term_taxonomy_id` IN (".implode(' , ', $getrelateds).") AND 
						`".$wpdb->prefix."posts`.`post_status` = 'publish' 
					GROUP BY	`".$wpdb->prefix."posts`.`id`
					ORDER BY `".$wpdb->prefix."posts`.`ID`
					LIMIT	".$begin." , ".$ad_limit_items;
					
					$relateditems	=	$wpdb->get_results($sql_query, ARRAY_A);
					
					if($have_more) {
						if(count($relateditems)==$ad_limit_items) {
							$have_more	=	true;
							unset($relateditems[$ad_limit_items-1]);
						} else {
							$have_more	=	false;
						}
					}
					
					$wpisy		=	array();
					$array		=	array();
					foreach($relateditems as $k => $item) {
						array_push($wpisy, $item);
						array_push($array, $item['id']);
						unset($relateditems[$k]);
					}
					
					if(is_array($array) and count($array)) {
						
						$sql_query	=	" 
							SELECT	
								`object_id` as `oid` , 
								`term_taxonomy_id` as `tid`
							FROM	`".$wpdb->prefix."term_relationships` 
							WHERE	`object_id` IN (".implode(' , ', $array).") 
						";
						$objects	=	$wpdb->get_results($sql_query, ARRAY_A);
						
						foreach($objects as $item) {
							if(!isset($relations[$item['oid']])) {
								$relations[$item['oid']]	=	array();
							}
							array_push($relations[$item['oid']], $item['tid']);
						}
						
						$array	=	array();
						foreach($wpisy as $wpis) {
							$array[$wpis['id']]	=	$wpis['id'];
						}
						
						if(is_array($array) and count($array)) {
							$thumbs	=	array();
							if($main['sourcedata']=='2') { // woocommerce
								$sql_query	=	" SELECT `post_id`, `meta_value` FROM `".$wpdb->prefix."postmeta` WHERE `post_id` IN ( ".implode(' , ', $array)." ) AND `meta_key` = '_product_image_gallery' ";
								$thumbs		=	$wpdb->get_results($sql_query, ARRAY_A);
								foreach($thumbs as $k => $v) {
									$v['meta_value']	=	trim($v['meta_value']);
									$value	=	0;
									if(strlen($v['meta_value'])>0) {
										if(strlen($v['meta_value'])==1) {
											$value	=	$v['meta_value'];
										}
										if(strlen($v['meta_value'])>1) {
											$tt	=	explode(',', $v['meta_value']);
											if(isset($tt[0])) {
												$value	=	$tt[0];
											}
										}
									}
									if($value>0) {
										$thumbs[$k]['meta_value']	=	$value;
										unset($array[$v['post_id']]);
									} else {
										unset($thumbs[$k]);
									}
								}
							}
							$thumbs2	=	array();
							if(is_array($array) and count($array)) {
								$sql_query	=	" SELECT `post_id`, `meta_value` FROM `".$wpdb->prefix."postmeta` WHERE `post_id` IN ( ".implode(' , ', $array)." ) AND `meta_key` = '_thumbnail_id' ";
								$thumbs2	=	$wpdb->get_results($sql_query, ARRAY_A);
							}
							$thumbs		=	array_merge($thumbs, $thumbs2);
							$array		=	array();
							foreach($thumbs as $thumb) {
								$url			=	get_permalink( $thumb['post_id'] );
								$title			=	'';
								$post_description	=	'';
								$post_author		=	'';
								$post_date		=	'';
								
								foreach($wpisy as $wpis) {
									if($wpis['id']==$thumb['post_id']) {
										$title			=	$wpis['post_title'];
										$post_description	=	$wpis['post_content'];
										$post_author		=	$wpis['post_author'];
										$post_date		=	$wpis['post_date'];
										break;
									}
								}
								
								$price_woocommerce	=	0;
								$raiting_woocommerce	=	0;
								
								//	$price_woocommerce
								if($main['sourcedata']=='2') {
									$price_woocommerce	=	get_post_meta( $thumb['post_id'], '_price', true);
								}
								
								array_push($items, array(
									'url'			=>	$url,
									'id'			=>	$thumb['post_id'],
									'title'			=>	$title,
									'description'		=>	$post_description,
									'image'			=>	$thumb['meta_value'],
									'author'		=>	$post_author,
									'dateymd'		=>	$post_date,
									'price_value'		=>	$price_woocommerce,
									'raiting'		=>	$raiting_woocommerce,
								));
								
							}
						}
						
					}
					*/
					
					
					
					
					
					
					
					
					
					
					
					
				} else {
					$categories	=	array();
				}
				
				$items_id	=	array();
				foreach($items as $item) {
					array_push($items_id, $item['id']);
				}
				
				//	
				$sql_query	=	" 
					SELECT `post_id` , `meta_key` , `meta_value` 
					FROM `".$wpdb->prefix."postmeta` 
					WHERE  `post_id` IN (".implode(" , ", $items_id).") AND `meta_key` IN ('portgal-mp4url' , 'portgal-webm' , 'portgal-ogg' , 'portgal-vimmeo' , 'portgal-youtube' , 'portgal-secondlayer' , 'portgal-alternativeimage', 'portgal-videothumbanil', 'portgal-dl-bgcolor') 
				";
				$postmeta	=	$wpdb->get_results($sql_query, ARRAY_A);
				
				
				$arfromto	=	array(
					'portgal-mp4url'	=>	'mp4file' ,
					'portgal-webm'		=>	'webmfile' ,
					'portgal-ogg'		=>	'oggfile' ,
					'portgal-vimmeo'	=>	'vimmeourl' ,
					'portgal-youtube'	=>	'youtubeurl' ,
					'portgal-secondlayer'	=>	'secondimage' ,
					'portgal-alternativeimage'	=>	'alternativeimage' ,
					'portgal-videothumbanil'	=>	'videothumbanil' ,
					'portgal-dl-bgcolor'	=>	'darklayerbackgroundcolor'
				);
				
				$grouppostmeta	=	array();
				foreach($postmeta as $value) {
					if(!isset($grouppostmeta[$value['post_id']])) {
						$grouppostmeta[$value['post_id']]	=	array();
					}
					if(isset($arfromto[$value['meta_key']])) {
						$grouppostmeta[$value['post_id']][$arfromto[$value['meta_key']]]	=	$value['meta_value'];
					}
				}
				
				foreach($items as $key => $item) {
					if(isset($grouppostmeta[$item['id']])) {
						$item	=	array_merge($item , $grouppostmeta[$item['id']]);
					}
					foreach($arfromto as $keyset) {
						if(!isset($item[$keyset])) {
							$item[$keyset]	=	'';
						}
					}
					$items[$key]	=	$item;
				}
				
				
				
/*
`id`
`parentid`
`url`
`title`
`description`
`image`
`secondimage`
`youtubeurl`
`vimmeourl`
`oggfile`
`webmfile`
`mp4file`
`darklayerbackgroundcolor`
`price_label`
`price_value`
`raiting`
`lp`
*/
				
			break;
		}
		
		$array_image_id	=	array();
		$keyim		=	array('image', 'secondimage', 'alternativeimage', 'videothumbanil');
		foreach($items as $item) {
			foreach($keyim as $keyi) {
				if(isset($item[$keyi]) and $item[$keyi]!='') {
					array_push( $array_image_id , $item[$keyi] );
				}
			}
		}
		
		$max_width	=	400;
		if($main['advanced_max_widths']!='') {
			$temp		=	unserialize($main['advanced_max_widths']);
			if(is_array($temp) and count($temp)) {
				$max_width	=	max($temp);
			}
		}
		
		$images		=	self::GetImages($array_image_id, $max_width);
		
		foreach($items as $keyitem => $item) {
			foreach($keyim as $keyi) {
				if(isset($item[$keyi]) and $item[$keyi]!='') {
					if(isset($images[$item[$keyi]])) {
						$items[$keyitem][$keyi."_imdata"]	=	$images[$item[$keyi]];
					}
				}
			}
		}
		
		return	array(
			'havemore'	=>	$have_more,
			'relations'	=>	$relations ,
			'categories'	=>	$categories ,
			'items'		=>	$items
		);
	}
	
	public static function PrepareData4Send_GetNamesOfCategory($relations, $sourcedata) {
		global	$wpdb;
		//	POBRANIE NAZW KATEGORII -- START
		$own_names_cats	=	array();
		$category	=	array();
		foreach($relations as $relations_categories) {
			foreach($relations_categories as $relations_categories_item) {
				array_push( $category , $relations_categories_item );
			}
			$category	=	array_unique($category);
		}
		if(is_array($category) and count($category)) {
			$sql_query	=	" SELECT `term_taxonomy_id`, `term_id`, `taxonomy` FROM `".$wpdb->prefix."term_taxonomy` WHERE `term_taxonomy_id` IN (".implode( " , ", $category ).") ";
			$itemscategory	=	$wpdb->get_results($sql_query, ARRAY_A);
			$bytaxonomy	=	array();
			$category_terms	=	array();
			foreach($itemscategory as $itemscategory_items) {
				array_push( $category_terms , $itemscategory_items['term_id'] );
				$bytaxonomy[$itemscategory_items['term_taxonomy_id']]	=	array(
					'term_id'	=>	$itemscategory_items['term_id'] ,
					'taxonomy'	=>	$itemscategory_items['taxonomy']
				);
			}
			
			$itemstermsbyid	=	array();
			if(is_array($category_terms) and count($category_terms)) {
				$sql_query	=	" SELECT `term_id` , `name` , `slug` FROM `".$wpdb->prefix."terms` WHERE `term_id` IN (".implode( " , ", $category_terms ).") ";
				$itemsterms	=	$wpdb->get_results($sql_query, ARRAY_A);
				foreach($itemsterms as $term) {
					$itemstermsbyid[$term['term_id']]['name']	=	$term['name'];
					$itemstermsbyid[$term['term_id']]['slug']	=	$term['slug'];
				}
			}
			
			foreach($bytaxonomy as $taxonomyid => $item) {
				$url	=	'';
				if(isset($itemstermsbyid[$item['term_id']]['slug'])) {
					$url	=	get_term_link($itemstermsbyid[$item['term_id']]['slug'], $item['taxonomy']);
				}
				$bytaxonomy[$taxonomyid]['url']	=	$url;
			}
			
			
			foreach($itemscategory as $itemcategory) {
				$url		=	'';
				if(isset($bytaxonomy[$itemcategory['term_taxonomy_id']]['url'])) {
					$url	=	$bytaxonomy[$itemcategory['term_taxonomy_id']]['url'];
				}
				
				$name		=	'';
				if(isset($itemstermsbyid[$itemcategory['term_id']]['name'])) {
					$name	=	$itemstermsbyid[$itemcategory['term_id']]['name'];
				}
				
				$taxonomy	=	'';
				if(isset($bytaxonomy[$itemcategory['term_taxonomy_id']]['taxonomy'])) {
					$taxonomy	=	$bytaxonomy[$itemcategory['term_taxonomy_id']]['taxonomy'];
				}
				
				$own_names_cats[$itemcategory['term_taxonomy_id']]	=	array(
					'href'		=>	$url ,
					'name'		=>	$name ,
					'taxonomy'	=>	$taxonomy
				);
			}
			
			return	$own_names_cats;
			
			/*
			href
			name
			taxonomy
			*/
			/*
			print_r($bytaxonomy);
			print_r($itemstermsbyid);
			
			exit;
			 
			
			foreach($itemscategory as $key => $itemcategory) {
				$setname	=	'';
				if(isset($itemstermsbyid[$itemcategory['term_id']])) {
					$setname	=	$itemstermsbyid[$itemcategory['term_id']]['name'];
				}
				
				switch( $itemcategory['taxonomy'] ) {
					case 'post_tag':
					case 'product_tag':
						//	product_tag 
						switch($sourcedata) {
						case '2':
							
							//	echo $itemcategory['term_taxonomy_id'].']]';
							//	$itemcategory['term_taxonomy_id']	=	50;
							$url	=	'';
							$term	=	get_term_by('term_taxonomy_id', $itemcategory['term_taxonomy_id'], 'product_tag', 'ARRAY_A'); 
							$ppp	=	get_term($itemcategory['term_taxonomy_id'], 'product_tag');
							
							if(is_array($term) and count($term)) {
								$url	=	get_term_link($term['slug'], 'product_tag');
							}
							
							
							
							//	TUTAJ SKOŃCZYŁEM	exit;
							
							$own_names_cats[$itemcategory['term_taxonomy_id']]['href']	=	$url;
							$own_names_cats[$itemcategory['term_taxonomy_id']]['name']	=	$setname;
							
							
						break;
						case '1':
						default:
							$own_names_cats[$itemcategory['term_taxonomy_id']]['href']	=	get_tag_link($itemcategory['term_taxonomy_id']);
							$own_names_cats[$itemcategory['term_taxonomy_id']]['name']	=	$setname;
						break;
						}
					break;
					default:
					case 'category':
					case 'product_cat':
						switch($sourcedata) {
						case '2':
							$url	=	'';
							$term	=	get_term_by('id', $itemcategory['term_taxonomy_id'], 'product_cat', 'ARRAY_A'); 
							if(is_array($term) and count($term)) {
								$url	=	get_term_link($term['slug'], 'product_cat');
							}
							$own_names_cats[$itemcategory['term_taxonomy_id']]['href']	=	$url;
							$own_names_cats[$itemcategory['term_taxonomy_id']]['name']	=	$setname;
						break;
						case '1':
						default:
							$own_names_cats[$itemcategory['term_taxonomy_id']]['href']	=	get_category_link($itemcategory['term_taxonomy_id']);
							$own_names_cats[$itemcategory['term_taxonomy_id']]['name']	=	$setname;
						break;
						}
					break;
				}
			}
			*/
			
		}
		//	POBRANIE NAZW KATEGORII -- KONIEC
		
		return	$own_names_cats;
	}
	
	public static function PrepareData4Send_GetNamesOfUsersFromItems($items) {
		global	$wpdb;
		$users_names	=	array();
		//	foreach($items['items'] as $item) {
		foreach($items as $item) {
			array_push( $users_names , $item['author']);
		}
		$users_names	=	array_unique($users_names);
		$users_names_ar	=	array();
		if(is_array($users_names) and count($users_names)) {
			//	$users_names
			$sql_query	=	" SELECT `ID` , `user_nicename` FROM `".$wpdb->prefix."users` WHERE `ID` IN (".implode(" , ", $users_names).") ";
			$itemsusers	=	$wpdb->get_results($sql_query, ARRAY_A);
			foreach($itemsusers as $itemsusers_item) {
				$users_names_ar[$itemsusers_item['ID']]	=	$itemsusers_item['user_nicename'];
			}
		}
		return	$users_names_ar;
	}
	
	public static function GetImages($groupids, $maxwidthitem) {
		global		$wpdb;
		$sizes		=	array( 'thumbnail' , 'medium' , 'large', 'full' );
		$imgbyid	=	array();
		if(is_array($groupids) and count($groupids)) {
			$byidimgs	=	array();
			foreach($groupids as $imgid) {
				$byidimgs[$imgid]	=	$imgid;
			}
			$sql_query	=	" SELECT `post_id`, `meta_value` FROM `".$wpdb->prefix."postmeta` WHERE `post_id` IN (".implode(" , ", $groupids).") AND `meta_key` = '_wp_attachment_metadata' ";
			$postsmeta	=	$wpdb->get_results($sql_query, ARRAY_A);
			foreach($postsmeta as $itempostmeta) {
			
				unset($byidimgs[$itempostmeta['post_id']]);
				$uns		=	unserialize($itempostmeta['meta_value']);
				
				$imgbyid[$itempostmeta['post_id']]['thumb']	=	home_url().'/wp-content/uploads/'.$uns['file'];
				$imgbyid[$itempostmeta['post_id']]['large']	=	home_url().'/wp-content/uploads/'.$uns['file'];
				
				$uns['sizes']['full']['width']	=	$uns['width'];
				$uns['sizes']['full']['height']	=	$uns['height'];
				$tt		=	explode('/', $uns['file']);
				$uns['sizes']['full']['file']	=	array_pop($tt);
				$find_size	=	'';
				foreach($sizes as $size) {
					if(isset($uns['sizes'][$size])) {
						if($uns['sizes'][$size]['width']>=$maxwidthitem) {
							$find_size	=	$size;
							break;
						} else {
							$find_size	=	$size;
						}
					}
				}
				
				$rok	=	substr($uns['file'], 0, 4);
				$msc	=	substr($uns['file'], 5, 2);
				if(isset($uns['sizes']['large']['file'])) {
					$imgbyid[$itempostmeta['post_id']]['thumb']	=	home_url().'/wp-content/uploads/'.$rok.'/'.$msc.'/'.$uns['sizes']['large']['file'];
					$imgbyid[$itempostmeta['post_id']]['large']	=	home_url().'/wp-content/uploads/'.$rok.'/'.$msc.'/'.$uns['sizes']['large']['file'];
				}
				
				if($find_size!='') {
					$path	=	$uns['sizes'][$find_size]['file'];
					$imgbyid[$itempostmeta['post_id']]['thumb']	=	home_url().'/wp-content/uploads/'.$rok.'/'.$msc.'/'.$path;
					
				} else {
					
				}
			}
			
			if(is_array($byidimgs) and count($byidimgs)) {
				$sql_query	=	" SELECT * FROM `".$wpdb->prefix."postmeta` WHERE `post_id` IN (".implode(" , ", $byidimgs).") AND `meta_key` = '_wp_attached_file' ";
				$imgs		=	$wpdb->get_results($sql_query, ARRAY_A);
;				foreach($imgs as $img) {
					$imgbyid[$itempostmeta['post_id']]['thumb']	=	home_url().'/wp-content/uploads/'.$img['meta_value'];
					$imgbyid[$itempostmeta['post_id']]['large']	=	home_url().'/wp-content/uploads/'.$img['meta_value'];
				}
			}
			
			
		}
		return	$imgbyid;
	}
	
	public static function hextorgba($color, $alpha=1) {
		$array	=	self::Hex2rgb($color);
		array_push($array, $alpha);
		return		$array;
	}
	
	public static function Hex2rgb($hex) {
		$hex = str_replace("#", "", $hex);
		if(strlen($hex) == 3) {
		    $r = hexdec(substr($hex,0,1).substr($hex,0,1));
		    $g = hexdec(substr($hex,1,1).substr($hex,1,1));
		    $b = hexdec(substr($hex,2,1).substr($hex,2,1));
		} else {
		    $r = hexdec(substr($hex,0,2));
		    $g = hexdec(substr($hex,2,2));
		    $b = hexdec(substr($hex,4,2));
		}
		$rgb = array($r, $g, $b);
		return $rgb;
	}
	
	public static function ShowInStructure($item, $main, $option) {
		
		//	print_r($main); exit;
		
		$set_dl_background_color	=	'';
		$set_dl_background___color	=	'';
		
		//	$item['dlbgcolor']				=	'ff0000';
		
		
		
		//	$main['advanced_dl_bgcolor_get_from_this_conf']	=	'1';
		
		//	$main['advanced_dl_bgcolor_get_from_item']	=	'1';
		
		//	print_r($main); exit;
		
		// nie zaznacza http://localhost/wordpress/wp-admin/admin.php?page=nihon_portgal_manage_handle&action=edit&actiontype=advanced&id=2
		// dark layer opacity
		
		if($main['advanced_dl_opacity']=='-1') {
			$main['advanced_dl_opacity']	=	0.75;
		}
		
		if($main['advanced_dl_bgcolor_get_from_this_conf']=='1') {
			if($main['advanced_dl_bgcolor']!='') {
				$rgba	=	self::hextorgba($main['advanced_dl_bgcolor'], $main['advanced_dl_opacity']);
				$strimp	=	implode(",", $rgba);
				$set_dl_background_color	=	' background-color: rgba('.$strimp.'); ';
				$set_dl_background___color	=	' background: rgba('.$strimp.'); ';
			}
		}
		
		if($main['advanced_dl_bgcolor_get_from_item']=='1') {
			if($item['dlbgcolor']!='') {
				$rgba	=	self::hextorgba($item['dlbgcolor'], $main['advanced_dl_opacity']);
				$strimp	=	implode(",", $rgba);
				$set_dl_background_color	=	' background-color: rgba('.$strimp.'); ';
				$set_dl_background___color	=	' background: rgba('.$strimp.'); ';
			}
		}
		
		
		
		
		
		//	darklayerbackgroundcolor
		
		//	self::hextorgba();
		
		//	ShowInStructure
		
		$animation	=	$main['dlanimation_type'];
		
		if(trim($main['advanced_label_read_more'])=='') {
			$main['advanced_label_read_more']	=	'Read more';
		}
		
		//	$animation
		/*
		$item	=	array(
			'title'		=>	'SOME TITLE',
			'description'	=>	'Some text some text',
			'thumb'		=>	'http://localhost/patti/wp-content/uploads/2014/12/8.jpg',
			'thumb_second'	=>	'http://localhost/untitled.png',
			'fullimage'	=>	'http://www.joomlaworks.net/images/documentation/SIGPro_Editor_XTD_Button.jpg',
			'category'	=>	array(1,2,3),
			'category_links'	=>	array(
				'hrefsomewhere1'	=>	'CATEGORY 1' ,
				'hrefsomewhere2'	=>	'CATEGORY 2'
			),
			'dateymd'	=>	'2015-04-12',
			'author'	=>	'admin',
			'href'		=>	'someurl',
			'order'		=>	array(
				1	=>	'21',
				2	=>	'Title'
			),
			'videourls'		=>	array( 'http://player.vimeo.com/video/92112616?color=bc9b6a&title=0&byline=0&portrait=0' ), // for vimmeo youtube
			'videourls'		=>	array( 
				'ogg'	=>	'http://goodwebtheme.com/previewvideo/forest_edit.ogv',
				'webm'	=>	'http://goodwebtheme.com/previewvideo/forest_edit.webm',
				'mp4'	=>	'http://goodwebtheme.com/previewvideo/forest_edit.mp4'
			),
			'videtype'		=>	'html5' // vimmeo , youtube
		);
		*/
		
		$have_video	=	false;
		$add_vide_str	=	'';
		switch($item['videtype']) {
			case 'vimmeo':
			case 'youtube':
				$item_video_url	=	array_pop($item['videourls']);
				if(trim($item_video_url)!='') {
					$have_video	=	true;
				}
				$add_vide_str	=	'  portgalvideourl="'.$item_video_url.'" portgalvideotype="'.$item['videtype'].'" ';
			break;
			case 'html5':
				$add_vide_str	=	' portgalvideotype="html5" ';
				foreach($item['videourls'] as $key => $url) {
					if(trim($url)!='') {
						$have_video	=	true;
					}
					$add_vide_str	.=	'  portgalvideo'.$key.'="'.$url.'" ';
				}
			break;
		}
		
		$str_order	=	'';
		
		foreach($item['order'] as $key => $value) {
			$str_order	.=	' portgal-attribute-order'.$key.'="'.$value.'" ';
		}
		
		if($option['addclassnewitems']==true) {
			$str_order	.=	'  class="portgalnewitem" ';
		}
		
		
		$searchtext		=	$item['title'].' '.$item['description'].' ';
		if($item['price_label']!='0' and $item['price_label']!='') {
			$searchtext	.=	$item['price_label'];
		}
		
		$searchtext		=	addslashes(mb_strtolower(strip_tags($searchtext)));
		$searchhtml		=	'<input type="hidden" class="portgal-vfsearch" value="'.$searchtext.'">';
		
		switch($animation) {
		case '1001':
		case 'wooida':
			$str_category	=	'';
			foreach($item['category_links'] as $url => $label) {
				if(strlen($str_category)>0) {
					$str_category	.=	', ';
				}
				$str_category	.=	' <a href="'.$url.'">'.$label.'</a> ';
			}
			
			$set_kupuj_button	=	'';
			if($item['woocommerce_id']!='' and $item['woocommerce_id']!='0') {
				$set_kupuj_button	=	'<span class="portgal-circle-anchortype1 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr2 fa fa-shopping-cart portgal-button-addtocart" title="Add to cart" portgalwoocommerceproductid="'.$item['woocommerce_id'].'"></span>';
			}
			
			$raiting	=	round($item['raiting'],1);
			$raiting	=	str_replace('.', '', $raiting);
			
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0" style="'.$set_dl_background_color.'">
						
						<a href="'.$item['href'].'" class="portgal-circle-anchortype1 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link" title="View"></a>
						'.$set_kupuj_button.'
						<a href="'.$item['href'].'" class="portgal-link-viewcard portgal-link portgal-text-link portgal-text-link-type-2 portgal-text-bold">VIEW CART</a>
						
					</div>
				</div>
				<div class="portgal-item-ajaxpreloader"></div>
				<div class="portgal-wooida-price-wrap"><span class="portgal-wooida-price">'.$item['price_label'].'</span></div>
				<div class="portgal-wooida-txt-wrap">
					<a href="'.$item['href'].'" class="portgal-wooida-header">'.$item['title'].'</a>
					
					<div class="portgal-wooaida-categories">'.$str_category.'</div>
					<div  class="portgal-wooida-star-raiting-wrap">
						<span class="portgal-woo-star-raiting"></span>
						<span class="portgal-woo-star-raiting_points portgal-woo-sr-'.$raiting.'"></span>
					</div>
					
				</div>
				<div class="portgal-wooida-bottomshadow" ></div>
				'.$searchhtml.'
			 </li>';
		break;
		case '1000':
		case 'wooadriana':
			$str_category	=	'';
			foreach($item['category_links'] as $url => $label) {
				if(strlen($str_category)>0) {
					$str_category	.=	', ';
				}
				$str_category	.=	' <a href="'.$url.'">'.$label.'</a> ';
			}
			
			$set_kupuj_button	=	'';
			if($item['woocommerce_id']!='' and $item['woocommerce_id']!='0') {
				$set_kupuj_button	=	'<span class="portgal-circle-anchortype1 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr2 fa fa-shopping-cart portgal-button-addtocart" title="Add to cart" portgalwoocommerceproductid="'.$item['woocommerce_id'].'"></span>';
			}
			
			$raiting	=	round($item['raiting'],1);
			$raiting	=	str_replace('.', '', $raiting);
			
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
			<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
				  <div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'">
					  
					  <a href="'.$item['href'].'" class="portgal-circle-anchortype1 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link" title="View"></a>
					  
					  '.$set_kupuj_button.'
					  
					  <a href="'.$item['href'].'" class="portgal-link-viewcard portgal-link portgal-text-link portgal-text-link-type-2 portgal-text-bold">VIEW CART</a>
					  
				  </div>
			  </div>
			  <div class="portgal-item-ajaxpreloader"></div>
			  <div class="portgal-wooadriana-txt-wrap">
				  <a href="'.$item['href'].'" class="portgal-wooadriana-header">'.$item['title'].'</a>
				  <div class="portgal-wooadriana-price-wrap"><span class="portgal-wooadriana-price">'.$item['price_label'].'</span></div>
				  <div class="portgal-wooadriana-categories">'.$str_category.'</div>
				  <div  class="portgal-wooadriana-star-raiting-wrap">
					  <span class="portgal-wooadriana-star-raiting"></span>
					  <span class="portgal-wooadriana-star-raiting_points portgal-wooadriana-sr-'.$raiting.'"></span>
				  </div>
			  </div>
			  '.$searchhtml.'
			  </li>';
		break;
		case '102':
		case 'videorose':
			$str_category	=	'';
			foreach($item['category_links'] as $url => $label) {
				if(strlen($str_category)>0) {
					$str_category	.=	' | ';
				}
				$str_category	.=	' <a href="'.$url.'">'.$label.'</a> ';
			}
			
			$str_button_play	=	'';
			if($have_video) {
				$str_button_play	=	'<span class="portgaldlinsideanimation portgal-button-run-video portgal-videorose-play-button" '.$add_vide_str.'><span class="fa fa-play-circle"></span></span>';
			}
			
			
			
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'">
						<div class="portgal-tabletype-wrapper">
							<div class="portgal-tabletype-cell">
								'.$str_button_play.'
							</div>
						</div>
					</div>
				</div>
				<div class="portgal-videorose-text-wrap">
					<a href="'.$item['href'].'" class="portgal-videorose-anchor-h1">'.$item['title'].'</a>
					<div class="portgal-videorose-anchor-category">
						'.$str_category.'
					</div>
					<div>'.$item['description'].'</div>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '101':
		case 'videoayumi':
			$str_category	=	'';
			foreach($item['category_links'] as $url => $label) {
				$str_category	.=	' <a href="'.$url.'">'.$label.'</a> ';
			}
			
			$str_button_play	=	'';
			if($have_video) {
				$str_button_play	=	'<span class="portgal-circle-anchortype1b2 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-play portgal-button-run-video portgal-videoayimu-play-button " '.$add_vide_str.'></span>';
			}
			
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper">
					<div class="portgal-dlayer"></div>
					<div class="portgal-dlayer-after"  style="'.$set_dl_background_color.'">
						'.$str_button_play.'
					</div>
				</div>
				<div class="portgal-videoayumi-title"><a href="'.$item['href'].'">'.$item['title'].'</a></div>
				<div class="portgal-videoayumi-category">'.$str_category.'</div>
				<div class="portgal-videoayumi-txt">'.$item['description'].'</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '100':
		case 'videoaoi':
			$str_category	=	'';
			foreach($item['category_links'] as $url => $label) {
				$str_category	.=	' <a href="'.$url.'">'.$label.'</a> ';
			}
			
			$str_button_play	=	'';
			if($have_video) {
				$str_button_play	=	'<span class="portgaldlinsideanimation portgal-button-run-video portgal-videoaoi-video-button" '.$add_vide_str.'><span class="fa fa-play-circle"></span></span>';
			}
			
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'">
						<div class="portgal-tabletype-wrapper">
							<div class="portgal-tabletype-cell">
								'.$str_button_play.'
							</div>
						</div>
					</div>
				</div>
				<div class="portgal-videoaoi-text-wrap">
					<a href="'.$item['href'].'" class="portgal-videoaoi-anchor-h1">'.$item['title'].'</a>
					<div class="portgal-videoaoi-anchor-category">
						'.$str_category.'
					</div>
					<div>'.$item['description'].'</div>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '16':
		case 'sophielight':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'"> 
						
						<span class="portgal-circle-anchortype1 portgal-circle-anchor portgal-link portgaldlinsideanimation portgal-circle-nr1 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
						
					</div>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '15':
		case 'veronica':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper">
					<div class="portgal-dlayer portgal-bgscale" style="background-image: url(\''.$item['thumb_second'].'\');  style="'.$set_dl_background_color.'"">
					</div>
					<div class="portgal-after-dlayer-content" style="width: 100%">
						<a href="'.$item['href'].'" class="portgaldlinsideanimation portgal-link-nr1 portgal-link portgal-text-link portgal-text-link-type-1">'.$item['title'].'</a>
					</div>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '14':
		case 'sophie':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'"> 
						<a href="'.$item['href'].'" class="portgal-circle-anchortype2 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link"></a>
						<span class="portgal-circle-anchortype2 portgal-circle-anchor portgal-link portgaldlinsideanimation portgal-circle-nr2 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
						
					</div>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '13':
		case 'silvia':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper">
					<div class="portgal-bgscale-bgwithopacity">
						<div class="portgal-dlayer" style="'.$set_dl_background_color.'"></div>
						<div class="portgal-after-dlayer-content"> <a href="'.$item['href'].'" class=" portgal-silvia-main-anchor"><span class="portgal-silvia-main-a-bold">'.$item['title'].'</span><span class="portgal-silvia-main-a-undertext">'.$item['description'].'</span></a></div>
					</div>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '12':
		case 'misha':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper">
					<div class="portgal-dlayer" style="'.$set_dl_background_color.'">
					<a href="'.$item['href'].'" class="portgal-circle-anchortype2 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link"></a>
					<span class="portgal-circle-anchortype2 portgal-circle-anchor portgal-link portgaldlinsideanimation portgal-circle-nr2 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
					<a href="'.$item['href'].'" class="portgaldlinsideanimation portgal-link-nr1 portgal-link portgal-text-link portgal-text-link-type-1">'.$item['title'].'</a>
					</div>
				</div>
				'.$searchhtml.'
			</li>';
		break;
		case '11':
		case 'marie':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper">
					<div class="portgal-dlayer" style="'.$set_dl_background_color.'">
						<div class="portgal-dlayer-content">
						</div>
					</div>
					<div class="portgal-dlayer-after">
						<a href="'.$item['href'].'" class="portgal-circle-anchortype1 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link"></a>
						<span href="'.$item['href'].'" class="portgal-circle-anchortype1 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr2 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
					</div>
				</div>
				'.$searchhtml.'
			</li>';
		break;
		case '10':
		case 'lisa':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'">
					<a href="'.$item['href'].'" class="portgaldlinsideanimation portgal-link-nr1 portgal-link portgal-text-link portgal-text-link-type-1">'.$item['title'].'</a>
					</div>
				</div>
				'.$searchhtml.'
			</li>';
		break;
		case '9':
		case 'kristina':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'"></div>
					<div class="portgal-dlayer-after">
						<a href="'.$item['href'].'" class="portgal-circle-anchortype1 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link portgal-link-nr1"></a>
						<span class="portgal-circle-anchortype1 portgal-circle-anchor portgal-link portgaldlinsideanimation portgal-circle-nr2 fa fa-search portgal-button-run-lightbox portgal-link-nr2" portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
					</div>
				</div>
				'.$searchhtml.'
			</li>';
		break;
		case '8':
		case 'kayla':
//	!!!!!! uzupełnic label Read more
			$str_category	=	'';
			foreach($item['category_links'] as $url => $label) {
				if(strlen($str_category)>0) {
					$str_category	.=	' , ';
				}
				$str_category	.=	' <a href="'.$url.'">'.$label.'</a> ';
			}
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer" style="text-align: center; '.$set_dl_background_color.'">
						<a href="'.$item['href'].'" class="portgaldlinsideanimation portgal-link-nr1 portgal-link portgal-text-link portgal-text-link-type-1">'.$main['advanced_label_read_more'].'</a>
					</div>
					<div class="portgal-dlayer-withbottomarea">
						<div class="portgal-kayla-title">'.$item['title'].'</div>
						<div class="portgal-kayla-category">'.$str_category.'</div>			
						<div class="portgal-kayla-txt">'.$item['description'].'</div>
					</div>
				</div>
				'.$searchhtml.'
			</li>';
		break;
		case '7':
		case 'jessie':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'">
						<a href="aa" class="portgaldlinsideanimation portgal-dlayer_uptext-wrap">
							<div class="portgal-dl-header">'.$item['title'].'</div>
							<div class="portgal-dl-subtext">'.$item['description'].'</div>
						</a>
					</div>
				</div>
				'.$searchhtml.'
			</li>';
		break;
		case '6':
		case 'irina':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper">
					<div class="portgal-fromdarkbg-before" style="'.$set_dl_background_color.'">
						<span class="portgal-fromdarkbg-before-txt-rapper">
							<span class="portgal-fromdarkbg-before-txt">'.$item['title'].'</span>
						</span>
					</div>
					<div class="portgal-dlayer portgal-dlayer-transparent" >
						
			<span class="portgal-circle-anchortype1b portgal-circle-anchor portgal-link portgal-link-nr1 portgaldlinsideanimation portgal-circle-nr1 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
			
			<a href="'.$item['href'].'" class="portgaldlinsideanimation portgal-link-nr2 portgal-link portgal-text-link portgal-text-link-type-1wa portgal-text-bigger">'.$item['title'].'</a>
					      
					</div>
				</div>
				'.$searchhtml.'

			</li>';
		break;
		case '5':
		case 'chihiro':
//	!!!!!! uzupełnic label Read more
			
			$str_category	=	'';
			foreach($item['category_links'] as $url => $label) {
				$str_category	.=	' <a href="'.$url.'">'.$label.'</a> ';
			}
			
			
			
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div class="portgal-chihiro-text-wrap">
					<a href="'.$item['href'].'" class="portgal-chihiro-anchor-h1">'.$item['title'].'</a>
					<div class="portgal-chihiro-anchor-category">
					      '.$str_category.'
					</div>
					<div>'.$item['description'].'</div>
					<div class="portgal-chihiro-social">
					<a href="https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($item['href']).'" class="fa fa-facebook-square"></a>
					<a href="https://twitter.com/intent/tweet?text='.rawurlencode($item['href']).'" class="fa fa-twitter-square"></a>
					<a href="https://plus.google.com/share?url='.rawurlencode($item['href']).'" class="fa fa-google-plus-square"></a>
					<a href="https://pinterest.com/pin/create/bookmarklet/?media=&description='.rawurlencode($item['href']).'" class="fa fa-pinterest-square"></a>
					</div>
				</div>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-opacity-0"  style="'.$set_dl_background_color.'">
						<a href="'.$item['href'].'" class="portgaldlinsideanimation portgal-link-nr1 portgal-link portgal-text-link portgal-text-link-type-1 portgal-text-bold">'.$main['advanced_label_read_more'].'</a>
					</div>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '0':
		case 'adele':
		//<span  class="portgal-circle-anchortype1b portgal-circle-anchor portgal-link portgaldlinsideanimation portgal-circle-nr2 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
			//	style="background-color: red ;  '.$set_dl_background_color.' "
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div class="fdlayer-rotateallx-before portgal-bgscale" style="background-image: url(\''.$item['thumb'].'\');"></div>
				<div class="fdlayer-rotateallx-after" style="'.$set_dl_background___color.'""></div>
				<div class="portgal-dlayer-after"  >
					<span  class="portgal-circle-anchortype1b portgal-circle-anchor portgal-link portgaldlinsideanimation portgal-circle-nr2 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
					<a href="'.$item['href'].'" class="portgal-circle-anchortype1b portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link"></a>
					<a href="'.$item['href'].'" class="portgaldlinsideanimation portgal-link-nr1 portgal-link portgal-text-link portgal-text-link-type-1">'.$item['title'].'</a>
				</div>
				'.$searchhtml.'
			</li>';
		break;
		case '1':
		case 'alexis':
//	!!!!!! uzupełnic label Read more
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper">
					<div class="portgal-dlayer " style="'.$set_dl_background_color.'"></div>
					<div class="portgal-dlayer-after">
						<a href="'.$item['href'].'" class="portgal-circle-anchortype2 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link"></a>
						<span class="portgal-circle-anchortype2 portgal-circle-anchor portgal-link portgaldlinsideanimation portgal-circle-nr2 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
					</div>
				</div>
				<a href="" class="portgal-alexis-anchor-h1">'.$item['title'].'</a>
				<div class="portgal-alexis-afteranchor">
					<span class="fa fa-calendar"></span>
					<span class="portgal-data">'.$item['dateymd'].'</span>
					<span class="fa fa-user"></span>
					<span class="portgal-username">'.$item['author'].'</span>
				</div>
				<div>'.$item['description'].'</div>
				<a href="'.$item['href'].'" class="portgal-alexis-anchor-readmore">'.$main['advanced_label_read_more'].'</a>
				'.$searchhtml.'
			</li>
			';
		break;
		case '2':
		case 'angelina':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
					<div class="portgal-dlayer portgal-dlayer-h100 portgal-dlayer-transparent"  style="'.$set_dl_background_color.'">
					
					<a href="'.$item['href'].'" class="portgal-circle-anchortype1b2 portgal-circle-anchor portgaldlinsideanimation portgal-circle-nr1 fa fa-link"></a>
					<span class="portgal-circle-anchortype1b2 portgal-circle-anchor portgal-link portgaldlinsideanimation portgal-circle-nr2 fa fa-search portgal-button-run-lightbox " portgalshowinlightboximg="'.$item['fullimage'].'" portgalshowinlightboxtxt="'.$item['title'].'"></span>
					<div class="portgal-dl-downtext-subtext_bottom">'.$item['title'].'</div>
					
					</div>
				</div>
				<div class="portgal-angelina-text-wrap">
					<div class="portgal-angelina-afteranchor">
						<span class="fa fa-calendar"></span>
						<span class="portgal-data">'.$item['dateymd'].'</span>
						<span class="fa fa-user"></span>
						<span class="portgal-username">'.$item['author'].'</span>
					</div>
					<div>'.$item['description'].'</div>
				</div>
				'.$searchhtml.'
			</li>';
		break;
		case '3':
		case 'bridget':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\'); " class="portgal-bgscale fdlayer-wrapper" >
				<a href="'.$item['href'].'">
					<div class="portgal-dlayer portgal-opacity-0 portgal-bgscale" style="'.$set_dl_background_color.' background-image: url(\''.$item['thumb_second'].'\');" ></div>
				</a>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;
		case '4':
		case 'caroline':
			$output	=	'
			<li portgal-category="'.implode(',',$item['category']).'" '.$str_order.'>
				<div style="background-image: url(\''.$item['thumb'].'\');" class="portgal-bgscale fdlayer-wrapper">
					<div class="portgal-dlayer" style="'.$set_dl_background_color.'">
						<a href="'.$item['href'].'" class="portgaldlinsideanimation portgal-link-nr1 portgal-link portgal-text-link portgal-text-link-type-1">'.$item['title'].'</a>
					</div>
				</div>
				'.$searchhtml.'
			</li>
			';
		break;

		}
		
		return	$output;
	}
	
	public static function MakeNavigationHtml($main, $categories) {
		
		$align			=	self::GetTextAlignOption();
		$set_style	=	'';
		foreach($align as $item) {
			if($item['id']==$main[0]['navigation_align']) {
				$set_style	.=	' '.$item['style'];
			}
		}
		
		$str_categories		=	'';
		foreach($categories as $category) {
			$str_categories		.=	'<span portgal-category="'.$category['id'].'">'.htmlspecialchars($category['name']).'</span>';
		}
		
		if($main[0]['navigation_label_for_all']=='') {
			$main[0]['navigation_label_for_all']	=	'ALL';
		}
		//	'.$main[0]['navigation_type'].'
		
		if($main[0]['navigation_hide']=='1') {
			$set_style	.=	' display: none; ';
		}
		
		$output	=	'
		<div class="portgal-navigation-wrap portgal-navigation-type-'.$main[0]['navigation_type'].'" style="'.$set_style.'">
			<div class="portgal-navigation">
				<span portgal-category="" class="portgal-check">'.htmlspecialchars($main[0]['navigation_label_for_all']).'</span>'.$str_categories.'
			</div>
			<div class="portgal-navigation-order"></div>
		</div>
		';
		
		return	$output;
	}
	
	public static function GetBoolStr($input) {
		if($input=='1') { return 'true'; }
		return	'false';
	}
	
	public static function GetValueFromVA($array, $search, $default='') {
		foreach($array as $item) {
			if($item['id']==$search) {
				return $item['name'];
			}
		}
		return	$default;
	}
	
	public static function GetMaxTimeCache() {
		$array	=	array(
			array(	'id' => '60'	, 'name'	=>	'1 minute'),
			array(	'id' => '120'	, 'name'	=>	'2 minutes'),
			array(	'id' => '300'	, 'name'	=>	'5 minutes'),
			array(	'id' => '600'	, 'name'	=>	'10 minutes'),
			array(	'id' => '1800'	, 'name'	=>	'30 minutes'),
			array(	'id' => '3600'	, 'name'	=>	'1 hour'),
			array(	'id' => '7200'	, 'name'	=>	'2 hours'),
			array(	'id' => '10800'	, 'name'	=>	'3 hours'),
			array(	'id' => '21600'	, 'name'	=>	'6 hours'),
			array(	'id' => '43200'	, 'name'	=>	'12 hours'),
			array(	'id' => '86400'	, 'name'	=>	'1 day'),
			array(	'id' => '172800' , 'name'	=>	'2 days')
		);
		return	$array;
	}
	
	public static function GetPreloaders() {
		$array	=	array(
			array('id' => '0', 'name' => '159.GIF') ,
			array('id' => '1', 'name' => '25.GIF') ,
			array('id' => '2', 'name' => '282.GIF') ,
			array('id' => '3', 'name' => '287.GIF') ,
			array('id' => '4', 'name' => '294.GIF') ,
			array('id' => '5', 'name' => '301.GIF') ,
			array('id' => '6', 'name' => '350.GIF') ,
			array('id' => '7', 'name' => '359.GIF') ,
			array('id' => '8', 'name' => '35.GIF') ,
			array('id' => '9', 'name' => '377.GIF') ,
			array('id' => '10', 'name' => '477.GIF') ,
			array('id' => '11', 'name' => '482.GIF') ,
			array('id' => '12', 'name' => '486.GIF') ,
			array('id' => '13', 'name' => '495.GIF') ,
			array('id' => '14', 'name' => '712.GIF') ,
			array('id' => '15', 'name' => '713.GIF') ,
			array('id' => '16', 'name' => '719.GIF') ,
			array('id' => '17', 'name' => '720.GIF') ,
			array('id' => '18', 'name' => '728.GIF'),
			array('id' => '19', 'name' => '89.GIF')
		);
		return	$array;
	}
	
	public static function GetBgScaleRatio() {
		$array	=	array( );
		for($x=0.25;$x<=2.01;$x=$x + 0.05) {
			array_push($array, array( 'id' => round($x, 2) , 'name' => sprintf("%01.2f", $x) ));
		}
		return	$array;
	}
	
	public static function GetMinMarginBeewtenElemets() {
		$array	=	array( array( 'id' => -1 , 'name' => 'Default' ) );
		for($x=0;$x<=20;$x=$x+1) {
			array_push( $array , array( 'id' => $x , 'name' => $x.'px' ) );
		}
		return	$array;
	}
	
	public static function GetDarlLayerOpacity() {
		$array	=	array(
			array( 'id' => -1 , 'name' => 'Default' )
		);
		for($x=0;$x<=1.01;$x=$x + 0.05) {
			array_push($array, array( 'id' => $x , 'name' => sprintf("%01.2f", $x) ));
		}
		return	$array;
	}
	
	public static function GetDuration() {
		$output	=	array();
		for($x=300;$x<=1500;$x=$x+100) {
			array_push($output, array( 'id' => $x , 'name' => $x ));
		}
		return	$output;
	}
	
	public static function GetMaxWidthForItem() {
		$array	=	array();
		for($x=100;$x<=500;$x++) {
			array_push( $array , array(
				'id'	=>	$x,
				'name'	=>	$x.'px'
			));
		}
		return	$array;
	}
	
	public static function GetPagination() {
		return	array(
			array('id' => '0', 'name' => 'All items'),
			array('id' => '1', 'name' => '10'),
			array('id' => '2', 'name' => '20'),
			array('id' => '3', 'name' => '30'),
			array('id' => '4', 'name' => '40'),
			array('id' => '5', 'name' => '50'),
			array('id' => '6', 'name' => '100'),
			array('id' => '7', 'name' => '200'),
			array('id' => '8', 'name' => '300'),
			array('id' => '9', 'name' => '400'),
			array('id' => '10', 'name' => '500')
		);
	}
	
	public static function GetSortBy() {
		return	array(
			array('id' => '0', 'name' => 'Title / Name', 'type' => 'string'),
			array('id' => '1', 'name' => 'Date publish', 'type' => 'numeric'),
			array('id' => '2', 'name' => 'Price (woocommerce)', 'type' => 'numeric'),
			array('id' => '3', 'name' => 'Raiting (woocommerce)', 'type' => 'numeric'),
		);
	}
	
	public static function GetPaginationLoadMoreType() {
		return	array(
			array('id' => '0', 'name' => 'By button'),
			array('id' => '1', 'name' => 'By scroll')
		);
	}
	
	public static function GetShowHideItemAnimation() {
		return	array(
			array('id' => '0', 'name' => 'None'),
			array('id' => '1', 'name' => 'Scale'),
			array('id' => '2', 'name' => 'Rotate'),
			array('id' => '3', 'name' => 'From out / Go out'),
		);
	}
	
	public static function GetDLAnimations() {
		return	array(
			array( 'id' => '0' , 'name' => 'adele' ) ,
			array( 'id' => '1' , 'name' => 'alexis' ) ,
			array( 'id' => '2' , 'name' => 'angelina' ) ,
			array( 'id' => '3' , 'name' => 'bridget' ) ,
			array( 'id' => '4' , 'name' => 'caroline' ) ,
			array( 'id' => '5' , 'name' => 'chihiro' ) ,
			array( 'id' => '6' , 'name' => 'irina' ) ,
			array( 'id' => '7' , 'name' => 'jessie' ) ,
			array( 'id' => '8' , 'name' => 'kayla' ) ,
			array( 'id' => '9' , 'name' => 'kristina' ) ,
			array( 'id' => '10' , 'name' => 'lisa' ) ,
			array( 'id' => '11' , 'name' => 'marie' ) ,
			array( 'id' => '12' , 'name' => 'misha' ) ,
			array( 'id' => '13' , 'name' => 'silvia' ) ,
			array( 'id' => '14' , 'name' => 'sophie' ) ,
			array( 'id' => '15' , 'name' => 'veronica' ) ,
			array( 'id' => '16' , 'name' => 'sophielight' ) ,
			
			array( 'id' => '100' , 'name' => 'videoaoi' ) ,
			array( 'id' => '101' , 'name' => 'videoayumi' ) ,
			array( 'id' => '102' , 'name' => 'videorose' ) ,
			
			array( 'id' => '1000' , 'name' => 'wooadriana' ) ,
			array( 'id' => '1001' , 'name' => 'wooida' ) ,
		);
	}
	
	public static function GetEasing() {
		return	array(
			array('id' => '0' , 'name' => 'swing'),
			array('id' => '1' , 'name' => 'linear'),
			array('id' => '2' , 'name' => 'easeInQuad'),
			array('id' => '3' , 'name' => 'easeOutQuad'),
			array('id' => '4' , 'name' => 'easeInOutQuad'),
			array('id' => '5' , 'name' => 'easeInCubic'),
			array('id' => '6' , 'name' => 'easeOutCubic'),
			array('id' => '7' , 'name' => 'easeInOutCubic'),
			array('id' => '8' , 'name' => 'easeInQuart'),
			array('id' => '9' , 'name' => 'easeOutQuart'),
			array('id' => '10' , 'name' => 'easeInOutQuart'),
			array('id' => '11' , 'name' => 'easeInQuint'),
			array('id' => '12' , 'name' => 'easeOutQuint'),
			array('id' => '13' , 'name' => 'easeInOutQuint'),
			array('id' => '14' , 'name' => 'easeInExpo'),
			array('id' => '15' , 'name' => 'easeOutExpo'),
			array('id' => '16' , 'name' => 'easeInOutExpo'),
			array('id' => '17' , 'name' => 'easeInSine'),
			array('id' => '18' , 'name' => 'easeOutSine'),
			array('id' => '19' , 'name' => 'easeInOutSine'),
			array('id' => '20' , 'name' => 'easeInCirc'),
			array('id' => '21' , 'name' => 'easeOutCirc'),
			array('id' => '22' , 'name' => 'easeInOutCirc'),
			array('id' => '23' , 'name' => 'easeInElastic'),
			array('id' => '24' , 'name' => 'easeOutElastic'),
			array('id' => '25' , 'name' => 'easeInOutElastic'),
			array('id' => '26' , 'name' => 'easeInBack'),
			array('id' => '27' , 'name' => 'easeOutBack'),
			array('id' => '28' , 'name' => 'easeInOutBack'),
			array('id' => '29' , 'name' => 'easeInBounce'),
			array('id' => '30' , 'name' => 'easeOutBounce'),
			array('id' => '31' , 'name' => 'easeInOutBounce')
		);
	}
	
	public static function GetTextAlignOption() {
		return	array(
			array( 'id' => 0 , 'style' => '' , 'name' => 'None' ) ,
			array( 'id' => 1 , 'style' => 'text-align:left;' , 'name' => 'Left' ) ,
			array( 'id' => 2 , 'style' => 'text-align:center;' , 'name' => 'Center' ) ,
			array( 'id' => 3 , 'style' => 'text-align:right;' , 'name' => 'Right' ) ,
		);
	}
	
	public static function GetFontSize() {
		$output	=	array(
			array( 'id' => 0 , 'name' => 'from theme' )
		);
		for($x=9;$x<40;$x++) {
			array_push($output, array('id' => $x, 'name' => $x.'px'));
		}
		return	$output;
	}

	public static function GetDarkLayerAnimationType() {
		return	array(
			array( 'id' => 0 , 'str' =>  'fadein', 'name' => 'Fade In') ,
			array( 'id' => 1 , 'str' =>  'fromtop', 'name' => 'From top') ,
			array( 'id' => 2 , 'str' =>  'fromleft', 'name' => 'From left') ,
			array( 'id' => 3 , 'str' =>  'fromright', 'name' => 'From right') ,
			array( 'id' => 4 , 'str' =>  'frombottom', 'name' => 'From bottom') ,
		);
	}
	
	public static function GetOpacityValues() {
		$output	=	array();
		for($x=0;$x<1.01;$x=$x+0.05) {
			array_push($output, sprintf("%01.2f", $x));
		}
		return	$output;
	}
	
	public static function GetAvailableNavigationSkins() {
		return	array(
			array( 'id' => 0 , 'name' => 'Default',	'pic' => 'navskin0.png') ,
			array( 'id' => 1 , 'name' => 'Skin 1',	'pic' => 'navskin1.png') ,
			array( 'id' => 2 , 'name' => 'Skin 2',	'pic' => 'navskin2.png') ,
			array( 'id' => 3 , 'name' => 'Skin 3',	'pic' => 'navskin3.png') ,
			array( 'id' => 4 , 'name' => 'Skin 4',	'pic' => 'navskin4.png') ,
			array( 'id' => 5 , 'name' => 'Skin 5', 	'pic' => 'navskin5.png') ,
			array( 'id' => 6 , 'name' => 'Skin 6',	'pic' => 'navskin6.png') ,
			array( 'id' => 7 , 'name' => 'Skin 7',	'pic' => 'navskin7.png') ,
		);
	}
	
	public static function GetRatioValues() {
		$output	=	array();
		for($x=0.5;$x<3.01;$x=$x+0.05) {
			array_push($output, sprintf("%01.2f", $x));
		}
		return	$output;
	}
	
	public static function GetAvailableSourceData() {
		return	array(
			array( 'id' => '0', 'name' => 'Own images' ),
			array( 'id' => '1', 'name' => 'Posts' ),
			array( 'id' => '2', 'name' => 'Woocommerce' )
		);
	}
	
	public static function GetItemRaiting() {
		$array	=	array();
		for($x=0;$x<=5;$x=$x+0.5) {
			array_push( $array , array( 'id' => $x , 'name' => sprintf("%01.2f", $x) ) );
		}
		return	$array;
	}
	
	public static function GetPostsWith() {
		return	array(
			array( 'id' => '0', 'name' => 'All' ),
			array( 'id' => '1', 'name' => 'Only images' ),
			array( 'id' => '2', 'name' => 'Only videos' ) ,
			array( 'id' => '3', 'name' => 'Images & videos' )
		);
	}
	
}

?>
