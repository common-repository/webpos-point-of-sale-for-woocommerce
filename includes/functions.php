<?php
/**
 * Function include all files in folder
 *
 * @param $path   Directory address
 * @param $ext    array file extension what will include
 * @param $prefix string Class prefix
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! function_exists( 'villatheme_include_folder' ) ) {
	function villatheme_include_folder( $path, $prefix = '', $ext = array( 'php' ) ) {
		/*Include all files in payment folder*/
		if ( ! is_array( $ext ) ) {
			$ext = explode( ',', $ext );
			$ext = array_map( 'trim', $ext );
		}
		$sfiles = scandir( $path );
		foreach ( $sfiles as $sfile ) {
			if ( $sfile != '.' && $sfile != '..' ) {
				if ( is_file( $path . "/" . $sfile ) ) {
					$ext_file  = pathinfo( $path . "/" . $sfile );
					$file_name = $ext_file['filename'];
					if ( $ext_file['extension'] ) {
						if ( in_array( $ext_file['extension'], $ext ) ) {
							if ( $prefix === 'just_require' ) {
								require_once $path . $sfile;
								continue;
							}
							$class = preg_replace( '/\W/i', '_', $prefix . ucfirst( $file_name ) );
							if ( ! class_exists( $class ) ) {
								require_once $path . $sfile;
								if ( class_exists( $class ) ) {
									new $class();
								}
							}
						}
					}
				}
			}
		}
	}
}
if ( ! function_exists( 'villatheme_sanitize_fields' ) ) {
	function villatheme_sanitize_fields( $data ) {
		if ( is_array( $data ) ) {
			return array_map( 'villatheme_sanitize_fields', $data );
		} else {
			return is_scalar( $data ) ? sanitize_text_field( wp_unslash( $data ) ) : $data;
		}
	}
}
if ( ! function_exists( 'villatheme_sanitize_kses' ) ) {
	function villatheme_sanitize_kses( $data ) {
		if ( is_array( $data ) ) {
			return array_map( 'villatheme_sanitize_kses', $data );
		} else {
			return is_scalar( $data ) ? wp_kses_post( wp_unslash( $data ) ) : $data;
		}
	}
}
if ( ! function_exists( 'villatheme_convert_time' ) ) {
	function villatheme_convert_time( $time ) {
		if ( ! $time ) {
			return 0;
		}
		$temp = explode( ":", $time );
		if ( count( $temp ) == 2 ) {
			return ( absint( $temp[0] ) * 3600 + absint( $temp[1] ) * 60 );
		} else {
			return 0;
		}
	}
}
if ( ! function_exists( 'villatheme_revert_time' ) ) {
	function villatheme_revert_time( $time ) {
		$hour = floor( $time / 3600 );
		$min  = floor( ( $time - 3600 * $hour ) / 60 );
		return implode( ':', array( zeroise( $hour, 2 ), zeroise( $min, 2 ) ) );
	}
}
if ( ! function_exists( 'villatheme_json_decode' ) ) {
	function villatheme_json_decode( $json, $assoc = true, $depth = 512, $options = 2 ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$json = mb_convert_encoding( $json, 'UTF-8', 'UTF-8' );
		}
		return json_decode( is_string( $json ) ? $json : '{}', $assoc, $depth, $options );
	}
}

