<?php
function usces_define_functions() {

if( !function_exists('usces_item_uploadcsv') ):
function usces_item_uploadcsv() {
	global $wpdb, $usces, $user_ID;

	if( !current_user_can( 'import' ) ) {
		$res['status'] = 'error';
		$res['message'] = __('You do not have permission to do that.');
		$url = USCES_ADMIN_URL.'?page=usces_itemedit&usces_status='.$res['status'].'&usces_message='.urlencode($res['message']);
		wp_redirect($url);
		exit;
	}

	//check data SELECT id, title FROM table GROUP BY id HAVING COUNT(id) > 1
	$query = $wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} 
								LEFT JOIN {$wpdb->posts} ON ID = post_id
								WHERE meta_key = %s AND (post_status = 'pending' OR post_status = 'publish' OR post_status = 'draft' OR post_status = 'private' OR post_status = 'future') 
								GROUP BY meta_value HAVING COUNT(meta_value) > 1", 
							'_itemCode');
	$db_check = $wpdb->get_results( $query, ARRAY_A );
	if( $db_check ) {
		$res['status'] = 'error';
		$res['message'] .= __('The same product cord is registered.', 'usces');
		foreach( $db_check as $d_item ) {
			$res['message'] .= ' , '.$d_item['meta_value'];
		}
		$url = USCES_ADMIN_URL.'?page=usces_itemedit&usces_status='.$res['status'].'&usces_message='.urlencode($res['message']);
		wp_redirect($url);
		exit;
	}

	$path = WP_CONTENT_DIR.'/uploads/';
/*********************************************************************/
//	Upload
/**********************************************************************/
	if( isset($_REQUEST['action']) && 'itemcsv' == $_REQUEST['action'] ) {
		$workfile = $_FILES["usces_upcsv"]["tmp_name"];

		if( !is_uploaded_file($workfile) ) {
			$res['status'] = 'error';
			$res['message'] = __('The file was not uploaded.', 'usces');
			$url = USCES_ADMIN_URL.'?page=usces_itemedit&usces_status='.$res['status'].'&usces_message='.urlencode($res['message']);
			wp_redirect($url);
			exit;
		}

		//check ext
		list($fname, $fext) = explode('.', $_FILES["usces_upcsv"]["name"], 2);
		if( $fext != 'csv' ) {
			$res['status'] = 'error';
			$res['message'] =  __('The file is not supported.', 'usces').$fname.'.'.$fext;
			$url = USCES_ADMIN_URL.'?page=usces_itemedit&usces_status='.$res['status'].'&usces_message='.urlencode($res['message']);
			wp_redirect($url);
			exit;
		}

		$new_filename = base64_encode($fname.'_'.time().'.'.$fext);
		if( ! move_uploaded_file($_FILES['usces_upcsv']['tmp_name'], $path.$new_filename) ) {
			$res['status'] = 'error';
			$res['message'] =  __('The file was not stored.', 'usces').$fname.'.'.$fext;
			$url = USCES_ADMIN_URL.'?page=usces_itemedit&usces_status='.$res['status'].'&usces_message='.urlencode($res['message']);
			wp_redirect($url);
			exit;
		}
		return $new_filename;
	}

