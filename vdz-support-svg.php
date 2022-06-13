<?php
/*
Plugin Name: VDZ Support SVG
Plugin URI:  http://online-services.org.ua
Description: Simple add any svg for your site
Version:     1.2
Author:      VadimZ
Author URI:  http://online-services.org.ua#vdz-support-svg
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VDZ_SUPPORT_SVG_API', 'vdz_info_support_svg' );

require_once 'api.php';
require_once 'updated_plugin_admin_notices.php';

// Код активации плагина
register_activation_hook( __FILE__, 'vdz_support_svg_activate_plugin' );
function vdz_support_svg_activate_plugin() {
	global $wp_version;
	if ( version_compare( $wp_version, '3.8', '<' ) ) {
		// Деактивируем плагин
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'This plugin required WordPress version 3.8 or higher' );
	}
	add_option( 'vdz_support_svg_front_show', 1 );

	do_action( VDZ_SUPPORT_SVG_API, 'on', plugin_basename( __FILE__ ) );
}

// Код деактивации плагина
register_deactivation_hook( __FILE__, function () {
	$plugin_name = preg_replace( '|\/(.*)|', '', plugin_basename( __FILE__ ));
	$response = wp_remote_get( "http://api.online-services.org.ua/off/{$plugin_name}" );
	if ( ! is_wp_error( $response ) && isset( $response['body'] ) && ( json_decode( $response['body'] ) !== null ) ) {
		//TODO Вывод сообщения для пользователя
	}
} );
//Сообщение при отключении плагина
add_action( 'admin_init', function (){
	if(is_admin()){
		$plugin_data = get_plugin_data(__FILE__);
		$plugin_name = isset($plugin_data['Name']) ? $plugin_data['Name'] : ' us';
		$plugin_dir_name = preg_replace( '|\/(.*)|', '', plugin_basename( __FILE__ ));
		$handle = 'admin_'.$plugin_dir_name;
		wp_register_script( $handle, '', null, false, true );
		wp_enqueue_script( $handle );
		$msg = '';
		if ( function_exists( 'get_locale' ) && in_array( get_locale(), array( 'uk', 'ru_RU' ), true ) ) {
			$msg .= "Спасибо, что были с нами! ({$plugin_name}) Хорошего дня!";
		}else{
			$msg .= "Thanks for your time with us! ({$plugin_name}) Have a nice day!";
		}
		wp_add_inline_script( $handle, "document.getElementById('deactivate-".esc_attr($plugin_dir_name)."').onclick=function (e){alert('".esc_attr( $msg )."');}" );
	}
} );

//ALLOW SVG
add_filter('upload_mimes', function ($mimes) {
	$mimes['svg'] = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';
	return $mimes;
},100,1);

function vdz_svg_upload_check( $checked_data=null, $file=null, $filename=null, $mimes=null, $real_mime=null ) {

	if ( (isset($checked_data['type']) && !$checked_data['type'])
          || (isset($checked_data['ext']) && !$checked_data['ext'])
          || (isset($checked_data['proper_filename']) && !$checked_data['proper_filename'])
    ) {
		$check_filetype = wp_check_filetype( $filename, $mimes );
		if ( (isset($check_filetype['type']) && !empty($check_filetype['type']))
		     && (isset($check_filetype['ext']) && !empty($check_filetype['ext']))
		     && ('svg' === $check_filetype['ext'])
		     && substr_count( $check_filetype['type'], 'image/svg')
		) {
            $ext = $check_filetype['ext'];
            $type = $check_filetype['type'];
			$proper_filename = $filename;
			$checked_data = compact( 'ext','type', 'proper_filename' );
		}
    }

	return $checked_data;

}
add_filter( 'wp_check_filetype_and_ext', 'vdz_svg_upload_check', 10, 5 );

add_action('admin_head', function () {
    ?>
	<style>
        .attachments-browser img[src$=".svg"] {
            min-width: 110px;
        }
        #postimagediv .inside img{
            min-width: 200px;
            min-height: 200px;
        }
        /* Gutenberg Support */
        .components-responsive-wrapper__content[src$=".svg"] {
            position: relative;
        }
        /* Media LIB */
        /*img[src$=".svg"]{*/
        /*    width: 100%;*/
        /*    height: auto;*/
        /*    object-fit: contain;*/
        /*    object-position: center center;*/
        /*    display: block;*/
        /*    background-image: url("");*/
        /*    background-position: center;*/
        /*    background-size: contain;*/
        /*    background-repeat: no-repeat;*/
        /*}*/
    </style>
<?php
});

