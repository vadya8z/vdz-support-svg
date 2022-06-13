<?php
/**
 * This function runs when WordPress completes its upgrade process
 * It iterates through each plugin updated to see if ours is included
 *
 * @param $upgrader_object Array
 * @param $options Array
 */
add_action(
	'upgrader_process_complete',
	function ( $upgrader_object, $options ) {
		// The path to our plugin's main file
		//$our_plugin = plugin_basename( __FILE__ );
		$our_plugin = preg_replace( '|(.*)\/[a-z_]+\.php|', '$1/$1.php', plugin_basename( __FILE__ ));
		// If an update has taken place and the updated type is plugins and the plugins element exists
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] && isset( $options['plugins'] ) ) {
			// Iterate through the plugins being updated and check if ours is there
			foreach ( $options['plugins'] as $plugin ) {
				if ( $plugin === $our_plugin ) {
					// Set a transient to record that our plugin has just been updated
					set_transient( 'vdz_api_updated_' . $our_plugin, 1 );
				}
			}
		}
	},
	10,
	2
);

/**
 * Show a notice to anyone who has just updated this plugin
 * This notice shouldn't display to anyone who has just installed the plugin for the first time
 */
add_action(
	'admin_notices',
	function () {
		$plugin_data = get_plugin_data( preg_replace( '|(.*)\/[a-z_]+\.php|', __DIR__.'/$1.php', plugin_basename( __FILE__ )) );
		$plugin_link = preg_replace( '|\/(.*)|', '', plugin_basename( __FILE__ ));
		$our_plugin = preg_replace( '|(.*)\/[a-z_]+\.php|', '$1/$1.php', plugin_basename( __FILE__ ));
		$plugin_name = $plugin_data['Name'];
//		var_export($plugin_data);
//		var_export($plugin_link);
//		var_export(plugin_basename( __FILE__ ));
//		var_export(preg_replace( '|(.*)\/[a-z_]+\.php|', '$1/$1.php', plugin_basename( __FILE__ )));
//		var_export( preg_replace( '|(.*)\/[a-z_]+\.php|', __DIR__.'/$1.php', plugin_basename( __FILE__ )));

		// Check the transient to see if we've just updated the plugin
		if ( get_transient( 'vdz_api_updated_' . $our_plugin ) ) {

			if ( function_exists( 'get_locale' ) && in_array( get_locale(), array( 'uk', 'ru_RU' ), true ) ) {
				echo '<div class="notice notice-success">
					<h4>Поздравляю! Обновление успешно завершено! </h4>
					<h3>В разработку плагина вложено очень много сил и времени, поэтому если не сложно:<br/><a target="_blank" style="display: inline-block; margin: 5px 0;" href="https://wordpress.org/support/plugin/'.$plugin_link.'/reviews/?rate=5#new-post">Скажи спасибо и проголосуй (5 звезд) для  '.$plugin_name.'</a> - это займет 5 минут, а мне будет приятно и я пойму, что все делаю правильно</h3>
				  </div>';
			} else {
				echo '<div class="notice notice-success">
					<h4>Congratulations! Update completed successfully!</h4>
					<h3>A lot of time and effort has been invested in the development of the plugin, so if not difficult:<br/><a target="_blank" style="display: inline-block; margin: 5px 0;" href="https://wordpress.org/support/plugin/'.$plugin_link.'/reviews/?rate=5#new-post">Say thanks and vote (5 stars) for '.$plugin_name.'</a> - it will take 5 minutes, but I will be pleased and I will understand that I am doing everything right</h3>
				  </div>';
			}

			delete_transient( 'vdz_api_updated_' . $our_plugin );
		}
	}
);