/*********************************************************************/
//	Register
/**********************************************************************/
	if( isset($_REQUEST['regfile']) && !WCUtils::is_blank($_REQUEST['regfile']) && isset($_REQUEST['action']) && 'upload_register' == $_REQUEST['action'] ) {
		$file_name = $_REQUEST['regfile'];
		$decode_filename = base64_decode($file_name);
		list( $dfname, $dfext ) = explode( '.', $decode_filename, 2 );
		$lpos = strrpos( $dfname, '_' );
		if( 0 < $lpos ) {
			$decode_filename = substr( $dfname, 0, $lpos ).'.'.$dfext;
		}
		if( ! file_exists($path.$file_name) ) {
			$res['status'] = 'error';
			$res['message'] =  __('CSV file does not exist.', 'usces').esc_html($decode_filename);
			return $res;
		}
	}

	/*////////////////////////////////////////*/
	// ready 
	/*////////////////////////////////////////*/
	$start = microtime(true);

	$wpdb->query( 'SET SQL_BIG_SELECTS=1' );
	set_time_limit(3600);

	define('USCES_COL_POST_ID', 0);
	define('USCES_COL_POST_AUTHOR', 1);
	define('USCES_COL_POST_CONTENT', 2);
	define('USCES_COL_POST_TITLE', 3);
	define('USCES_COL_POST_EXCERPT', 4);
	define('USCES_COL_POST_STATUS', 5);
	define('USCES_COL_POST_COMMENT_STATUS', 6);
	define('USCES_COL_POST_PASSWORD', 7);
	define('USCES_COL_POST_NAME', 8);
	define('USCES_COL_POST_MODIFIED', 9);

	define('USCES_COL_ITEM_CODE', 10);
	define('USCES_COL_ITEM_NAME', 11);
	define('USCES_COL_ITEM_RESTRICTION', 12);
	define('USCES_COL_ITEM_POINTRATE', 13);
	define('USCES_COL_ITEM_GPNUM1', 14);
	define('USCES_COL_ITEM_GPDIS1', 15);
	define('USCES_COL_ITEM_GPNUM2', 16);
	define('USCES_COL_ITEM_GPDIS2', 17);
	define('USCES_COL_ITEM_GPNUM3', 18);
	define('USCES_COL_ITEM_GPDIS3', 19);
	define('USCES_COL_ITEM_SHIPPING', 20);
	define('USCES_COL_ITEM_DELIVERYMETHOD', 21);
	define('USCES_COL_ITEM_SHIPPINGCHARGE', 22);
	define('USCES_COL_ITEM_INDIVIDUALSCHARGE', 23);

	define('USCES_COL_CATEGORY', 24);
	define('USCES_COL_POST_TAG', 25);
	define('USCES_COL_CUSTOM_FIELD', 26);

	$add_field_num = apply_filters( 'usces_filter_uploadcsv_item_field_num', 0 );
	$add_field_num = apply_filters( 'usces_filter_uploadcsv_add_item_field_num', $add_field_num );

	define('USCES_COL_SKU_CODE', 27 + $add_field_num);
	define('USCES_COL_SKU_NAME', 28 + $add_field_num);
	define('USCES_COL_SKU_CPRICE', 29 + $add_field_num);
	define('USCES_COL_SKU_PRICE', 30 + $add_field_num);
	define('USCES_COL_SKU_ZAIKONUM', 31 + $add_field_num);
	define('USCES_COL_SKU_ZAIKO', 32 + $add_field_num);
	define('USCES_COL_SKU_UNIT', 33 + $add_field_num);
	define('USCES_COL_SKU_GPTEKIYO', 34 + $add_field_num);
	$normal_field_num = 35;
	if( usces_is_reduced_taxrate() ) {
		define( 'USCES_COL_SKU_APPLICABLE_TAXRATE', 35 + $add_field_num );
		$normal_field_num = 36;
	}

	$lines = array();
	$total_num = 0;
	$comp_num = 0;
	$err_num = 0;
	$line_num = 0;
	$min_field_num = apply_filters( 'usces_filter_uploadcsv_min_field_num', $normal_field_num + $add_field_num );
	$min_field_num = apply_filters( 'usces_filter_uploadcsv_add_min_field_num', $min_field_num );
	$item_custom_fields = usces_get_item_custom_fields();
	$log = '';
	$pre_code = '';
	$sku_index = 0;
	$res = array();
	$date_pattern = "/(\d{4})-(\d{2}|\d)-(\d{2}|\d) (\d{2}):(\d{2}|\d):(\d{2}|\d)/";
	$linereadytime = 0;
	$checktime = 0;
	$insertposttime = 0;
	$metadeletetime = 0;
	$addmetatime = 0;
	$AddOptiontime = 0;
	$AddSKUtime = 0;
	$onelinetime = 0;
	$yn = "\r\n";
	$br = "<br />";
	$sp = ",";

	//log
	if( ! ($fpi = @fopen (USCES_PLUGIN_DIR.'/logs/itemcsv_log.txt', "w")) ) {
		$res['status'] = 'error';
		$res['message'] = __('The log file was not prepared for.', 'usces').esc_html($decode_filename);
		//echo $res['status'].' : '.$res['message'];
		//return;
	}
	//read data
	if( ! ($fpo = fopen ($path.$file_name, "r")) ) {
		$res['status'] = 'error';
		$res['message'] = __('A file does not open.', 'usces').esc_html($decode_filename);
		//echo $res['status'].' : '.$res['message'];
		return;
	}

	$orglines = array();
	$fname_parts = explode('.', $decode_filename);
	if( 'csv' !== end($fname_parts) ) {
		$res['status'] = 'error';
		$res['message'] = __('This file is not in the CSV file.', 'usces').esc_html($decode_filename);
		echo $res['status'].' : '.$res['message'];
		return;

	} else {
		$buf = '';
		while( ! feof ($fpo) ) {
			$temp = fgets ($fpo, 65535);
			if( 0 == strlen($temp) ) {
				continue;
			}

			$num = substr_count($temp, '"');
			if( 0 == $num % 2 && '' == $buf ) {
				$orglines[] = $temp;
			} elseif( 1 == $num % 2 && '' == $buf ) {
				$buf .= $temp;
			} elseif( 0 == $num % 2 && '' != $buf ) {
				$buf .= $temp;
			} elseif( 1 == $num % 2 && '' != $buf ) {
				$buf .= $temp;
				$orglines[] = $buf;
				$buf = '';
			}
		}
	}
	fclose($fpo);

	$category_format_slug = ( isset($usces->options['system']['csv_category_format']) && 1 == $usces->options['system']['csv_category_format'] ) ? true : false;

	echo '<script type="text/javascript">changeMsg("'.__('Processing...', 'usces').'");</script>'.$yn;
	echo '<div class="error_log">'.$yn;
	ob_flush();
	flush();

	foreach( $orglines as $line ) {
		$line = trim($line);
		if( 0 !== strpos( $line, 'Post ID' ) && !empty($line) ) {
			$lines[] = $line;
		}
	}
	$total_num = count($lines);

	//==========================================================================

	$results = apply_filters( 'usces_filter_item_uploadcsv_mode', array(), $lines );
	if( !empty( $results ) ) {
		extract( $results );

	} elseif( isset( $_REQUEST['mode'] ) && 'stock' == $_REQUEST['mode'] ) {
		$results = usces_item_stock_uploadcsv( $lines );
		if( !empty( $results ) ) {
			extract( $results );
		}

	} elseif( isset( $_REQUEST['mode'] ) && 'sku' == $_REQUEST['mode'] ) {
		$results = usces_item_sku_uploadcsv( $lines );
		if( !empty( $results ) ) {
			extract( $results );
		}

	} elseif( isset( $_REQUEST['mode'] ) && 'meta' == $_REQUEST['mode'] ) {
		$results = usces_item_meta_uploadcsv( $lines );
		if( !empty( $results ) ) {
			extract( $results );
		}

	} else {

		$readytime = microtime(true);

		//reg loop
		foreach( $lines as $rows_num => $line ) {

			/*////////////////////////////////////////*/
			// lineReady 
			/*////////////////////////////////////////*/
			$linestart = microtime(true);
			$datas = array();

			$logtemp = '';
			$mestemp = '';
			$line = trim($line);
			if( empty($line) ) {
				continue;
			}

			$d = explode($sp, $line);
			$buf = '';
			foreach( $d as $data ) {
				$num = substr_count($data, '"');
				if( 0 == $num % 2 && '' == $buf ) {
					if( '"' == substr($data, 0, 1) ) {
						$data = substr($data, 1);
					}
					if( '"' == substr($data, -1) ) {
						$data = substr($data, 0, -1);
					}
					$data = str_replace(array('""'), '"', $data);
					$datas[] = ( false !== $data ) ? $data : '';
				} elseif( 1 == $num % 2 && '' == $buf ) {
					$buf .= $data;
				} elseif( 0 == $num % 2 && '' != $buf ) {
					$buf .= $sp.$data;
				} elseif( 1 == $num % 2 && '' != $buf ) {
					$buf .= $sp.$data;
					if( '"' == substr($buf, 0, 1) ) {
						$buf = substr($buf, 1);
					}
					if( '"' == substr($buf, -1) ) {
						$buf = substr($buf, 0, -1);
					}
					$buf = str_replace(array('""'), '"', $buf);
					$datas[] = ( false !== $buf ) ? $buf : '';
					$buf = '';
				}
			}

			if( 'Post ID' == $datas[USCES_COL_POST_ID] ) {
				continue;
			}

			$line_num = $rows_num + 1;

			if( $min_field_num > count($datas) ) {
				$err_num++;
				$mes = "No.".$line_num." ".count($datas)."\t".__('The number of the columns is abnormal.', 'usces');
				$logtemp .= $mes.$yn;
				$log .= $logtemp;
				echo $mes.$br.$yn;
				continue;
			}

			$item_code = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[USCES_COL_ITEM_CODE], 'UTF-8', 'SJIS')) : trim($datas[USCES_COL_ITEM_CODE]);

			if( $pre_code == $item_code && WCUtils::is_blank($datas[USCES_COL_POST_ID]) ) {
				$mode = 'add';

			} else {
				$post_id = ( !WCUtils::is_blank($datas[USCES_COL_POST_ID]) ) ? (int)$datas[USCES_COL_POST_ID] : NULL;
				if( $post_id ) {
					$db_res = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $post_id) );
					if( !$db_res ) {
						$err_num++;
						$mes = "No.".$line_num."\t".sprintf(__("Post-ID %s does not exist in the database.", 'usces'), $post_id);
						$logtemp .= $mes.$yn;
						$log .= $logtemp;
						echo $mes.$br.$yn;
						continue;
					}
				}
				if( $post_id ) {
					$mode = 'upd';
				} else {
					$mode = 'add';
				}
			}

			$lineready = microtime(true);
			$linereadytime += $lineready - $linestart;
			/*////////////////////////////////////////*/
			// dataCheck 
			/*////////////////////////////////////////*/
			//data check loop
			foreach( $datas as $key => $data ) {
				$data = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($data, 'UTF-8', 'SJIS')) : trim($data);
				switch( $key ) {
					case USCES_COL_ITEM_CODE:
						if( 0 == strlen($data) ) {
							$mes = "No.".$line_num."\t".__('An item cord is non-input.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						} else {
							$query = $wpdb->prepare("SELECT meta_id, post_id FROM {$wpdb->postmeta} 
													LEFT JOIN {$wpdb->posts} ON ID = post_id
													WHERE meta_key = %s AND meta_value = %s AND (post_status = 'pending' OR post_status = 'publish' OR post_status = 'draft' OR post_status = 'private' OR post_status = 'future')", 
													'_itemCode', $data);
							$db_res1 = $wpdb->get_results( $query, ARRAY_A );
							if( 'upd' == $mode ) {
								if( $db_res1 && is_array( $db_res1 ) && 1 < count( $db_res1 ) ) {
									$mes = "No.".$line_num."\t".__('This Item-Code has been duplicated.', 'usces');
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
									$mes = '';
									foreach( $db_res1 as $res_val ) {
										$mes .= "meta_id=".$res_val['meta_id'].", post_id=".$res_val['post_id'].$br;
									}
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
								}
								$query = $wpdb->prepare("SELECT meta_id, post_id FROM {$wpdb->postmeta} 
														LEFT JOIN {$wpdb->posts} ON ID = post_id
														WHERE post_id <> %d AND meta_key = %s AND meta_value = %s AND (post_status = 'pending' OR post_status = 'publish' OR post_status = 'draft' OR post_status = 'private' OR post_status = 'future')", 
														$post_id, '_itemCode', $data);
								$db_res2 = $wpdb->get_results( $query, ARRAY_A );
								if( $db_res2 && is_array( $db_res2 ) && 0 < count( $db_res2 ) ) {
									$mes = "No.".$line_num."\t".__('This Item-Code has already been used.', 'usces');
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
									$mes = '';
									foreach( $db_res2 as $res_val ) {
										$mes .= "meta_id=".$res_val['meta_id'].", post_id=".$res_val['post_id'].$br;
									}
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
								}
							} else if( 'add' == $mode ) {
								if( $data != $pre_code ) {
									if( $db_res1 && is_array( $db_res1 ) && 0 < count( $db_res1 ) ) {
										$mes = "No.".$line_num."\t".__('This Item-Code has already been used.', 'usces');
										$logtemp .= $mes.$yn;
										$mestemp .= $mes.$br.$yn;
										$mes = '';
										foreach( $db_res1 as $res_val ) {
											$mes .= "meta_id=".$res_val['meta_id'].", post_id=".$res_val['post_id'].$br;
										}
										$logtemp .= $mes.$yn;
										$mestemp .= $mes.$br.$yn;
									}
								}
							}
						}
						break;
					case USCES_COL_ITEM_NAME:
						if( 0 == strlen($data) ) {
							$mes = "No.".$line_num."\t".__('An item name is non-input.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_RESTRICTION:
						if( !preg_match("/^[0-9]+$/", $data) && 0 != strlen($data) ) {
							$mes = "No.".$line_num."\t".__('A value of the purchase limit number is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_POINTRATE:
						if( !preg_match("/^[0-9]+$/", $data) ) {
							$mes = "No.".$line_num."\t".__('A value of the point rate is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_GPNUM1:
						if( !preg_match("/^[0-9]+$/", $data) ) {
							$mes = "No.".$line_num."\t".__('Business package discount', 'usces')."1-".__('umerical value is abnormality.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_GPDIS1:
						if( !preg_match("/^[0-9]+$/", $data) || ( 0 < $datas[USCES_COL_ITEM_GPNUM1] && 1 > $data ) ) {
							$mes = "No.".$line_num."\t".__('Business package discount', 'usces')."1-".__('rate is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_GPNUM2:
						if( !preg_match("/^[0-9]+$/", $data) || ($datas[USCES_COL_ITEM_GPNUM1] >= $data && 0 != $data) ) {
							$mes = "No.".$line_num."\t".__('Business package discount', 'usces')."2-".__('umerical value is abnormality.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_GPDIS2:
						if( !preg_match("/^[0-9]+$/", $data) || ( 0 < $datas[USCES_COL_ITEM_GPNUM2] && 1 > $data ) ) {
							$mes = "No.".$line_num."\t".__('Business package discount', 'usces')."2-".__('rate is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_GPNUM3:
						if( !preg_match("/^[0-9]+$/", $data) || ($datas[USCES_COL_ITEM_GPNUM2] >= $data && 0 != $data) ) {
							$mes = "No.".$line_num."\t".__('Business package discount', 'usces')."3-".__('umerical value is abnormality.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_GPDIS3:
						if( !preg_match("/^[0-9]+$/", $data) || ( 0 < $datas[USCES_COL_ITEM_GPNUM3] && 1 > $data ) ) {
							$mes = "No.".$line_num."\t".__('Business package discount', 'usces')."3-".__('rate is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_SHIPPING:
						if( !preg_match("/^[0-9]+$/", $data) || 9 < $data ) {
							$mes = "No.".$line_num."\t".__('A value of the shipment day is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_DELIVERYMETHOD:
						if( 0 === strlen($data) || !preg_match("/^[0-9;]+$/", $data) ) {
							$mes = "No.".$line_num."\t".__('Invalid value of Delivery method.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_SHIPPINGCHARGE:
						if( 0 === strlen($data) || !preg_match("/^[0-9]+$/", $data) ) {
							$mes = "No.".$line_num."\t".__('Invalid type of shipping charge.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_ITEM_INDIVIDUALSCHARGE:
						if( !preg_match("/^[0-9]+$/", $data) || 1 < $data ) {
							$mes = "No.".$line_num."\t".__('A value of the postage individual charging is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_POST_ID:
						if( !preg_match("/^[0-9]+$/", $data) && 0 != strlen($data) ) {
							$mes = "No.".$line_num."\t".__('A value of the Post-ID is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_POST_AUTHOR:
					case USCES_COL_POST_COMMENT_STATUS:
					case USCES_COL_POST_PASSWORD:
					case USCES_COL_POST_NAME:
					case USCES_COL_POST_TITLE:
					case USCES_COL_POST_CONTENT:
					case USCES_COL_POST_EXCERPT:
						break;
					case USCES_COL_POST_STATUS:
						$array17 = array( 'publish', 'future', 'draft', 'pending', 'private' );
						if( !in_array($data, $array17) || WCUtils::is_blank($data) ) {
							$mes = "No.".$line_num."\t".__('A value of the display status is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_POST_MODIFIED:
						if( 'future' == $datas[USCES_COL_POST_STATUS] && (WCUtils::is_blank($data) || '0000-00-00 00:00:00' === $data) ) {
							if( preg_match($date_pattern, $data, $match) ) {
								if( checkdate($match[2], $match[3], $match[1]) && 
											(0 < $match[4] && 24 > $match[4]) && 
											(0 < $match[5] && 60 > $match[5]) && 
											(0 < $match[6] && 60 > $match[6]) ) {
									$mes = "";
								} else {
									$mes = "No.".$line_num."\t".__('A value of the schedule is abnormal.', 'usces');
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
								}
							} else {
								$mes = "No.".$line_num."\t".__('A value of the schedule is abnormal.', 'usces');
								$logtemp .= $mes.$yn;
								$mestemp .= $mes.$br.$yn;
							}
						} else if( !WCUtils::is_blank($data) && '0000-00-00 00:00:00' !== $data ) {
							if( preg_match("/^[0-9]+$/", substr($data,0,4)) ) {//First 4 digits are numbers only.
								if( strtotime($data) === false ) {
									$mes = "No.".$line_num."\t".__('A value of the schedule is abnormal.', 'usces');
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
								}
							} else {
								$datetime = explode(' ', $data);
								$date_str = usces_dates_interconv($datetime[0]).' '.$datetime[1];
								if( strtotime($date_str) === false ) {
									$mes = "No.".$line_num."\t".__('A value of the schedule is abnormal.', 'usces');
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
								}
							}
						}
						break;
					case USCES_COL_CATEGORY:
						if( 0 == strlen($data) ) {
							$mes = "No.".$line_num."\t".__('A category is non-input.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_POST_TAG:
						break;
					case USCES_COL_CUSTOM_FIELD:
						break;
					case USCES_COL_SKU_CODE:
						if( 0 == strlen($data) ) {
							$mes = "No.".$line_num."\t".__('A SKU cord is non-input.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_SKU_NAME:
						break;
					case USCES_COL_SKU_CPRICE:
						if( 0 < strlen($data) && !preg_match("/^\d$|^\d+\.?\d+$/", $data) ) {
							$mes = "No.".$line_num."\t".__('A value of the normal price is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_SKU_PRICE:
						if( !preg_match("/^\d$|^\d+\.?\d+$/", $data) || 0 == strlen($data) ) {
							$mes = "No.".$line_num."\t".__('A value of the sale price is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_SKU_ZAIKONUM:
						if( 0 < strlen($data) ) {
							$itemOrderAcceptable = usces_get_item_custom_field_value( '_itemOrderAcceptable', $datas[USCES_COL_CUSTOM_FIELD] );
							if( '1' != $itemOrderAcceptable ) {
								if( !preg_match("/^[0-9]+$/", $data) ) {
									$mes = "No.".$line_num."\t".__('A value of the stock amount is abnormal.', 'usces');
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
								}
							} else {
								if( !preg_match("/^[-]?[0-9]+$/", $data) ) {
									$mes = "No.".$line_num."\t".__('A value of the stock amount is abnormal.', 'usces');
									$logtemp .= $mes.$yn;
									$mestemp .= $mes.$br.$yn;
								}
							}
						}
						break;
					case USCES_COL_SKU_ZAIKO:
						$stock_status = apply_filters( 'usces_filter_csv_upload_check_stock_status', $data );
						if( !preg_match("/^[0-9]+$/", $data) || $stock_status < $data ) {
							$mes = "No.".$line_num."\t".__('A value of the stock status is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
					case USCES_COL_SKU_UNIT:
						break;
					case USCES_COL_SKU_GPTEKIYO:
						if( !preg_match("/^[0-9]+$/", $data) || 1 < $data ) {
							$mes = "No.".$line_num."\t".__('The value of the duties pack application is abnormal.', 'usces');
							$logtemp .= $mes.$yn;
							$mestemp .= $mes.$br.$yn;
						}
						break;
				}
			}
			$opnum = ceil((count($datas) - $min_field_num) / 4);
			for( $i = 0; $i < $opnum; $i++ ) {
				$val = array();
				$oplogtemp = '';
				$opmestemp = '';
				for( $o = 1; $o <= 4; $o++ ) {
					$key = ($min_field_num - 1) + $o + ($i * 4);
					if( isset($datas[$key]) ) {
						$value = trim($datas[$key]);
					} else {
						$value = NULL;
					}
					switch( $o ) {
						case 1:
							if( empty($value) ) {
								$oplogtemp .= "No.".$line_num."\t".sprintf( __( 'Option name of No.%s option is non-input.', 'usces' ), ($i+1) ).$yn;
								$opmestemp .= "No.".$line_num."\t".sprintf( __( 'Option name of No.%s option is non-input.', 'usces' ), ($i+1) ).$br.$yn;
							}
							$val['name'] = $value;
							break;
						case 2:
							if( $value != NULL && (( 0 > (int)$value) || (5 < (int)$value)) ) {
								$oplogtemp .= "No.".$line_num."\t".sprintf( __( 'Option-entry-field of No.%s option is abnormal.', 'usces' ), ($i+1) ).$yn;
								$opmestemp .= "No.".$line_num."\t".sprintf( __( 'Option-entry-field of No.%s option is abnormal.', 'usces' ), ($i+1) ).$br.$yn;
							}
							$val['mean'] = $value;
							break;
						case 3:
							if( $value != NULL && (!preg_match("/^[0-9]+$/", $value) || 1 < (int)$value) ) {
								$oplogtemp .= "No.".$line_num."\t".sprintf( __( 'Option-required-item of No.%s option is abnormal.', 'usces' ), ($i+1) ).$yn;
								$opmestemp .= "No.".$line_num."\t".sprintf( __( 'Option-required-item of No.%s option is abnormal.', 'usces' ), ($i+1) ).$br.$yn;
							}
							$val['essential'] = $value;
							break;
						case 4:
							if( ($value != NULL && $value == '') && (2 > $datas[($key-2)] && 0 < strlen($datas[($key-2)])) ) {
								$oplogtemp .= "No.".$line_num."\t".sprintf( __( 'Option-select of No.%s option is non-input.', 'usces' ), ($i+1) ).$yn;
								$opmestemp .= "No.".$line_num."\t".sprintf( __( 'Option-select of No.%s option is non-input.', 'usces' ), ($i+1) ).$br.$yn;
							}
							$val['value'] = $value;
							break;
					}
				}
				if( !WCUtils::is_blank($val['name']) || !WCUtils::is_blank($val['mean']) || !WCUtils::is_blank($val['essential']) || !WCUtils::is_blank($val['value']) ) {
					$logtemp .= $oplogtemp;
					$mestemp .= $opmestemp;
				}
			}
			if( 0 < strlen($logtemp) ) {
				$err_num++;
				$log .= $logtemp;
				echo $mestemp;
				continue;
			}

			/******************/
			$checkend = microtime(true);
			$checktime += $checkend - $lineready;
			/*////////////////////////////////////////*/
			// insPost 
			/*////////////////////////////////////////*/
			//wp_posts data reg;
			$cdatas = array();
			$post_fields = array();
			$sku = array();
			$opt = array();
			$valstr = '';

			if( $pre_code != $item_code ) {
				$sku_index = 0;
				$current_date = current_time( 'mysql' );
				$current_date_gmt = current_time( 'mysql', 1 );
				$cdatas['ID'] = $post_id;

				$post_modified = $datas[USCES_COL_POST_MODIFIED];
				if( $post_modified == '' || $post_modified == '0000-00-00 00:00:00' ) {
					if( 'add' == $mode ) {
						$cdatas['post_date'] = $current_date;
						$cdatas['post_date_gmt'] = $current_date_gmt;
					}
					$cdatas['post_modified'] = $current_date;
					$cdatas['post_modified_gmt'] = $current_date_gmt;
				} else {
					if( preg_match("/^[0-9]+$/", substr($post_modified,0,4)) ) {//First 4 digits are numbers only.
						$time_data = strtotime($post_modified);
					} else {
						$datetime = explode(' ', $post_modified);
						$date_str = usces_dates_interconv( $datetime[0] ).' '.$datetime[1];
						$time_data = strtotime($date_str);
					}
					$difference = get_option( 'gmt_offset' ) * 60 * 60;
					$cdatas['post_date'] = date('Y-m-d H:i:s', $time_data);
					$cdatas['post_date_gmt'] = gmdate('Y-m-d H:i:s', ($time_data-$difference));
					$cdatas['post_modified'] = date('Y-m-d H:i:s', $time_data);
					$cdatas['post_modified_gmt'] = gmdate('Y-m-d H:i:s', ($time_data-$difference));
				}
				if( 'publish' == $datas[USCES_COL_POST_STATUS] ) {
					if( mysql2date('U', $cdatas['post_modified'], false) > mysql2date('U', $current_date, false) ) {
						$datas[USCES_COL_POST_STATUS] = 'future';
					}
				} elseif( 'future' == $datas[USCES_COL_POST_STATUS] ) {
					if( mysql2date('U', $cdatas['post_modified'], false) <= mysql2date('U', $current_date, false) ) {
						$datas[USCES_COL_POST_STATUS] = 'publish';
					}
				}
				$cdatas['ID'] = $post_id;
				$cdatas['post_author'] = ( !WCUtils::is_blank($datas[USCES_COL_POST_AUTHOR]) ) ? $datas[USCES_COL_POST_AUTHOR] : $user_ID;
				$cdatas['post_content'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[USCES_COL_POST_CONTENT], 'UTF-8', 'SJIS')) : trim($datas[USCES_COL_POST_CONTENT]);
				$cdatas['post_title'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[USCES_COL_POST_TITLE], 'UTF-8', 'SJIS')) : trim($datas[USCES_COL_POST_TITLE]);
				$cdatas['post_excerpt'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[USCES_COL_POST_EXCERPT], 'UTF-8', 'SJIS')) : trim($datas[USCES_COL_POST_EXCERPT]);
				$cdatas['post_status'] = $datas[USCES_COL_POST_STATUS];
				$cdatas['comment_status'] = ( !WCUtils::is_blank($datas[USCES_COL_POST_COMMENT_STATUS]) ) ? $datas[USCES_COL_POST_COMMENT_STATUS] : 'close';
				$cdatas['ping_status'] = 'close';
				$cdatas['post_password'] = ( 'private' == $cdatas['post_status'] ) ? '' : $datas[USCES_COL_POST_PASSWORD];
				$cdatas['post_type'] = 'post';
				$cdatas['post_parent'] = 0;
				$spname = ( $usces->options['system']['csv_encode_type'] == 0 ) ? sanitize_title(trim(mb_convert_encoding($datas[USCES_COL_POST_NAME], 'UTF-8', 'SJIS'))) : sanitize_title( trim($datas[USCES_COL_POST_NAME]) );
				$cdatas['post_name'] = wp_unique_post_slug($spname, $cdatas['ID'], $cdatas['post_status'], $cdatas['post_type'], $cdatas['post_parent']);
				$cdatas['to_ping'] = '';
				$cdatas['pinged'] = '';
				$cdatas['menu_order'] = 0;
				$cdatas['post_mime_type'] = 'item';
				$cdatas['post_content_filtered'] = '';

				if( empty($cdatas['post_name']) && !in_array( $cdatas['post_status'], array( 'draft', 'pending', 'auto-draft' ) ) ) {
					$cdatas['post_name'] = sanitize_title($cdatas['post_title'], $post_id);
				}

				$cfdata = array();
				$cfrows = ( $usces->options['system']['csv_encode_type'] == 0 ) ? explode( ';', trim(mb_convert_encoding($datas[USCES_COL_CUSTOM_FIELD], 'UTF-8', 'SJIS')) ) : explode(';', trim($datas[USCES_COL_CUSTOM_FIELD]));
				if( is_array( $cfrows ) &&  0 < count($cfrows) ) {
					reset($cfrows);

					foreach( $cfrows as $cfindex => $row ) {
						if( false !== strpos( $row, '=' ) ) {
							$cfdata[] = $row;
						}else{
							$cfdend = count($cfdata)-1;
							if( false !== strpos( $cfdata[$cfdend], ':{' ) && false === strpos( $cfdata[$cfdend], '}' ) ){
								$cfdata[$cfdend] = $cfdata[$cfdend].';'.$row;
							}
						}
					}
				}
				
				$cdatas = apply_filters( 'usces_filter_pre_registered_data', $cdatas, $datas );
				
				if( $mode == 'add' ) {

					$cdatas['guid'] = '';
					if( false === $wpdb->insert( $wpdb->posts, $cdatas ) ) {
						$err_num++;
						$mes = "No.".$line_num."\t".__('This data was not registered in the database.', 'usces');
						$log .= $mes.$yn;
						echo $mes.$br.$yn;
						$pre_code = $item_code;
						continue;
					}
					$post_id = $wpdb->insert_id;
					$where = array( 'ID' => $post_id );
					$wpdb->update( $wpdb->posts, array( 'guid' => get_permalink( $post_id ) ), $where );

					/******************/
					$insert_post = microtime(true);
					$insertposttime += $insert_post - $checkend;

				} elseif( $mode == 'upd' ) {

					$where = array( 'ID' => $post_id );
					if( false === $wpdb->update( $wpdb->posts, $cdatas, $where ) ) {
						$err_num++;
						$mes = "No.".$line_num."\t".__('The data were not registered with a database.', 'usces');
						$log .= $mes.$yn;
						echo $mes.$br.$yn;
						$pre_code = $item_code;
						continue;
					}

					/******************/
					$insert_post = microtime(true);
					$insertposttime += $insert_post - $checkend;
					//end of wp_insert_post

					/************************************************************************************************/
					/*////////////////////////////////////////*/
					// delMeta 
					/*////////////////////////////////////////*/

					//delete metas of Item only
					$meta_key_table = array( '_itemCode', '_itemName', '_itemRestriction', '_itemPointrate', '_itemGpNum1', '_itemGpDis1', '_itemGpNum2', '_itemGpDis2', '_itemGpNum3', '_itemGpDis3', '_itemShipping', '_itemDeliveryMethod', '_itemShippingCharge', '_itemIndividualSCharge', '_iopt_', '_isku_' );

					if( is_array( $cfrows ) &&  0 < count($cfrows) ) {
						reset($cfrows);

						foreach( $cfdata as $row ) {
							$cf = explode( '=', $row );
							if( !WCUtils::is_blank($cf[0]) ) {
								array_push( $meta_key_table, trim($cf[0]) );
							}
						}
					}
					$meta_key_table = apply_filters( 'usces_filter_uploadcsv_delete_postmeta', $meta_key_table );
					$query = $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ( %s ) AND post_id = %d", implode( "','", $meta_key_table ), $post_id );
					$query = stripslashes( $query );
					$db_res = $wpdb->query( $query );
					if( $db_res === false ) {
						$err_num++;
						$mes = "No.".$line_num."\t".__('Error : delete postmeta', 'usces');
						$log .= $mes.$yn;
						echo $mes.$br.$yn;
						$pre_code = $item_code;
						continue;
					}
					// delete Item wcct
					$query = $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND post_id = %d", 'wccs_%', $post_id );
					$db_res = $wpdb->query( $query );
					if( $db_res === false ) {
						$err_num++;
						$mes = "No.".$line_num."\t".__('Error : delete wcct', 'usces');
						$log .= $mes.$yn;
						echo $mes.$br.$yn;
						$pre_code = $item_code;
						continue;
					}
					// delete Item revisions
					$query = $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s", $post_id, 'revision' );
					$db_res = $wpdb->query( $query );
					if( $db_res === false ) {
						$err_num++;
						$mes = "No.".$line_num."\t".__('Error : delete revisions', 'usces');
						$log .= $mes.$yn;
						echo $mes.$br.$yn;
						$pre_code = $item_code;
						continue;
					}
				}

				/*********************/
				/*////////////////////////////////////////*/
				// publish_future_post 
				/*////////////////////////////////////////*/
				if( 'future' == $datas[USCES_COL_POST_STATUS] && $cdatas['post_date'] > current_time('Y-m-d H:i:s') ) {
					wp_clear_scheduled_hook( 'publish_future_post', array( $post_id ) );
					wp_schedule_single_event( strtotime( get_gmt_from_date( $cdatas['post_date'] ).' GMT'), 'publish_future_post', array( $post_id ) );
				}

				/******************/
				$metadelete = microtime(true);
				$metadeletetime += $metadelete - $insert_post;
				/*////////////////////////////////////////*/
				// addMeta 
				/*////////////////////////////////////////*/
				//add postmeta
				$itemOrderAcceptable = usces_get_item_custom_field_value( '_itemOrderAcceptable', $datas[USCES_COL_CUSTOM_FIELD] );
				$itemDeliveryMethod = explode(';', $datas[USCES_COL_ITEM_DELIVERYMETHOD]);
				if( $usces->options['system']['csv_encode_type'] == 0 ) {
					$valstr .= '('.$post_id.", '_itemCode','".esc_sql(trim(mb_convert_encoding($datas[USCES_COL_ITEM_CODE], 'UTF-8', 'SJIS')))."'),";
				} else {
					$valstr .= '('.$post_id.", '_itemCode','".esc_sql(trim($datas[USCES_COL_ITEM_CODE]))."'),";
				}
				if( $usces->options['system']['csv_encode_type'] == 0 ) {
					$valstr .= '('.$post_id.", '_itemName','".esc_sql(trim(mb_convert_encoding($datas[USCES_COL_ITEM_NAME], 'UTF-8', 'SJIS')))."'),";
				} else {
					$valstr .= '('.$post_id.", '_itemName','".esc_sql(trim($datas[USCES_COL_ITEM_NAME]))."'),";
				}
				$valstr .= '('.$post_id.", '_itemRestriction','".$datas[USCES_COL_ITEM_RESTRICTION]."'),";
				$valstr .= '('.$post_id.", '_itemPointrate','".$datas[USCES_COL_ITEM_POINTRATE]."'),";
				$valstr .= '('.$post_id.", '_itemGpNum1','".$datas[USCES_COL_ITEM_GPNUM1]."'),";
				$valstr .= '('.$post_id.", '_itemGpDis1','".$datas[USCES_COL_ITEM_GPDIS1]."'),";
				$valstr .= '('.$post_id.", '_itemGpNum2','".$datas[USCES_COL_ITEM_GPNUM2]."'),";
				$valstr .= '('.$post_id.", '_itemGpDis2','".$datas[USCES_COL_ITEM_GPDIS2]."'),";
				$valstr .= '('.$post_id.", '_itemGpNum3','".$datas[USCES_COL_ITEM_GPNUM3]."'),";
				$valstr .= '('.$post_id.", '_itemGpDis3','".$datas[USCES_COL_ITEM_GPDIS3]."'),";
				$valstr .= '('.$post_id.", '_itemShipping','".$datas[USCES_COL_ITEM_SHIPPING]."'),";
				$valstr .= '('.$post_id.", '_itemDeliveryMethod','".esc_sql(serialize($itemDeliveryMethod))."'),";
				$valstr .= '('.$post_id.", '_itemShippingCharge','".$datas[USCES_COL_ITEM_SHIPPINGCHARGE]."'),";
				$valstr .= '('.$post_id.", '_itemIndividualSCharge','".$datas[USCES_COL_ITEM_INDIVIDUALSCHARGE]."'),";

				$valstr = rtrim($valstr, ',');

				$db_res = $wpdb->query("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES {$valstr}");

				//add term_relationships, edit term_taxonomy
				//category
				if( $category_format_slug ) {
					$categories = array();
					$category_slug = explode( ';', $datas[USCES_COL_CATEGORY] );
					foreach( (array)$category_slug as $slug ) {
						$categories[] = usces_get_cat_id( $slug );
					}
				} else {
					$categories = explode(';', $datas[USCES_COL_CATEGORY]);
				}
				wp_set_post_categories( $post_id, $categories );

				//tag
				$tags = ( $usces->options['system']['csv_encode_type'] == 0 ) ? explode(';', trim(mb_convert_encoding($datas[USCES_COL_POST_TAG], 'UTF-8', 'SJIS'))) : explode(';', trim($datas[USCES_COL_POST_TAG]));
				wp_set_post_tags( $post_id, $tags );

				/*////////////////////////////////////////*/
				// Add Custom Field 
				/*////////////////////////////////////////*/
				if( is_array( $cfdata ) && 0 <= count($cfdata) ) {
					reset($cfdata);
					$cfstr = '';

					foreach( $cfdata as $row ) {
						$cf = explode( '=', $row );
						if( !WCUtils::is_blank($cf[0]) ) {
							$cfstr .= '('.$post_id.", '".$cf[0]."','".$cf[1]."'),";
						}
					}
					
					if( !WCUtils::is_blank($cfstr) ) {
						$cfstr = rtrim($cfstr, ',');
						$db_res = $wpdb->query("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES {$cfstr}");
					}
				}

				do_action( 'usces_action_uploadcsv_itemvalue', $post_id, $datas, $add_field_num );

				/******************/
				$add_postmeta = microtime(true);
				$addmetatime += $add_postmeta - $metadelete;
				/*////////////////////////////////////////*/
				// addOption 
				/*////////////////////////////////////////*/
				// Add Item Option
				for( $i = 0; $i < $opnum; $i++ ) {
					$opflg = true;
					$optvalue = array();
					for( $o = 1; $o <= 4; $o++ ) {
						$key = ($min_field_num - 1) + $o + ($i * 4);
						if( $o === 1 && $datas[$key] == '' ) {
							$opflg = false;
							break 1;
						}
						switch( $o ) {
							case 1:
								$optvalue['name'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[$key], 'UTF-8', 'SJIS')) : trim($datas[$key]);
								break;
							case 2:
								$optvalue['means'] = (int)$datas[$key];
								break;
							case 3:
								$optvalue['essential'] = (int)$datas[$key];
								break;
							case 4:
								if( !empty($datas[$key]) ) {
									$cr = array("\r\n", "\r");
									$datavalue = trim($datas[$key]);
									$datavalue = str_replace($cr, "", $datavalue);
									$optvalue['value'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? str_replace(';', "\n", mb_convert_encoding($datavalue, 'UTF-8', 'SJIS')) : str_replace(';', "\n", $datavalue);
								} else {
									$optvalue['value'] = "";
								}
								break;
						}
					}

					if( $opflg && !empty($optvalue) ) {
						$optvalue['sort'] = $i;
						$resopt = usces_add_opt($post_id, $optvalue, false);
					}
				}

			} else {
				$sku_index++;
			}

			/******************/
			$optionmeta = microtime(true);
			$AddOptiontime += $optionmeta - $add_postmeta;
			/*////////////////////////////////////////*/
			// addSku 
			/*////////////////////////////////////////*/
			// Add Item SKU
			$skuvalue = array();
			$sku_code = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[USCES_COL_SKU_CODE], 'UTF-8', 'SJIS')) : trim($datas[USCES_COL_SKU_CODE]);
			$skuvalue['code'] = $sku_code;
			$skuvalue['name'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[USCES_COL_SKU_NAME], 'UTF-8', 'SJIS')) : trim($datas[USCES_COL_SKU_NAME]);
			$skuvalue['cprice'] = $datas[USCES_COL_SKU_CPRICE];
			$skuvalue['price'] = $datas[USCES_COL_SKU_PRICE];
			$skuvalue['unit'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[USCES_COL_SKU_UNIT], 'UTF-8', 'SJIS')) : trim($datas[USCES_COL_SKU_UNIT]);
			$skuvalue['stocknum'] = $datas[USCES_COL_SKU_ZAIKONUM];
			$skuvalue['stock'] = $datas[USCES_COL_SKU_ZAIKO];
			$skuvalue['gp'] = $datas[USCES_COL_SKU_GPTEKIYO];
			$skuvalue['sort'] = $sku_index;
			if( usces_is_reduced_taxrate() ) {
				$skuvalue['taxrate'] = usces_csv_set_sku_applicable_taxrate( $datas[USCES_COL_SKU_APPLICABLE_TAXRATE] );
			}
			$skuvalue = apply_filters( 'usces_filter_uploadcsv_skuvalue', $skuvalue, $datas );
			usces_add_sku( $post_id, $skuvalue, false );

			$comp_num++;
			$pre_code = $item_code;
			clean_post_cache($post_id);
			wp_cache_delete($post_id, 'posts');
			wp_cache_delete($post_id, 'post_meta');
			clean_object_term_cache($post_id, 'post');

			/******************/
			$addsku = microtime(true);
			$AddSKUtime += $addsku - $optionmeta;
			$onelinetime += $addsku - $linestart;

			if( 0 === ($line_num % 10) ) {
				$av = $onelinetime / $rows_num;
				$nt = round($av * ($total_num - $rows_num));
				if( 60 < $nt ) {
					$mtime = ceil($nt / 60).__('min', 'usces').($nt % 60).__('sec', 'usces').' ';
				} else {
					$mtime = $nt.__('sec', 'usces').' ';
				}
				echo '<script type="text/javascript">changeMsg("'.__('Processing...', 'usces').__('Remaining time:', 'usces').$mtime.'");</script>'.$yn;
				echo '<script type="text/javascript">setProgress('.$line_num.','.$total_num.');</script>'.$yn;
				ob_flush();
				flush();
			}
		}
	}

	if( $fpi ) {
		flock($fpi, LOCK_EX);
		fputs($fpi, mb_convert_encoding($log, 'SJIS', 'UTF-8'));
		flock($fpi, LOCK_UN);
		fclose($fpi);
	}

	$res['status'] = 'success';
	$res['message'] = sprintf(__('%2$s of %1$s lines registration completion, error on %3$s lines.', 'usces'), $total_num, $comp_num, $err_num);
	/******************/
	$finish = microtime(true);

	if( $fpi ) {
		usces_log('ready     : '.round($readytime - $start, 4), 'itemcsv_log.txt');
		usces_log('lineReady : '.round($linereadytime / $total_num, 4), 'itemcsv_log.txt');
		usces_log('dataCheck : '.round($checktime / $total_num, 4), 'itemcsv_log.txt');
		usces_log('insPost   : '.round($insertposttime / $total_num, 4), 'itemcsv_log.txt');
		usces_log('delMeta   : '.round($metadeletetime / $total_num, 4), 'itemcsv_log.txt');
		usces_log('addMeta   : '.round($addmetatime / $total_num, 4), 'itemcsv_log.txt');
		usces_log('addOption : '.round($AddOptiontime / $total_num, 4), 'itemcsv_log.txt');
		usces_log('addSku    : '.round($AddSKUtime / $total_num, 4), 'itemcsv_log.txt');
		usces_log('oneline   : '.round($onelinetime / $total_num, 4), 'itemcsv_log.txt');
		usces_log('finish    : '.round($finish - $start, 4), 'itemcsv_log.txt');
		usces_log('totalLines: '.$total_num, 'itemcsv_log.txt');
		usces_log('--------------------------------------------------------------', 'itemcsv_log.txt');
	}

	echo '<script type="text/javascript">changeMsg("'.sprintf(__('Successful %1$s lines, Failed %2$s lines.', 'usces'), $comp_num, $err_num).' ");setProgress('.$line_num.','.$total_num.');</script>'.$yn;
	if( $log ) {

	} else {
		echo __('Finished', 'usces').$yn;
	}
	echo '</div>'.$yn;
	unlink($path.$file_name);
	return $res;
}
endif;

// item list download
if( !function_exists('usces_download_item_list') ):
function usces_download_item_list() {
	require_once( USCES_PLUGIN_DIR."/classes/itemList.class.php" );
	global $wpdb, $usces;

	$ext = 'csv';
	$table_h = "";
	$table_f = "";
	$tr_h = "";
	$tr_f = "";
	$th_h1 = '"';
	$th_h = ',"';
	$th_f = '"';
	$td_h1 = '"';
	$td_h = ',"';
	$td_f = '"';
	$sp = ";";
	$eq = "=";
	$lf = "\n";
	$end = '';

	//==========================================================================

	$usces_opt_item = get_option('usces_opt_item');
	if( !is_array($usces_opt_item) ) {
		$usces_opt_item = array();
	}
	$usces_opt_item['chk_header'] = ( isset($_REQUEST['chk_header']) ) ? 1 : 0;
	$usces_opt_item['ftype_item'] = $ext;
	update_option('usces_opt_item', $usces_opt_item);

	//==========================================================================

	$tableName = $wpdb->posts;
	$arr_column = array(
				__('item code', 'usces') => 'item_code', 
				__('item name', 'usces') => 'item_name', 
				__('SKU code', 'usces') => 'sku_key', 
				__('selling price', 'usces') => 'price', 
				__('stock', 'usces') => 'zaiko_num', 
				__('stock status', 'usces') => 'zaiko', 
				__('Categories', 'usces') => 'category', 
				__('display status', 'usces') => 'display_status');

	$_REQUEST['searchIn'] = "searchIn"; 
	$DT = new dataList($tableName, $arr_column);
	$DT->pageLimit = 'off';
	$DT->exportMode = true;
	$res = $DT->MakeTable();
	$rows = $DT->rows;

	//==========================================================================

	$results = apply_filters( 'usces_filter_item_downloadcsv_mode', array(), $rows, $usces_opt_item );
	if( !empty( $results ) ) {
		extract( $results );

	} elseif( isset( $_REQUEST['mode'] ) && 'stock' == $_REQUEST['mode'] ) {
		$results = usces_download_item_stock_list( $rows, $usces_opt_item );
		if( !empty( $results ) ) {
			extract( $results );
		}

	} elseif( isset( $_REQUEST['mode'] ) && 'sku' == $_REQUEST['mode'] ) {
		$results = usces_download_item_sku_list( $rows, $usces_opt_item );
		if( !empty( $results ) ) {
			extract( $results );
		}

	} elseif( isset( $_REQUEST['mode'] ) && 'meta' == $_REQUEST['mode'] ) {
		$results = usces_download_item_meta_list( $rows, $usces_opt_item );
		if( !empty( $results ) ) {
			extract( $results );
		}

	} else {

		$line = $table_h;
		if( $usces_opt_item['chk_header'] == 1 ) {
			$line .= $tr_h;
			$line .= $th_h1.'Post ID'.$th_f;
			$line .= $th_h.__('Post Author', 'usces').$th_f;
			$line .= $th_h.__('explanation', 'usces').$th_f;
			$line .= $th_h.__('Title', 'usces').$th_f;
			$line .= $th_h.__('excerpt', 'usces').$th_f;
			$line .= $th_h.__('display status', 'usces').$th_f;
			$line .= $th_h.__('Comment Status', 'usces').$th_f;
			$line .= $th_h.__('Post Password', 'usces').$th_f;
			$line .= $th_h.__('Post Name', 'usces').$th_f;
			$line .= $th_h.__('Publish date', 'usces').$th_f;

			$line .= $th_h.__('item code', 'usces').$th_f;
			$line .= $th_h.__('item name', 'usces').$th_f;
			$line .= $th_h.__('Limited amount for purchase', 'usces').$th_f;
			$line .= $th_h.__('Percentage of points', 'usces').$th_f;
			$line .= $th_h.__('Business package discount', 'usces').'1-'.__('num', 'usces').$th_f.$th_h.__('Business package discount', 'usces').'1-'.__('rate', 'usces').$th_f;
			$line .= $th_h.__('Business package discount', 'usces').'2-'.__('num', 'usces').$th_f.$th_h.__('Business package discount', 'usces').'2-'.__('rate', 'usces').$th_f;
			$line .= $th_h.__('Business package discount', 'usces').'3-'.__('num', 'usces').$th_f.$th_h.__('Business package discount', 'usces').'3-'.__('rate', 'usces').$th_f;
			$line .= $th_h.__('estimated shipping date', 'usces').$th_f;
			$line .= $th_h.__('shipping option', 'usces').$th_f;
			$line .= $th_h.__('Shipping', 'usces').$th_f;
			$line .= $th_h.__('Postage individual charging', 'usces').$th_f;

			$line .= $th_h.__('Categories', 'usces').$th_f;
			$line .= $th_h.__('tag', 'usces').$th_f;
			$line .= $th_h.__('Custom Field', 'usces').$th_f;

			$line .= apply_filters( 'usces_filter_downloadcsv_itemheader', '' );
			$line = apply_filters( 'usces_filter_downloadcsv_add_itemheader', $line );

			$line .= $th_h.__('SKU code', 'usces').$th_f;
			$line .= $th_h.__('SKU display name ', 'usces').$th_f;
			$line .= $th_h.__('normal price', 'usces').$th_f;
			$line .= $th_h.__('Sale price', 'usces').$th_f;
			$line .= $th_h.__('stock', 'usces').$th_f;
			$line .= $th_h.__('stock status', 'usces').$th_f;
			$line .= $th_h.__('unit', 'usces').$th_f;
			$line .= $th_h.__('Apply business package', 'usces').$th_f;
			if( usces_is_reduced_taxrate() ) {
				$line .= $th_h.__( 'Applicable tax rate', 'usces' ).$th_f;
			}
			$line .= apply_filters( 'usces_filter_downloadcsv_header', '' );
			$line = apply_filters( 'usces_filter_downloadcsv_add_header', $line );
			$line .= $th_h.__('option name', 'usces').$th_f.$th_h.__('Field type', 'usces').$th_f.$th_h.__('Required', 'usces').$th_f.$th_h.__('selected amount', 'usces').$th_f;
			$line .= $tr_f.$lf;
		}

		//==========================================================================

		mb_http_output('pass');
		set_time_limit(3600);
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=usces_item_list.".$ext);
		@ob_end_flush();
		flush();
		$category_format_slug = ( isset($usces->options['system']['csv_category_format']) && 1 == $usces->options['system']['csv_category_format'] ) ? true : false;
		$item_custom_fields = usces_get_item_custom_fields();

		foreach( (array)$rows as $row ) {
			$post_id = $row['ID'];
			$post = get_post($post_id);

			//Post Data
			$line_item  = $td_h1.$post->ID.$td_f;
			$line_item .= $td_h.$post->post_author.$td_f;
			$line_item .= $td_h.usces_entity_decode($post->post_content, $ext).$td_f;
			$line_item .= $td_h.usces_entity_decode($post->post_title, $ext).$td_f;
			$line_item .= $td_h.usces_entity_decode($post->post_excerpt, $ext).$td_f;
			$line_item .= $td_h.$post->post_status.$td_f;
			$line_item .= $td_h.$post->comment_status.$td_f;
			$line_item .= $td_h.$post->post_password.$td_f;
			$line_item .= $td_h.urldecode($post->post_name).$td_f;
			$line_item .= $td_h.$post->post_date.$td_f;

			//Item Meta
			$line_item .= $td_h.$usces->getItemCode($post_id).$td_f;
			$line_item .= $td_h.usces_entity_decode($usces->getItemName($post_id), $ext).$td_f;
			$line_item .= $td_h.$usces->getItemRestriction($post_id).$td_f;
			$line_item .= $td_h.$usces->getItemPointrate($post_id).$td_f;
			$line_item .= $td_h.$usces->getItemGpNum1($post_id).$td_f.$td_h.$usces->getItemGpDis1($post_id).$td_f;
			$line_item .= $td_h.$usces->getItemGpNum2($post_id).$td_f.$td_h.$usces->getItemGpDis2($post_id).$td_f;
			$line_item .= $td_h.$usces->getItemGpNum3($post_id).$td_f.$td_h.$usces->getItemGpDis3($post_id).$td_f;
			$line_item .= $td_h.$usces->getItemShipping($post_id).$td_f;

			$delivery_method = '';
			$itemDeliveryMethod = $usces->getItemDeliveryMethod($post_id);
			foreach( (array)$itemDeliveryMethod as $k => $v ) {
				$delivery_method .= $v.$sp;
			}
			$delivery_method = rtrim($delivery_method, $sp);
			$line_item .= $td_h.$delivery_method.$td_f;
			$line_item .= $td_h.$usces->getItemShippingCharge($post_id).$td_f;
			$line_item .= $td_h.$usces->getItemIndividualSCharge($post_id).$td_f;

			//Categories
			$category = '';
			$cat_ids = wp_get_post_categories($post_id);
			if( !empty($cat_ids) ) {
				if( $category_format_slug ) {
					foreach( $cat_ids as $id ) {
						$cat = get_category( $id );
						$category .= $cat->slug.$sp;
					}
				} else {
					foreach( $cat_ids as $id ) {
						$category .= $id.$sp;
					}
				}
				$category = rtrim($category, $sp);
			}
			$line_item .= $td_h.$category.$td_f;

			//Tags
			$tag = '';
			$tags_ob = wp_get_object_terms($post_id, 'post_tag');
			foreach( $tags_ob as $ob ) {
				$tag .= $ob->name.$sp;
			}
			$tag = rtrim($tag, $sp);
			$line_item .= $td_h.$tag.$td_f;

			//Custom Fields
			$icfield = '';
			if( $item_custom_fields && is_array($item_custom_fields) && 0 < count($item_custom_fields) ) {
				foreach( $item_custom_fields as $key ) {
					$values = get_post_meta( $post_id, $key, true );
					if( is_array($values) ) {
						foreach( $values as $value ) {
							$icfield .= usces_entity_decode($key, $ext).$eq.usces_entity_decode($value, $ext).$sp;
						}
					} else {
						$icfield .= usces_entity_decode($key, $ext).$eq.usces_entity_decode($values, $ext).$sp;
					}
				}
			}
			$cfield = '';
			$custom_fields = $usces->get_post_user_custom($post_id);
			if( $custom_fields && is_array($custom_fields) && 0 < count($custom_fields) ) {
				foreach( $custom_fields as $cfkey => $cfvalues ) {
					if( is_array($cfvalues) ) {
						foreach( $cfvalues as $value ) {
							$cfield .= usces_entity_decode($cfkey, $ext).$eq.usces_entity_decode($value, $ext).$sp;
						}
					} else {
						$cfield .= usces_entity_decode($cfkey, $ext).$eq.usces_entity_decode($cfvalues, $ext).$sp;
					}
				}
				$cfield = rtrim($cfield, $sp);
			}
			$line_item .= $td_h.$icfield.$cfield.$td_f;

			$line_item .= apply_filters( 'usces_filter_downloadcsv_itemvalue', '', $post_id );
			$line_item = apply_filters( 'usces_filter_downloadcsv_add_itemvalue', $line_item, $post_id, $post );

			//Item Options
			$line_options = '';
			$option_meta = usces_get_opts( $post_id, 'sort' );
			foreach( $option_meta as $option_value ) {
				$value = '';
				if( is_array($option_value['value']) ) {
					foreach( $option_value['value'] as $k => $v ) {
						$v = usces_change_line_break( $v );
						$values = explode("\n", $v);
						foreach( $values as $val ) {
							$value .= $val.$sp;
						}
					}
					$value = rtrim($value, $sp);
				} else {
					$value = usces_change_line_break( $option_value['value'] );
					$value = str_replace("\n", ';', $value);
				}
				$line_options .= $td_h.usces_entity_decode($option_value['name'], $ext).$td_f;
				$line_options .= $td_h.$option_value['means'].$td_f;
				$line_options .= $td_h.$option_value['essential'].$td_f;
				$line_options .= $td_h.usces_entity_decode($value, $ext).$td_f;
			}

			//SKU
			$sku_meta = $usces->get_skus( $post_id, 'sort' );
			foreach( $sku_meta as $sku_value ) {
				$line_sku  = $td_h.$sku_value['code'].$td_f;
				$line_sku .= $td_h.usces_entity_decode($sku_value['name'], $ext).$td_f;
				$line_sku .= $td_h.usces_crform($sku_value['cprice'], false, false, 'return', false).$td_f;
				$line_sku .= $td_h.usces_crform($sku_value['price'], false, false, 'return', false).$td_f;
				$line_sku .= $td_h.$sku_value['stocknum'].$td_f;
				$line_sku .= $td_h.$sku_value['stock'].$td_f;
				$line_sku .= $td_h.usces_entity_decode($sku_value['unit'], $ext).$td_f;
				$line_sku .= $td_h.$sku_value['gp'].$td_f;
				if( usces_is_reduced_taxrate() ) {
					$line_sku .= $td_h.usces_csv_get_sku_applicable_taxrate( $sku_value ).$td_f;
				}
				$line_sku .= apply_filters( 'usces_filter_downloadcsv_skuvalue', '', $sku_value );
				$line_sku = apply_filters( 'usces_filter_downloadcsv_add_skuvalue', $line_sku, $sku_value );
				$line .= $tr_h.$line_item.$line_sku.$line_options.$tr_f.$lf;
			}
			if( $usces->options['system']['csv_encode_type'] == 0 ) {
				$line = mb_convert_encoding( $line, apply_filters( 'usces_filter_output_csv_encode', 'SJIS-win'), "UTF-8" );
			}
			print($line);

			if( ob_get_contents() ) {
				ob_flush();
				flush();
			}
			$line = '';
			wp_cache_flush();
		}
		$end = $table_f.$lf;
	}

	//==========================================================================

	if( $usces->options['system']['csv_encode_type'] == 0 ) {
		$end = mb_convert_encoding( $end, apply_filters( 'usces_filter_output_csv_encode', 'SJIS-win'), "UTF-8" );
	}
	print($end);
	unset($rows, $DT, $line, $line_item, $line_options, $line_sku);
	exit();
}
endif;

// item stock list upload
function usces_item_stock_uploadcsv( $lines ) {
	global $wpdb, $usces;

	$comp_num = 0;
	$err_num = 0;
	$line_num = 0;
	$log = '';
	$res = array();
	$linereadytime = 0;
	$checktime = 0;
	$insertposttime = 0;
	$metadeletetime = 0;
	$addmetatime = 0;
	$AddOptiontime = 0;
	$AddSKUtime = 0;
	$onelinetime = 0;
	$yn = "\r\n";
	$br = "<br />";
	$sp = ",";

	$total_num = count($lines);
	$readytime = microtime(true);

	foreach( $lines as $rows_num => $line ) {

		$linestart = microtime(true);
		$datas = array();

		$logtemp = '';
		$mestemp = '';
		$line = trim($line);
		if( empty($line) ) {
			continue;
		}

		$d = explode($sp, $line);
		$buf = '';
		foreach( $d as $data ) {
			$num = substr_count($data, '"');
			if( 0 == $num % 2 && '' == $buf ) {
				if( '"' == substr($data, 0, 1) ) {
					$data = substr($data, 1);
				}
				if( '"' == substr($data, -1) ) {
					$data = substr($data, 0, -1);
				}
				$data = str_replace(array('""'), '"', $data);
				$datas[] = ( false !== $data ) ? $data : '';
			} elseif( 1 == $num % 2 && '' == $buf ) {
				$buf .= $data;
			} elseif( 0 == $num % 2 && '' != $buf ) {
				$buf .= $sp.$data;
			} elseif( 1 == $num % 2 && '' != $buf ) {
				$buf .= $sp.$data;
				if( '"' == substr($buf, 0, 1) ) {
					$buf = substr($buf, 1);
				}
				if( '"' == substr($buf, -1) ) {
					$buf = substr($buf, 0, -1);
				}
				$buf = str_replace(array('""'), '"', $buf);
				$datas[] = ( false !== $buf ) ? $buf : '';
				$buf = '';
			}
		}

		if( 'Post ID' == $datas[0] ) {
			continue;
		}

		$line_num = $rows_num + 1;

		if( 7 > count($datas) ) {
			$err_num++;
			$mes = "No.".$line_num." ".count($datas)."\t".__('The number of the columns is abnormal.', 'usces');
			$logtemp .= $mes.$yn;
			$log .= $logtemp;
			echo $mes.$br.$yn;
			continue;
		}

		$lineready = microtime(true);
		$linereadytime += $lineready-$linestart;

		foreach( $datas as $key => $data ) {
			switch( $key ) {
			case 0://POST_ID
				$post_id = ( !WCUtils::is_blank($data) ) ? (int)$data : NULL;
				if( $post_id ) {
					$db_res = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $post_id) );
					if( !$db_res ) {
						$err_num++;
						$mes = "No.".$line_num."\t".sprintf(__("Post-ID %s does not exist in the database.", 'usces'), $post_id);
						$logtemp .= $mes.$yn;
						$log .= $logtemp;
					}
				} else {
					$err_num++;
					$mes = "No.".$line_num."\t".__('A value of the Post-ID is abnormal.', 'usces');
					$logtemp .= $mes.$yn;
					$log .= $logtemp;
				}
				break;
			case 3://SKU CODE
				if( 0 == strlen($data) ) {
					$mes = "No.".$line_num."\t".__('A SKU cord is non-input.', 'usces');
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			case 5://STOCK NUM
				if( 0 < strlen($data) && !preg_match("/^[0-9]+$/", $data) ) {
					$mes = "No.".$line_num."\t".__('A value of the stock amount is abnormal.', 'usces');
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			case 6://STOCK STATUS
				$stock_status = apply_filters( 'usces_filter_csv_upload_check_stock_status', $data );
				if( !preg_match("/^[0-9]+$/", $data) || $stock_status < $data ) {
					$mes = "No.".$line_num."\t".__('A value of the stock status is abnormal.', 'usces');
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			}
		}

		if( 0 < strlen($logtemp) ) {
			$err_num++;
			$log .= $logtemp;
			echo $mestemp;
			continue;
		}

		$checkend = microtime(true);
		$checktime += $checkend - $lineready;
		$insert_post = microtime(true);
		$insertposttime += $insert_post - $checkend;
		$metadelete = microtime(true);
		$metadeletetime += $metadelete - $insert_post;
		$add_postmeta = microtime(true);
		$addmetatime += $add_postmeta - $metadelete;
		$optionmeta = microtime(true);
		$AddOptiontime += $optionmeta - $add_postmeta;

		$sku_code = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[3], 'UTF-8', 'SJIS')) : trim($datas[3]);

		$sku_meta = usces_get_post_meta( $post_id, '_isku_' );
		if( !$sku_meta ) {
			$err_num++;
			$mes = "No.".$line_num."\t".__('This data was not registered in the database.', 'usces');
			$log .= $mes.$yn;
			echo $mes.$br.$yn;
			continue;
		}

		foreach( $sku_meta as $meta ) {
			$sku = unserialize($meta['meta_value']);
			if( $sku_code == urldecode($sku['code']) ) {
				$sku['stocknum'] = $datas[5];
				$sku['stock'] = $datas[6];
				$query = $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d", serialize($sku), $meta['meta_id'] );
				$db_res = $wpdb->query( $query );
				if( 0 > $db_res ) {
					$err_num++;
					$mes = "No.".$line_num."\t".__('This data was not registered in the database.', 'usces');
					$log .= $mes.$yn;
					echo $mes.$br.$yn;
					continue;
				}
				break;
			}
		}

		$comp_num++;
		clean_post_cache($post_id);
		wp_cache_delete($post_id, 'posts');
		wp_cache_delete($post_id, 'post_meta');
		clean_object_term_cache($post_id, 'post');

		/******************/
		$addsku = microtime(true);
		$AddSKUtime += $addsku - $optionmeta;
		$onelinetime += $addsku - $linestart;

		if( 0 === ($line_num % 10) ) {
			$av = $onelinetime / $rows_num;
			$nt = round($av * ($total_num - $rows_num));
			if( 60 < $nt ) {
				$mtime = ceil($nt / 60).__('min', 'usces').($nt % 60).__('sec', 'usces').' ';
			} else {
				$mtime = $nt.__('sec', 'usces').' ';
			}
			echo '<script type="text/javascript">changeMsg("'.__('Processing...', 'usces').__('Remaining time:', 'usces').$mtime.'");</script>'.$yn;
			echo '<script type="text/javascript">setProgress('.$line_num.','.$total_num.');</script>'.$yn;
			ob_flush();
			flush();
		}
	}

	$results = compact( 'res', 'log', 'total_num', 'comp_num', 'err_num', 'readytime', 'linereadytime', 'insertposttime', 'metadeletetime', 'addmetatime', 'AddOptiontime', 'AddSKUtime', 'onelinetime', 'line_num' );

	return $results;
}

// item stock list download
function usces_download_item_stock_list( $rows, $usces_opt_item ) {
	global $usces;

	$ext = 'csv';
	$table_h = "";
	$table_f = "";
	$tr_h = "";
	$tr_f = "";
	$th_h1 = '"';
	$th_h = ',"';
	$th_f = '"';
	$td_h1 = '"';
	$td_h = ',"';
	$td_f = '"';
	$sp = ";";
	$eq = "=";
	$lf = "\n";
	$end = '';

	$line = '';

	if( $usces_opt_item['chk_header'] == 1 ) {
		$line .= $tr_h;
		$line .= $th_h1.'Post ID'.$th_f;
		$line .= $th_h.__('item code', 'usces').$th_f;
		$line .= $th_h.__('item name', 'usces').$th_f;
		$line .= $th_h.__('SKU code', 'usces').$th_f;
		$line .= $th_h.__('SKU display name ', 'usces').$th_f;
		$line .= $th_h.__('stock', 'usces').$th_f;
		$line .= $th_h.__('stock status', 'usces').$th_f;
		$line .= $tr_f.$lf;
	}

	//==========================================================================

	mb_http_output('pass');
	set_time_limit(3600);
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=usces_item_list.".$ext);
	@ob_end_flush();
	flush();

	foreach( (array)$rows as $row ) {
		$post_id = $row['ID'];

		//Post Data
		$line_item = $td_h1.$post_id.$td_f;

		//Item Meta
		$line_item .= $td_h.$usces->getItemCode($post_id).$td_f;
		$line_item .= $td_h.usces_entity_decode($usces->getItemName($post_id), $ext).$td_f;

		//SKU
		$sku_meta = $usces->get_skus( $post_id, 'sort' );
		foreach( $sku_meta as $sku_value ) {
			$line_sku = $td_h.$sku_value['code'].$td_f;
			$line_sku .= $td_h.usces_entity_decode($sku_value['name'], $ext).$td_f;
			$line_sku .= $td_h.$sku_value['stocknum'].$td_f;
			$line_sku .= $td_h.$sku_value['stock'].$td_f;
			$line .= $tr_h.$line_item.$line_sku.$tr_f.$lf;
		}
		if( $usces->options['system']['csv_encode_type'] == 0 ) {
			$line = mb_convert_encoding($line, apply_filters( 'usces_filter_output_csv_encode', 'SJIS-win'), "UTF-8");
		}
		print($line);

		if( ob_get_contents() ) {
			ob_flush();
			flush();
		}
		$line = '';
		wp_cache_flush();
	}
	$end = $table_f.$lf;

	$results = compact( 'end' );

	return $results;
}

// item sku list upload
function usces_item_sku_uploadcsv( $lines ) {
	global $wpdb, $usces;

	$comp_num = 0;
	$err_num = 0;
	$line_num = 0;
	$log = '';
	$res = array();
	$linereadytime = 0;
	$checktime = 0;
	$insertposttime = 0;
	$metadeletetime = 0;
	$addmetatime = 0;
	$AddOptiontime = 0;
	$AddSKUtime = 0;
	$onelinetime = 0;
	$yn = "\r\n";
	$br = "<br />";
	$sp = ",";

	$total_num = count($lines);
	$readytime = microtime(true);

	foreach( $lines as $rows_num => $line ) {

		$linestart = microtime(true);
		$datas = array();

		$logtemp = '';
		$mestemp = '';
		$line = trim($line);
		if( empty($line) ) {
			continue;
		}

		$d = explode($sp, $line);
		$buf = '';
		foreach( $d as $data ) {
			$num = substr_count($data, '"');
			if( 0 == $num % 2 && '' == $buf ) {
				if( '"' == substr($data, 0, 1) ) {
					$data = substr($data, 1);
				}
				if( '"' == substr($data, -1) ) {
					$data = substr($data, 0, -1);
				}
				$data = str_replace(array('""'), '"', $data);
				$datas[] = ( false !== $data ) ? $data : '';
			} elseif( 1 == $num % 2 && '' == $buf ) {
				$buf .= $data;
			} elseif( 0 == $num % 2 && '' != $buf ) {
				$buf .= $sp.$data;
			} elseif( 1 == $num % 2 && '' != $buf ) {
				$buf .= $sp.$data;
				if( '"' == substr($buf, 0, 1) ) {
					$buf = substr($buf, 1);
				}
				if( '"' == substr($buf, -1) ) {
					$buf = substr($buf, 0, -1);
				}
				$buf = str_replace(array('""'), '"', $buf);
				$datas[] = ( false !== $buf ) ? $buf : '';
				$buf = '';
			}
		}

		if( 'Post ID' == $datas[0] ) {
			continue;
		}

		$line_num = $rows_num + 1;

		$min_field_num = ( usces_is_reduced_taxrate() ) ? 12 : 11;
		$min_field_num = apply_filters( 'usces_filter_sku_uploadcsv_min_field_num', $min_field_num );
		if( $min_field_num > count($datas) ) {
			$err_num++;
			$mes = "No.".$line_num." ".count($datas)."\t".__('The number of the columns is abnormal.', 'usces');
			$logtemp .= $mes.$yn;
			$log .= $logtemp;
			echo $mes.$br.$yn;
			continue;
		}

		$lineready = microtime(true);
		$linereadytime += $lineready-$linestart;

		foreach( $datas as $key => $data ) {
			switch( $key ) {
			case 0://Post ID
				$post_id = ( !WCUtils::is_blank($data) ) ? (int)$data : NULL;
				if( $post_id ) {
					$db_res = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
					if( !$db_res ) {
						$err_num++;
						$mes = "No.".$line_num."\t".sprintf( __( "Post-ID %s does not exist in the database.", 'usces' ), $post_id );
						$logtemp .= $mes.$yn;
						$log .= $logtemp;
					}
				} else {
					$err_num++;
					$mes = "No.".$line_num."\t".__( 'A value of the Post-ID is abnormal.', 'usces' );
					$logtemp .= $mes.$yn;
					$log .= $logtemp;
				}
				break;
			case 3://SKU Code
				if( 0 == strlen($data) ) {
					$mes = "No.".$line_num."\t".__( 'A SKU cord is non-input.', 'usces' );
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			case 4://SKU Name
				break;
			case 5://Normal Price
				if( 0 < strlen( $data ) && !preg_match( "/^\d$|^\d+\.?\d+$/", $data ) ) {
					$mes = "No.".$line_num."\t".__( 'A value of the normal price is abnormal.', 'usces' );
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			case 6://Sale Price
				if( !preg_match( "/^\d$|^\d+\.?\d+$/", $data ) || 0 == strlen( $data ) ) {
					$mes = "No.".$line_num."\t".__( 'A value of the sale price is abnormal.', 'usces' );
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			case 7://Stock
				if( 0 < strlen( $data ) && !preg_match( "/^[0-9]+$/", $data ) ) {
					$mes = "No.".$line_num."\t".__( 'A value of the stock amount is abnormal.', 'usces' );
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			case 8://Stock Status
				$stock_status = apply_filters( 'usces_filter_csv_upload_check_stock_status', $data );
				if( !preg_match( "/^[0-9]+$/", $data ) || $stock_status < $data ) {
					$mes = "No.".$line_num."\t".__( 'A value of the stock status is abnormal.', 'usces' );
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			case 9://Unit
				break;
			case 10://Apply business package
				if( !preg_match( "/^[0-9]+$/", $data ) || 1 < $data ) {
					$mes = "No.".$line_num."\t".__( 'The value of the duties pack application is abnormal.', 'usces' );
					$logtemp .= $mes.$yn;
					$mestemp .= $mes.$br.$yn;
				}
				break;
			case 11://Applicable tax rate
				if( usces_is_reduced_taxrate() ) {
					if( !preg_match( "/^[0-9]+$/", $data ) || 1 < $data ) {
						$mes = "No.".$line_num."\t".__( 'Invalid value of Applicable tax rate.', 'usces' );
						$logtemp .= $mes.$yn;
						$mestemp .= $mes.$br.$yn;
					}
				}
				break;
			}
		}

		if( 0 < strlen($logtemp) ) {
			$err_num++;
			$log .= $logtemp;
			echo $mestemp;
			continue;
		}

		$checkend = microtime(true);
		$checktime += $checkend - $lineready;
		$insert_post = microtime(true);
		$insertposttime += $insert_post - $checkend;
		$metadelete = microtime(true);
		$metadeletetime += $metadelete - $insert_post;
		$add_postmeta = microtime(true);
		$addmetatime += $add_postmeta - $metadelete;
		$optionmeta = microtime(true);
		$AddOptiontime += $optionmeta - $add_postmeta;

		$sku_code = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[3], 'UTF-8', 'SJIS')) : trim($datas[3]);

		$sku_meta = usces_get_post_meta( $post_id, '_isku_' );
		if( !$sku_meta ) {
			$err_num++;
			$mes = "No.".$line_num."\t".__('This data was not registered in the database.', 'usces');
			$log .= $mes.$yn;
			echo $mes.$br.$yn;
			continue;
		}

		foreach( $sku_meta as $meta ) {
			$skuvalue = unserialize($meta['meta_value']);
			if( $sku_code == urldecode($skuvalue['code']) ) {
				$skuvalue['name'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[4], 'UTF-8', 'SJIS')) : trim($datas[4]);
				$skuvalue['cprice'] = $datas[5];
				$skuvalue['price'] = $datas[6];
				$skuvalue['unit'] = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($datas[9], 'UTF-8', 'SJIS')) : trim($datas[9]);
				$skuvalue['stocknum'] = $datas[7];
				$skuvalue['stock'] = $datas[8];
				$skuvalue['gp'] = $datas[10];
				if( usces_is_reduced_taxrate() ) {
					$skuvalue['taxrate'] = usces_csv_set_sku_applicable_taxrate( $datas[11] );
				}
				$skuvalue = apply_filters( 'usces_filter_sku_uploadcsv_skuvalue', $skuvalue, $datas );
				$query = $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_id = %d", serialize($skuvalue), $meta['meta_id'] );
				$db_res = $wpdb->query( $query );
				if( 0 > $db_res ) {
					$err_num++;
					$mes = "No.".$line_num."\t".__('This data was not registered in the database.', 'usces');
					$log .= $mes.$yn;
					echo $mes.$br.$yn;
					continue;
				}
				break;
			}
		}

		$comp_num++;
		clean_post_cache($post_id);
		wp_cache_delete($post_id, 'posts');
		wp_cache_delete($post_id, 'post_meta');
		clean_object_term_cache($post_id, 'post');

		/******************/
		$addsku = microtime(true);
		$AddSKUtime += $addsku - $optionmeta;
		$onelinetime += $addsku - $linestart;

		if( 0 === ($line_num % 10) ) {
			$av = $onelinetime / $rows_num;
			$nt = round($av * ($total_num - $rows_num));
			if( 60 < $nt ) {
				$mtime = ceil($nt / 60).__('min', 'usces').($nt % 60).__('sec', 'usces').' ';
			} else {
				$mtime = $nt.__('sec', 'usces').' ';
			}
			echo '<script type="text/javascript">changeMsg("'.__('Processing...', 'usces').__('Remaining time:', 'usces').$mtime.'");</script>'.$yn;
			echo '<script type="text/javascript">setProgress('.$line_num.','.$total_num.');</script>'.$yn;
			ob_flush();
			flush();
		}
	}

	$results = compact( 'res', 'log', 'total_num', 'comp_num', 'err_num', 'readytime', 'linereadytime', 'insertposttime', 'metadeletetime', 'addmetatime', 'AddOptiontime', 'AddSKUtime', 'onelinetime', 'line_num' );

	return $results;
}

// item sku list download
function usces_download_item_sku_list( $rows, $usces_opt_item ) {
	global $usces;

	$ext = 'csv';
	$table_h = "";
	$table_f = "";
	$tr_h = "";
	$tr_f = "";
	$th_h1 = '"';
	$th_h = ',"';
	$th_f = '"';
	$td_h1 = '"';
	$td_h = ',"';
	$td_f = '"';
	$sp = ";";
	$eq = "=";
	$lf = "\n";
	$end = '';

	$line = '';

	if( $usces_opt_item['chk_header'] == 1 ) {
		$line .= $tr_h;
		$line .= $th_h1.'Post ID'.$th_f;//0
		$line .= $th_h.__('item code', 'usces').$th_f;//1
		$line .= $th_h.__('item name', 'usces').$th_f;//2
		$line .= $th_h.__('SKU code', 'usces').$th_f;//3
		$line .= $th_h.__('SKU display name ', 'usces').$th_f;//4
		$line .= $th_h.__('normal price', 'usces').$th_f;//5
		$line .= $th_h.__('Sale price', 'usces').$th_f;//6
		$line .= $th_h.__('stock', 'usces').$th_f;//7
		$line .= $th_h.__('stock status', 'usces').$th_f;//8
		$line .= $th_h.__('unit', 'usces').$th_f;//9
		$line .= $th_h.__('Apply business package', 'usces').$th_f;//10
		if( usces_is_reduced_taxrate() ) {
			$line .= $th_h.__( 'Applicable tax rate', 'usces' ).$th_f;//11
		}
		$line = apply_filters( 'usces_filter_downloadcsv_header_skulist', $line );
		$line .= $tr_f.$lf;
	}

	//==========================================================================

	mb_http_output('pass');
	set_time_limit(3600);
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=usces_item_list.".$ext);
	@ob_end_flush();
	flush();

	foreach( (array)$rows as $row ) {
		$post_id = $row['ID'];

		//Post Data
		$line_item = $td_h1.$post_id.$td_f;

		//Item Meta
		$line_item .= $td_h.$usces->getItemCode($post_id).$td_f;
		$line_item .= $td_h.usces_entity_decode($usces->getItemName($post_id), $ext).$td_f;

		//SKU
		$sku_meta = $usces->get_skus( $post_id, 'sort' );
		foreach( $sku_meta as $sku_value ) {
			$line_sku = $td_h.$sku_value['code'].$td_f;
			$line_sku .= $td_h.usces_entity_decode($sku_value['name'], $ext).$td_f;
			$line_sku .= $td_h.usces_crform($sku_value['cprice'], false, false, 'return', false).$td_f;
			$line_sku .= $td_h.usces_crform($sku_value['price'], false, false, 'return', false).$td_f;
			$line_sku .= $td_h.$sku_value['stocknum'].$td_f;
			$line_sku .= $td_h.$sku_value['stock'].$td_f;
			$line_sku .= $td_h.usces_entity_decode($sku_value['unit'], $ext).$td_f;
			$line_sku .= $td_h.$sku_value['gp'].$td_f;
			if( usces_is_reduced_taxrate() ) {
				$line_sku .= $td_h.usces_csv_get_sku_applicable_taxrate( $sku_value ).$td_f;
			}
			$line_sku = apply_filters( 'usces_filter_downloadcsv_skuvalue_skulist', $line_sku, $sku_value );
			$line .= $tr_h.$line_item.$line_sku.$tr_f.$lf;
		}
		if( $usces->options['system']['csv_encode_type'] == 0 ) {
			$line = mb_convert_encoding($line, apply_filters( 'usces_filter_output_csv_encode', 'SJIS-win'), "UTF-8");
		}
		print($line);

		if( ob_get_contents() ) {
			ob_flush();
			flush();
		}
		$line = '';
		wp_cache_flush();
	}
	$end = $table_f.$lf;

	$results = compact( 'end' );

	return $results;
}

// item meta list upload
function usces_item_meta_uploadcsv( $lines ) {
	global $wpdb, $usces;

	$comp_num = 0;
	$err_num = 0;
	$line_num = 0;
	$log = '';
	$res = array();
	$linereadytime = 0;
	$checktime = 0;
	$insertposttime = 0;
	$metadeletetime = 0;
	$addmetatime = 0;
	$AddOptiontime = 0;
	$AddSKUtime = 0;
	$onelinetime = 0;
	$yn = "\r\n";
	$br = "<br />";
	$sp = ",";

	$total_num = count($lines);
	$readytime = microtime(true);

	$labels = array();

	foreach( $lines as $rows_num => $line ) {

		$linestart = microtime(true);
		$datas = array();

		$logtemp = '';
		$mestemp = '';
		$line = trim($line);
		if( empty($line) ) {
			continue;
		}

		$d = explode($sp, $line);
		$buf = '';
		foreach( $d as $data ) {
			$num = substr_count($data, '"');
			if( 0 == $num % 2 && '' == $buf ) {
				if( '"' == substr($data, 0, 1) ) {
					$data = substr($data, 1);
				}
				if( '"' == substr($data, -1) ) {
					$data = substr($data, 0, -1);
				}
				$data = str_replace(array('""'), '"', $data);
				$datas[] = ( false !== $data ) ? $data : '';
			} elseif( 1 == $num % 2 && '' == $buf ) {
				$buf .= $data;
			} elseif( 0 == $num % 2 && '' != $buf ) {
				$buf .= $sp.$data;
			} elseif( 1 == $num % 2 && '' != $buf ) {
				$buf .= $sp.$data;
				if( '"' == substr($buf, 0, 1) ) {
					$buf = substr($buf, 1);
				}
				if( '"' == substr($buf, -1) ) {
					$buf = substr($buf, 0, -1);
				}
				$buf = str_replace(array('""'), '"', $buf);
				$datas[] = ( false !== $buf ) ? $buf : '';
				$buf = '';
			}
		}


		if( 0 == $rows_num ){

			if( 'post_id_meta' !== $datas[0] ) {
				$res['status'] = 'error';
				$res['message'] = __('This CSV file is not for custom fields', 'usces');
				echo $res['status'].' : '.$res['message'];
				$results = compact( 'res', 'log', 'total_num', 'comp_num', 'err_num', 'readytime', 'linereadytime', 'insertposttime', 'metadeletetime', 'addmetatime', 'AddOptiontime', 'AddSKUtime', 'onelinetime', 'line_num' );
				return $results;
			}

			$labels = $datas;
			continue;
		}

		$line_num = $rows_num + 1;

		$lineready = microtime(true);
		$linereadytime += $lineready-$linestart;

		$post_id = $datas[0];

		foreach( $datas as $key => $data ) {

			if( 3 > $key ){
				continue;
			}

			$meta_key = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($labels[$key], 'UTF-8', 'SJIS')) : trim($labels[$key]);
			$meta_value = ( $usces->options['system']['csv_encode_type'] == 0 ) ? trim(mb_convert_encoding($data, 'UTF-8', 'SJIS')) : trim($data);

//			usces_p($meta_key);
//			usces_p($meta_value);

			//delete
			$query = $wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE `post_id` = %d AND `meta_key` = %s", $post_id, $meta_key );
			$db_res = $wpdb->query( $query );

			//add
			if( 'null' != $data ){
				$query = $wpdb->prepare("INSERT INTO {$wpdb->postmeta} ( `post_id`, `meta_key`, `meta_value` ) VALUES ( %d, %s, %s ) ", $post_id, $meta_key, $meta_value );
				$db_res = $wpdb->query( $query, ARRAY_A );
			}

//			$query = $wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE `post_id` = %d AND `meta_key` = %s", $post_id, $meta_key );
//			$db_res = $wpdb->get_results( $query, ARRAY_A );
//			usces_p($db_res);
		}

		$checkend = microtime(true);
		$checktime += $checkend - $lineready;
		$insert_post = microtime(true);
		$insertposttime += $insert_post - $checkend;
		$metadelete = microtime(true);
		$metadeletetime += $metadelete - $insert_post;
		$add_postmeta = microtime(true);
		$addmetatime += $add_postmeta - $metadelete;
		$optionmeta = microtime(true);
		$AddOptiontime += $optionmeta - $add_postmeta;


		$comp_num++;
		clean_post_cache($post_id);
		wp_cache_delete($post_id, 'posts');
		wp_cache_delete($post_id, 'post_meta');
		clean_object_term_cache($post_id, 'post');

		/******************/
		$addsku = microtime(true);
		$AddSKUtime += $addsku - $optionmeta;
		$onelinetime += $addsku - $linestart;

		if( 0 === ($line_num % 10) ) {
			$av = $onelinetime / $rows_num;
			$nt = round($av * ($total_num - $rows_num));
			if( 60 < $nt ) {
				$mtime = ceil($nt / 60).__('min', 'usces').($nt % 60).__('sec', 'usces').' ';
			} else {
				$mtime = $nt.__('sec', 'usces').' ';
			}
			echo '<script type="text/javascript">changeMsg("'.__('Processing...', 'usces').__('Remaining time:', 'usces').$mtime.'");</script>'.$yn;
			echo '<script type="text/javascript">setProgress('.$line_num.','.$total_num.');</script>'.$yn;
			ob_flush();
			flush();
		}
	}

	$results = compact( 'res', 'log', 'total_num', 'comp_num', 'err_num', 'readytime', 'linereadytime', 'insertposttime', 'metadeletetime', 'addmetatime', 'AddOptiontime', 'AddSKUtime', 'onelinetime', 'line_num' );

	return $results;
}

// item meta download
function usces_download_item_meta_list( $rows, $usces_opt_item ) {
	global $usces;

	$meta_keys = array( 'post_id_meta' => 'null', 'item_code' => 'null', 'item_name' => 'null' );
	foreach( $rows as $row ){

		$metas = get_post_meta( $row['ID'] );
		foreach( $metas as $key => $value ){
			if( '_' !== substr( $key, 0, 1 ) ){
				$meta_keys[$key] = 'null';
			}
		}
	}

	$data = array();
	foreach( $rows as $r => $row ){
		
		$new = $meta_keys;
		$new['post_id_meta'] = $row['ID'];
		$new['item_code'] = $row['item_code'];
		$new['item_name'] = $row['item_name'];
		$metas = get_post_meta( $row['ID'] );
		foreach( $metas as $key => $values ){
			if( '_' !== substr( $key, 0, 1 ) ){
				$new[$key] = $values[0];
			}
		}
		$data[$r] = $new;
	}



	$ext = 'csv';
	$h = '"';
	$f = '",';
	$lf = "\n";

	$line = '';
	foreach( $meta_keys as $label => $lv  ) {
		$line .= $h . $label . $f;
	}
	$line = trim( $line, ',' );
	$line .= $lf;

	//==========================================================================
	mb_http_output('pass');
	set_time_limit(0);
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=usces_item_meta.".$ext);
	@ob_end_flush();
	flush();

	if( $usces->options['system']['csv_encode_type'] == 0 ) {
		$line = mb_convert_encoding( $line, apply_filters( 'usces_filter_output_csv_encode', 'SJIS-win'), "UTF-8" );
	}
	print($line);

	foreach( $data as $d ) {
		$line = '';

		foreach( $d as $dv  ) {
			$line .= $h . str_replace( '"', '""', $dv ) . $f;
		}
		$line = trim( $line, ',' );
		$line .= $lf;

		if( $usces->options['system']['csv_encode_type'] == 0 ) {
			$line = mb_convert_encoding( $line, apply_filters( 'usces_filter_output_csv_encode', 'SJIS-win'), "UTF-8" );
		}

		print($line);

		if( ob_get_contents() ) {
			ob_flush();
			flush();
		}
	}

	exit();
}

}

function usces_get_item_custom_fields() {
	$item_custom_fields = apply_filters( 'usces_filter_item_custom_fields', array( '_itemOrderAcceptable' ) );
	return $item_custom_fields;
}

function usces_get_item_custom_field_value( $key, $custom_field ) {
	global $usces;

	$value = '';
	$cfdata = array();
	$cfrows = explode( ';', $custom_field );

	foreach( $cfrows as $cfindex => $row ) {
		if( false !== strpos( $row, '=' ) ) {
			$cfdata[] = $row;
		}else{
			$cfdend = count($cfdata)-1;
			if( $cfdend && 0 <= $cfdend ) {
				$cfdata[$cfdend] = $cfdata[$cfdend].';'.$row;
			}
		}
	}

	foreach( $cfdata as $row ) {
		$cf = explode( '=', $row );
		if( !WCUtils::is_blank($cf[0]) ) {
			if( $key == $cf[0] ) {
				$value = $cf[1];
				break;
			}
		}
	}
	return trim($value);
}

function usces_csv_get_sku_applicable_taxrate( $sku = array() ) {
	$taxrate = '0';
	if( isset( $sku['taxrate'] ) && !empty( $sku['taxrate'] ) ) {
		$taxrate = ( 'reduced' == $sku['taxrate'] ) ? '1' : '0';
	}
	return $taxrate;
}

function usces_csv_set_sku_applicable_taxrate( $taxrate = 0 ) {
	$value = ( 1 == (int)$taxrate ) ? 'reduced' : 'standard';
	return $value;
}
