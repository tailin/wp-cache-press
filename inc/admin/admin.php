<?php
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Link to the configuration page of the plugin, support & documentation
 *
 * @since 1.0
 *
 * @param array $actions Array of links to display.
 * @return array Updated array of links
 */
function rocket_settings_action_links( $actions ) {
	array_unshift( $actions, sprintf( '<a href="%s">%s</a>', esc_url( 'http://wp-rocket.me/support/' ), esc_html__( 'Support', 'rocket' ) ) );

	array_unshift( $actions, sprintf( '<a href="%s">%s</a>', esc_url( get_rocket_documentation_url() ), esc_html__( 'Docs', 'rocket' ) ) );

	array_unshift( $actions, sprintf( '<a href="%s">%s</a>', esc_url( get_rocket_faq_url() ), esc_html__( 'FAQ', 'rocket' ) ) );

	array_unshift( $actions, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . WP_ROCKET_PLUGIN_SLUG ) ), esc_html__( 'Settings', 'rocket' ) ) );

	return $actions;
}
add_filter( 'plugin_action_links_' . plugin_basename( WP_ROCKET_FILE ), 'rocket_settings_action_links' );

/**
 * Add a link "Renew your licence" when ou can't do it automatically (expired licence but new version available)
 *
 * @since 2.2
 *
 * @param array  $plugin_meta An array of the plugin's metadata, including the version, author, author URI, and plugin URI.
 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
 * @return array Updated meta content if license is expired
 */
function rocket_plugin_row_meta( $plugin_meta, $plugin_file ) {
	if ( 'wp-cache-press/wp-cache-press.php' === $plugin_file ) {

		$update_plugins = get_site_transient( 'update_plugins' );

		if ( false !== $update_plugins && isset( $update_plugins->response[ $plugin_file ] ) && empty( $update_plugins->response[ $plugin_file ]->package ) ) {

			$link = '<span class="dashicons dashicons-update rocket-dashicons"></span> <span class="rocket-renew">Renew your licence of WP Rocket to receive access to automatic upgrades and support.</span> <a href="http://wp-rocket.me" target="_blank" class="rocket-purchase">Purchase now</a>.';

			$plugin_meta = array_merge( (array) $link, $plugin_meta );
		}
	}

	return $plugin_meta;
}
add_action( 'plugin_row_meta', 'rocket_plugin_row_meta', 10, 2 );

/**
 * Add a link "Purge this cache" in the post edit area
 *
 * @since 1.0
 *
 * @param array  $actions An array of row action links.
 * @param object $post The post object.
 * @return array Updated array of row action links
 */
function rocket_post_row_actions( $actions, $post ) {
	/** This filter is documented in inc/admin-bar.php */
	if ( current_user_can( apply_filters( 'rocket_capacity', 'manage_options' ) ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=purge_cache&type=post-' . $post->ID ), 'purge_cache_post-' . $post->ID );
		$actions['rocket_purge'] = sprintf( '<a href="%s">%s</a>', $url, __( 'Clear this cache', 'rocket' ) );
	}
	return $actions;
}
add_filter( 'page_row_actions', 'rocket_post_row_actions', 10, 2 );
add_filter( 'post_row_actions', 'rocket_post_row_actions', 10, 2 );

/**
 * Add a link "Purge this cache" in the taxonomy edit area
 *
 * @since 1.0
 *
 * @param array  $actions An array of row action links.
 * @param object $term The term object.
 * @return array Updated array of row action links
 */
function rocket_tag_row_actions( $actions, $term ) {
	global $taxnow;

	/** This filter is documented in inc/admin-bar.php */
	if ( current_user_can( apply_filters( 'rocket_capacity', 'manage_options' ) ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=purge_cache&type=term-' . $term->term_id . '&taxonomy=' . $taxnow ), 'purge_cache_term-' . $term->term_id );
		$actions['rocket_purge'] = sprintf( '<a href="%s">%s</a>', $url, __( 'Clear this cache', 'rocket' ) );
	}

	return $actions;
}
add_filter( 'tag_row_actions', 'rocket_tag_row_actions', 10, 2 );

/**
 * Add a link "Purge this cache" in the user edit area
 *
 * @since 2.6.12
 * @param array  $actions An array of row action links.
 * @param object $user The user object.
 * @return array Updated array of row action links
 */
function rocket_user_row_actions( $actions, $user ) {
	/** This filter is documented in inc/admin-bar.php */
	if ( current_user_can( apply_filters( 'rocket_capacity', 'manage_options' ) ) && get_rocket_option( 'cache_logged_user', false ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=purge_cache&type=user-' . $user->ID ), 'purge_cache_user-' . $user->ID );
		$actions['rocket_purge'] = sprintf( '<a href="%s">%s</a>', $url, __( 'Clear this cache', 'rocket' ) );
	}

	return $actions;
}
add_filter( 'user_row_actions', 'rocket_user_row_actions', 10, 2 );

/**
 * Manage the dismissed boxes
 *
 * @since 2.4 Add a delete_transient on function name (box name)
 * @since 1.3.0 $args can replace $_GET when called internaly
 * @since 1.1.10
 *
 * @param array $args An array of query args.
 */
function rocket_dismiss_boxes( $args ) {
	$args = empty( $args ) ? $_GET : $args;

	if ( isset( $args['box'], $args['_wpnonce'] ) ) {

		if ( ! wp_verify_nonce( $args['_wpnonce'], $args['action'] . '_' . $args['box'] ) ) {
			if ( defined( 'DOING_AJAX' ) ) {
				wp_send_json( array(
					'error' => 1,
				) );
			} else {
				wp_nonce_ays( '' );
			}
		}

		global $current_user;
		$actual = get_user_meta( $current_user->ID, 'rocket_boxes', true );
		$actual = array_merge( (array) $actual, array( $args['box'] ) );
		$actual = array_filter( $actual );
		$actual = array_unique( $actual );
		update_user_meta( $current_user->ID, 'rocket_boxes', $actual );
		delete_transient( $args['box'] );

		if ( 'admin-post.php' === $GLOBALS['pagenow'] ) {
			if ( defined( 'DOING_AJAX' ) ) {
				wp_send_json( array(
					'error' => 0,
				) );
			} else {
				wp_safe_redirect( wp_get_referer() );
				die();
			}
		}
	}
}
add_action( 'wp_ajax_rocket_ignore', 'rocket_dismiss_boxes' );
add_action( 'admin_post_rocket_ignore', 'rocket_dismiss_boxes' );

/**
 * Renew the plugin modification warning on plugin de/activation
 *
 * @since 1.3.0
 *
 * @param string $plugin plugin name.
 */
function rocket_dismiss_plugin_box( $plugin ) {
	if ( plugin_basename( WP_ROCKET_FILE ) !== $plugin ) {
		rocket_renew_box( 'rocket_warning_plugin_modification' );
	}
}
add_action( 'activated_plugin', 'rocket_dismiss_plugin_box' );
add_action( 'deactivated_plugin', 'rocket_dismiss_plugin_box' );

/**
 * Display a prevention message when enabling or disabling a plugin can be in conflict with WP Rocket
 *
 * @since 1.3.0
 */
function rocket_deactivate_plugin() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'deactivate_plugin' ) ) {
		wp_nonce_ays( '' );
	}

	deactivate_plugins( $_GET['plugin'] );

	wp_safe_redirect( wp_get_referer() );
	die();
}
add_action( 'admin_post_deactivate_plugin', 'rocket_deactivate_plugin' );