if ( ! function_exists( 'villatheme_map_fields' ) ) {
	function villatheme_map_fields(&$args, $args_map ) {
		foreach ( $args_map as $k => $v ) {
			switch ( $k ) {
				case 'field':
					foreach ( $v as $item ) {
						$args[ $item ] = isset( $_POST[ $item ] ) ? sanitize_text_field( wp_unslash( $_POST[ $item ] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
					}
					break;
				case 'field_array':
					foreach ( $v as $item ) {
						$args[ $item ] = isset( $_POST[ $item ] ) ? villatheme_sanitize_fields( wp_unslash( $_POST[ $item ] ) ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Missing
					}
					break;
				case 'kses':
					foreach ( $v as $item ) {
						$args[ $item ] = isset( $_POST[ $item ] ) ? wp_kses_post( wp_unslash( $_POST[ $item ] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing
					}
					break;
				case 'kses_array':
					foreach ( $v as $item ) {
						$args[ $item ] = isset( $_POST[ $item ] ) ? villatheme_sanitize_kses( wp_unslash( $_POST[ $item ] ) ) : array();// phpcs:ignore WordPress.Security.NonceVerification.Missing
					}
					break;
			}
		}
	}
}
if (!function_exists('villatheme_render_field')){
	function villatheme_render_field($name, $field){
		if (!$name){
			return;
		}
		if (!empty($field['html'])){
			echo VIWEBPOS_DATA::kses_post($field['html']);// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		$type = $field['type'] ??'';
		$value = $field['value'] ??'';
		if (!empty($field['prefix'])){
			$id = "viwebpos-{$field['prefix']}-{$name}";
		}else{
			$id = "viwebpos-{$name}";
		}
		$class = $field['class'] ?? $id;
		$custom_attributes = array_merge([
			'type' => $type,
			'name' => $name,
			'id' => $id,
			'value' => $value,
			'class' => $class,
		], (array) ($field['custom_attributes'] ??[]));
		if (!empty($field['input_label'])){
			$input_label_type = $field['input_label']['type'] ?? 'left';
			printf('<div class="vi-ui %s labeled input">', (!empty($field['input_label']['fluid'])? 'fluid ':'').esc_attr( $input_label_type ));
			if ($input_label_type === 'left'){
				printf('<div class="%s">%s</div>', esc_attr($field['input_label']['label_class'] ?? 'vi-ui label'), wp_kses_post($field['input_label']['label'] ??''));
			}
		}
		switch ($type){
			case 'premium_option':
				printf('<a class="vi-ui button" href="https://1.envato.market/7m7Jmd"
                                       target="_blank">%s</a>', esc_html__( 'Unlock This Feature', 'webpos-point-of-sale-for-woocommerce' ));
				break;
			case 'checkbox':
				unset($custom_attributes['type']);
				printf('
					<div class="vi-ui toggle checkbox">
						<input type="hidden" %s>
						<input type="checkbox" id="%s-checkbox" %s ><label></label>
					</div>', wc_implode_html_attributes( $custom_attributes), esc_attr($id), esc_attr($value ? 'checked' :''));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'select':
				$select_options = $field['options'] ??'';
				$multiple = $field['multiple'] ?? '';
				unset($custom_attributes['type']);
				unset($custom_attributes['value']);
				$custom_attributes['class']= "vi-ui fluid dropdown {$class}";
				if ($multiple){
					$value = (array)$value;
					$custom_attributes['name']= $name.'[]';
					$custom_attributes['multiple']= "multiple";
				}
				printf('<select %s>', wc_implode_html_attributes( $custom_attributes));// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if (is_array($select_options) && count($select_options)){
					foreach ($select_options as $k => $v){
						$selected = $multiple ? in_array( $k, $value ) : ($k == $value);
						printf( '<option value="%s" %s>%s</option>',
							esc_attr( $k ), wp_kses_post($selected ? 'selected' :''), wp_kses_post( $v ) );
					}
				}
				printf('</select>');
				break;
			case 'textarea':
				unset($custom_attributes['type']);
				unset($custom_attributes['value']);
				printf('<textarea %s>%s</textarea>',wc_implode_html_attributes( $custom_attributes), esc_textarea( $value ) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			default:
				if ($type){
					printf('<input %s>',wc_implode_html_attributes( $custom_attributes) );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
		}
		if (!empty($field['input_label'])) {
			if ( ! empty( $input_label_type ) && $input_label_type === 'right' ) {
				printf( '<div class="%s">%s</div>', esc_attr( $field['input_label']['label_class'] ?? 'vi-ui label' ), wp_kses_post( $field['input_label']['label'] ?? '' ) );
			}
			printf('</div>');
		}
	}
}
if (!function_exists('villatheme_render_table_field')){
	function villatheme_render_table_field($options){
		if (!is_array($options) || !count($options)){
			return;
		}
		if (isset($options['section_start'])){
			if (!empty($options['section_start']['accordion'])){
				printf( '<div class="vi-ui styled fluid accordion">
                                            <div class="title">
                                                <i class="dropdown icon"> </i>
                                                %s
                                            </div>
                                        <div class="content">', esc_html( $options['section_start']['title'] ?? '' ) );
			}
			echo wp_kses_post('<table class="form-table">');
		}
		$fields = $options['fields'] ?? '';
		if (is_array($fields) && count($fields)){
			foreach ($fields as $key => $param){
				$type = $param['type'] ??'';
				$name = $param['name'] ?? $key;
				if (!$name){
					continue;
				}
				if (!empty($param['prefix'])){
					$id = "viwebpos-{$param['prefix']}-{$name}";
				}else{
					$id = "viwebpos-{$name}";
				}
				if (!empty($param['wrap_class'])) {
					printf( '<tr class="%s"><th><label for="%s">%s</label></th><td>',
						esc_attr( $param['wrap_class']), esc_attr( $type === 'checkbox' ? $id . '-' . $type : $id ), wp_kses_post( $param['title'] ?? '' ) );
				}else{
					printf( '<tr><th><label for="%s">%s</label></th><td>',esc_attr( $type === 'checkbox' ? $id . '-' . $type : $id ), wp_kses_post( $param['title'] ?? '' ) );
				}
				do_action('viwebpos_before_option_field', $name, $param);
				villatheme_render_field($name, $param);
				if (!empty($param['custom_desc'])){
					echo wp_kses_post( $param['custom_desc']);
				}
				if (!empty($param['desc'])){
					printf('<p class="description">%s</p>', wp_kses_post($param['desc']));
				}
				do_action('viwebpos_after_option_field', $name, $param);
				echo wp_kses_post('</td></tr>');
			}
		}
		if (isset($options['section_end'])){
			echo wp_kses_post('</table>');
			if (!empty($options['section_end']['accordion'])){
				echo wp_kses_post('</div></div>');
			}
		}
	}
}


if ( ! function_exists( 'viwebpos_init_set' ) ) {
	function viwebpos_init_set() {
		ini_set( 'memory_limit', '3000M' );
		ini_set( 'max_execution_time', '3000' );
		ini_set( 'max_input_time', '3000' );
		ini_set( 'default_socket_timeout', '3000' );
		viwebpos_set_time_limit();
	}
}
if ( ! function_exists( 'viwebpos_set_time_limit' ) ) {
	function viwebpos_set_time_limit( $limit = 0 ) {
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
			@set_time_limit( $limit ); // @codingStandardsIgnoreLine
		}
	}
}