//Получаем оригинальные размеры по атрибутам width/height/viewBox
function vdz_svg_get_dimensions( $svg_path ) {
    //Default sizes
	$width = 10;
	$height = 10;
    if(!class_exists('SimpleXMLElement')){
        return (object) array( 'width' => $width, 'height' => $height );
    }
	try {
		$svg = new SimpleXMLElement(@file_get_contents($svg_path));
		if($svg instanceof SimpleXMLElement){
			$attributes = $svg->attributes();
			$width = (int) $attributes->width;
			$height = (int) $attributes->height;

			if(isset($attributes['viewBox']) && (empty($width) || empty($height))){
				$viewBox = explode(',',preg_replace( '|\s+|',',',$attributes['viewBox']));
				//var_export($viewBox);
				if(isset($viewBox[2]) && !empty($viewBox[2]) && isset($viewBox[3]) && !empty($viewBox[3])){
					$width = (int) $viewBox[2];
					$height = (int) $viewBox[3];
				}
			}
		}
	}catch (\Error $e){

    }
	return (object) array( 'width' => $width, 'height' => $height );
}

function vdz_svg_response( $response, $attachment, $meta ) {

	if ( $response['mime'] == 'image/svg+xml' && empty( $response['sizes'] ) ) {

		$svg_path = get_attached_file( $attachment->ID );

		if ( ! file_exists( $svg_path ) ) {
			// If SVG is external, use the URL instead of the path
			$svg_path = $response['url'];
		}
		if ( ! file_exists( $svg_path ) ) {
            return $response;
		}

		$dimensions = vdz_svg_get_dimensions( $svg_path );

		$src                = $response['url'];
		$width              = (int) $dimensions->width;
		$height             = (int) $dimensions->height;
		$response['image']  = compact( 'src', 'width', 'height' );
		$response['thumb']  = compact( 'src', 'width', 'height' );
		$response['sizes'] = array(
			'full' => array(
				'url' => $src,
				'width' => $width,
				'height' => $height,
				'orientation' => $width > $height ? 'landscape' : 'portrait'
			)
		);

	}

	return $response;

}
add_filter( 'wp_prepare_attachment_for_js', 'vdz_svg_response', 10, 3 );

//Подготавливаем размеры
function vdz_svg_generate_attachment_metadata( $metadata, $attachment_id ) {

	$mime = get_post_mime_type( $attachment_id );

	if ( $mime == 'image/svg+xml' ) {

		$svg_path = get_attached_file( $attachment_id );
		$upload_dir = wp_upload_dir();
		// get the path relative to /uploads/ - found no better way:
		$relative_path = str_replace($upload_dir['basedir'], '', $svg_path);
		$filename = basename( $svg_path );

		$dimensions = vdz_svg_get_dimensions( $svg_path );

		$metadata = array(
			'width'		=> $dimensions->width,
			'height'	=> $dimensions->height,
			'file'		=> $relative_path,
			'mime-type'		=> $mime,
		);

		$sizes = array();
		foreach ( get_intermediate_image_sizes() as $s ) {
			$sizes[$s] = array( 'width' => '', 'height' => '', 'crop' => false );
			if ( isset( $_wp_additional_image_sizes[$s]['width'] ) )
				$sizes[$s]['width'] = (int) $_wp_additional_image_sizes[$s]['width']; // For theme-added sizes
			else
				$sizes[$s]['width'] = get_option( "{$s}_size_w" ); // For default sizes set in options
			if ( isset( $_wp_additional_image_sizes[$s]['height'] ) )
				$sizes[$s]['height'] = (int) $_wp_additional_image_sizes[$s]['height']; // For theme-added sizes
			else
				$sizes[$s]['height'] = get_option( "{$s}_size_h" ); // For default sizes set in options
			if ( isset( $_wp_additional_image_sizes[$s]['crop'] ) )
				$sizes[$s]['crop'] = (int) $_wp_additional_image_sizes[$s]['crop']; // For theme-added sizes
			else
				$sizes[$s]['crop'] = get_option( "{$s}_crop" ); // For default sizes set in options

			$sizes[$s]['file'] =  $filename;
			$sizes[$s]['mime-type'] =  'image/svg+xml';
		}
		$metadata['sizes'] = $sizes;
	}

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'vdz_svg_generate_attachment_metadata', 10, 3 );

//Удаляем размеры в 1px что бы показать оригинал
add_filter('post_thumbnail_html',function ($html, $post_id, $post_thumbnail_id, $size, $attr){
    return str_replace( array(
            'width="1"',
            "width='1'",
            'height="1"',
            "height='1'",
    ), '', $html);
}, 10, 5);