/**
 * This function will force the direct download of the plugin's options, compressed.
 *
 * @since 2.2
 */
function rocket_do_options_export() {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'rocket_export' ) ) {
		wp_nonce_ays( '' );
	}

	$filename = sprintf( '%s-settings-%s-%s.json', 'wp-rocket', date( 'Y-m-d' ), uniqid() );
	$gz = 'gz' . strrev( 'etalfed' );
	$options = json_encode( get_option( WP_ROCKET_SETTINGS_SLUG ) ); // do not use get_rocket_option() here.
	nocache_headers();
	@header( 'Content-Type: application/json' );
	@header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	@header( 'Content-Transfer-Encoding: binary' );
	@header( 'Content-Length: ' . strlen( $options ) );
	@header( 'Connection: close' );
	echo $options;
	exit();
}
add_action( 'admin_post_rocket_export', 'rocket_do_options_export' );

if ( ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_AUTOSAVE' ) ) {
	add_action( 'admin_init', 'rocket_init_cache_dir' );
	add_action( 'admin_init', 'rocket_maybe_generate_advanced_cache_file' );
	add_action( 'admin_init', 'rocket_maybe_generate_config_files' );
	add_action( 'admin_init', 'rocket_maybe_set_wp_cache_define' );
}

/**
 * Regenerate the advanced-cache.php file if an issue is detected.
 *
 * @since 2.6
 */
function rocket_maybe_generate_advanced_cache_file() {
	if ( ! defined( 'WP_ROCKET_ADVANCED_CACHE' ) || ( defined( 'WP_ROCKET_ADVANCED_CACHE_PROBLEM' ) && WP_ROCKET_ADVANCED_CACHE_PROBLEM ) ) {
		rocket_generate_advanced_cache_file();
	}
}

/**
 * Regenerate config file if an issue is detected.
 *
 * @since 2.6.5
 */
function rocket_maybe_generate_config_files() {
	list( $host, $path ) = get_rocket_parse_url( home_url() );
	$path = ( ! empty( $path ) ) ? str_replace( '/', '.', untrailingslashit( $path ) ) : '';

	if ( ! file_exists( WP_ROCKET_CONFIG_PATH . strtolower( $host ) . $path . '.php' ) ) {
		rocket_generate_config_file();
	}
}

/**
 * Define WP_CACHE to true if it's not defined yet.
 *
 * @since 2.6
 */
function rocket_maybe_set_wp_cache_define() {
	if ( defined( 'WP_CACHE' ) && ! WP_CACHE ) {
		set_rocket_wp_cache_define( true );
	}
}

/**
 * Launches the database optimization from admin
 *
 * @since 2.8
 * @author Remy Perona
 */
function rocket_optimize_database() {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'rocket_optimize_database' ) ) {
		wp_nonce_ays( '' );
	}

	do_rocket_database_optimization();

	wp_redirect( wp_get_referer() );
	die();
}
add_action( 'admin_post_rocket_optimize_database', 'rocket_optimize_database' );
