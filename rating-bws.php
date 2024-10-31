<?php
/**
Plugin Name: Rating BestWebSoft
Plugin URI: https://bestwebsoft.com/products/wordpress/plugins/rating/
Description: Add rating plugin to your WordPress website to receive feedback from your customers.
Author: BestWebSoft
Text Domain: rating-bws
Domain Path: /languages
Version: 1.7
Author URI: https://bestwebsoft.com/
License: GPLv2 or later
*/

/**
  © Copyright 2021  BestWebSoft  ( https://support.bestwebsoft.com )

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Add BWS menu */
if ( ! function_exists( 'add_rtng_menu' ) ) {
	function add_rtng_menu() {
		global $submenu, $wp_version, $rtng_plugin_info;
		$settings = add_menu_page( __( 'Rating Settings', 'rating-bws' ), 'Rating', 'manage_options', 'rating.php', 'rtng_settings_page' );
		add_submenu_page( 'rating.php', __( 'Rating Settings', 'rating-bws' ), __( 'Settings', 'rating-bws' ), 'manage_options', 'rating.php', 'rtng_settings_page' );
		add_submenu_page( 'rating.php', 'BWS Panel', 'BWS Panel', 'manage_options', 'rtng-bws-panel', 'bws_add_menu_render' );

		if ( isset( $submenu['rating.php'] ) ) {
			$submenu['rating.php'][] = array(
				'<span style="color:#d86463"> ' . __( 'Upgrade to Pro', 'rating-bws' ) . '</span>',
				'manage_options',
				'https://bestwebsoft.com/products/wordpress/plugins/rating/?k=cc5cf22c4332ef4ba368cf4b739c90df&pn=630&v=' . $rtng_plugin_info['Version'] . '&wp_v=' . $wp_version,
			);
		}

		add_action( 'load-' . $settings, 'rtng_add_tabs' );
	}
}
/* Internationalization*/
if ( ! function_exists( 'rtng_plugins_loaded' ) ) {
	function rtng_plugins_loaded() {
		/* Internationalization */
		load_plugin_textdomain( 'rating-bws', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}
/* Initialization */
if ( ! function_exists( 'rtng_init' ) ) {
	function rtng_init() {
		global $rtng_plugin_info, $rtng_options;
		if ( empty( $rtng_plugin_info ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$rtng_plugin_info = get_plugin_data( __FILE__ );
		}

		/* add general functions */
		require_once dirname( __FILE__ ) . '/bws_menu/bws_include.php';
		bws_include_init( plugin_basename( __FILE__ ) );

		/* check compatible with current WP version */
		bws_wp_min_version_check( plugin_basename( __FILE__ ), $rtng_plugin_info, '4.5' );

		/* Get/Register and check settings for plugin */
		if ( ! is_admin() || ( isset( $_GET['page'] ) && 'rating.php' === $_GET['page'] ) ) {
			rtng_settings();
		}

		if ( ! is_admin() ) {
			if ( in_array( 'in_comment', $rtng_options['rate_position'] ) ) {
				/* comment_post is an action triggered immediately after a comment is inserted into the database. */
				add_action( 'comment_post', 'rtng_add_rating_db_comment', 10, 2 );
				add_action( 'comment_form_top', 'rtng_show_rating_form', 10, 0 );
				/* comment_text - Displays the text of a comment. */
				add_action( 'comment_text', 'rtng_show_comment_rating' );
			}

			if ( ! empty( $rtng_options['average_position'] ) ||
				in_array( 'before', $rtng_options['rate_position'] ) ||
				in_array( 'after', $rtng_options['rate_position'] ) ) {
				add_filter( 'the_content', 'rtng_add_rating_to_content' );
			}
		}
	}
}

/* Function for admin_init */
if ( ! function_exists( 'rtng_admin_init' ) ) {
	function rtng_admin_init() {
		global $pagenow, $rtng_options;
		/* Add variable for bws_menu */
		global $bws_plugin_info, $rtng_plugin_info, $bws_shortcode_list;
		/* Function for bws menu */
		if ( empty( $bws_plugin_info ) ) {
			$bws_plugin_info = array(
				'id'      => '630',
				'version' => $rtng_plugin_info['Version'],
			);
		}
		/* add Plugin to global $bws_shortcode_list */
		$bws_shortcode_list['rtng'] = array(
			'name'        => 'Rating',
			'js_function' => 'rtng_shortcode_init',
		);

		if ( 'plugins.php' === $pagenow ) {
			if ( empty( $rtng_options ) ) {
				rtng_settings();
			}

			if ( function_exists( 'bws_plugin_banner_go_pro' ) ) {
				bws_plugin_banner_go_pro( $rtng_options, $rtng_plugin_info, 'rtng', 'rating', '3878391ae3be686396044e5e64f7634e', '630', 'rating-bws' );
			}
		}
	}
}

/* Register settings function*/
if ( ! function_exists( 'rtng_settings' ) ) {
	function rtng_settings() {
		global $rtng_options, $rtng_plugin_info, $wpdb;
		$db_version = '1.3';
		/* Install the option defaults */
		if ( ! get_option( 'rtng_options' ) ) {
			$options_default = rtng_get_default_options();
			add_option( 'rtng_options', $options_default );
		}

		/* Get options from the database */
		if ( empty( $rtng_options ) ) {
			$rtng_options = get_option( 'rtng_options' );
		}

		if ( empty( $rtng_plugin_info ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$rtng_plugin_info = get_plugin_data( __FILE__ );
		}

		if ( ! isset( $rtng_options['plugin_option_version'] ) || $rtng_options['plugin_option_version'] !== $rtng_plugin_info['Version'] ) {
			$options_default                       = rtng_get_default_options();
			$rtng_options                          = array_merge( $options_default, $rtng_options );
			$rtng_options['plugin_option_version'] = $rtng_plugin_info['Version'];

			/* show pro features */
			$rtng_options['hide_premium_options'] = array();

			$update_option = true;
		}
		if ( ! isset( $rtng_options['plugin_db_version'] ) || ( isset( $rtng_options['plugin_db_version'] ) && $rtng_options['plugin_db_version'] !== $db_version ) ) {
			rtng_db_create();
			/* updating from free: add necessary columns to 'whitelist' table */
			if ( isset( $rtng_options['plugin_db_version'] ) && version_compare( $rtng_options['plugin_db_version'], '1.1', '<' ) ) {
				$column_exists = $wpdb->query( "SHOW COLUMNS FROM `{$wpdb->prefix}bws_rating` LIKE 'user_ip'" );
				if ( 0 === absint( $column_exists ) ) {
					$wpdb->query( "ALTER TABLE `{$wpdb->prefix}bws_rating` ADD `user_ip` CHAR(15) NOT NULL;" );
				}
			}

			$rating_type = $wpdb->get_var(
				"SELECT DATA_TYPE
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE
					TABLE_NAME = '" . $wpdb->base_prefix . "bws_rating' AND
					COLUMN_NAME = 'rating'"
			);

			if ( 'varchar' !== $rating_type ) {
				$wpdb->query(
					'ALTER TABLE `' . $wpdb->base_prefix . "bws_rating`
					MODIFY COLUMN `rating` VARCHAR(255) NOT NULL COLLATE utf8_general_ci DEFAULT '0'"
				);
			}

			$rtng_options['plugin_db_version'] = $db_version;
			$update_option                     = true;
		}

		if ( isset( $update_option ) ) {
			update_option( 'rtng_options', $rtng_options );
		}
	}
}

/* Register default settings function*/
if ( ! function_exists( 'rtng_get_default_options' ) ) {
	function rtng_get_default_options( $is_network_admin = false ) {
		global $rtng_plugin_info, $wp_roles;

		if ( empty( $rtng_plugin_info ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$rtng_plugin_info = get_plugin_data( __FILE__ );
		}

		$default_options = array(
			'plugin_option_version'   => $rtng_plugin_info['Version'],
			'display_settings_notice' => 1,
			'suggest_feature_banner'  => 1,
			'use_post_types'          => array( 'post' ),
			'average_position'        => array( 'after' ),
			'combined'                => 0,
			'rate_position'           => array( 'after' ),
			'rate_color'              => '#ffb900',
			'rate_hover_color'        => '#ff7f00',
			'rate_size'               => 20,
			'text_color'              => '#777777',
			'text_size'               => '18',
			'star_post'               => 0,
			'quantity_star'           => '5',
			'result_title'            => __( 'Average Rating', 'rating-bws' ),
			'vote_title'              => __( 'My Rating', 'rating-bws' ) . ':',
			'enable_testimonials'     => 0,
			'options_quantity'        => 1,
			'testimonials_titles'     => array( __( 'My Rating', 'rating-bws' ) . ':' ),
			'total_message'           => sprintf( __( '%1$s out of 5 stars. %2$s votes.', 'rating-bws' ), '{total_rate}', '{total_count}' ),
			'non_login_message'       => esc_html( sprintf( __( 'You should %s to submit a review.', 'rating-bws' ), '{login_link="' . __( 'log in', 'rating-bws' ) . '"}' ) ),
			'thankyou_message'        => __( 'Thank you!', 'rating-bws' ),
			'error_message'           => __( 'You cannot submit ratings.', 'rating-bws' ),
			'already_rated_message'   => __( 'You have already rated this post.', 'rating-bws' ),
			'enabled_roles'           => array_keys( $wp_roles->roles ),
			'add_schema'              => 1,
			'schema_min_rate'         => 3.0,
			'schema_item_type'        => '',
			'always_clickable'        => 0,
			'rating_required'         => 0,
		);

		return $default_options;
	}
}
/* Performed at activation */
if ( ! function_exists( 'rtng_db_create' ) ) {
	function rtng_db_create() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		/**
		 * Contains templates for pdf-documents
		 * object_id        user ID or comment ID
		 * post_id          post ID
		 * rating           rating
		 * object_type      type - 'post' or 'comment'
		 * datetime         date of creation
		 * user_ip          user IP address
		 */
		$sql_query =
			"CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}bws_rating` (
			`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`object_id` INT( 10 ) NOT NULL,
			`post_id` INT( 10 ) NOT NULL,
			`rating` VARCHAR(255) NOT NULL COLLATE utf8_general_ci DEFAULT '0',
			`datetime` DATETIME NOT NULL,
			`object_type` ENUM( 'post', 'comment' ),
			`user_ip` CHAR( 15 ) NOT NULL,
			PRIMARY KEY ( `id` )
			 ) DEFAULT CHARSET = utf8;";
		dbDelta( $sql_query );
	}
}

/* Function formed content of the plugin's admin page. */
if ( ! function_exists( 'rtng_settings_page' ) ) {
	function rtng_settings_page() {
		if ( ! class_exists( 'Bws_Settings_Tabs' ) ) {
			require_once dirname( __FILE__ ) . '/bws_menu/class-bws-settings.php';
		}
		require_once dirname( __FILE__ ) . '/includes/class-rtng-settings.php';
		$page = new Rtng_Settings_Tabs( plugin_basename( __FILE__ ) );
		if ( method_exists( $page, 'add_request_feature' ) ) {
			$page->add_request_feature();
		} ?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rating Settings', 'rating-bws' ); ?></h1>
			<noscript>
				<div class="error below-h2">
					<p><strong><?php esc_html_e( 'Please, enable JavaScript in your browser.', '' ); ?></strong></p>
				</div>
			</noscript>
			<?php $page->display_content(); ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'rtng_get_user_ip' ) ) {
	function rtng_get_user_ip() {
		$ip = '';
		if ( isset( $_SERVER ) ) {
			$server_vars = array( 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
			foreach ( $server_vars as $var ) {
				if ( isset( $_SERVER[ $var ] ) && filter_var( wp_unslash( $_SERVER[ $var ] ), FILTER_VALIDATE_IP ) ) {
					$ip = sanitize_text_field( wp_unslash( $_SERVER[ $var ] ) );
					if ( 0 < sprintf( '%u', ip2long( $ip ) ) ) {
						break;
					}
				} elseif ( isset( $_SERVER[ $var ] ) ) { /* if proxy */
					$ip_array = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $var ] ) ) );
					if ( is_array( $ip_array ) && ! empty( $ip_array ) && filter_var( $ip_array[0], FILTER_VALIDATE_IP ) ) {
						$ip = $ip_array[0];
						if ( 0 < sprintf( '%u', ip2long( $ip ) ) ) {
							break;
						}
					}
				}
			}
		}
		return $ip;
	}
}

if ( ! function_exists( 'rtng_get_user_role' ) ) {
	function rtng_get_user_role( $user_id = false ) {

		if ( ! empty( $user_id ) ) {
			$user = get_userdata( $user_id );
		} elseif ( is_user_logged_in() ) {
			$user = wp_get_current_user();
		}

		if ( ! empty( $user ) && $user instanceof WP_User ) {
			$roles = (array) $user->roles;
			$role  = $roles[0];
		} else {
			$role = 'guest';
		}

		return $role;
	}
}

if ( ! function_exists( 'rtng_is_role_enabled' ) ) {
	function rtng_is_role_enabled( $role = false ) {
		global $rtng_options;

		if ( empty( $rtng_options ) ) {
			rtng_settings();
		}

		if ( ! $role ) {
			$role = rtng_get_user_role();
		}

		return in_array( $role, $rtng_options['enabled_roles'] );
	}
}

if ( ! function_exists( 'rtng_is_rating_allowed' ) ) {
	function rtng_is_rating_allowed( $post_id = false, $user_id = false, $user_ip = false ) {
		global $rtng_options;

		$args = array();

		/* post ID is missing || rating is desabled for this user role || coockie is already present */
		if (
			! $post_id ||
			! rtng_is_role_enabled() ||
			rtng_is_cookie_set( $post_id )
		) {
			return false;
		}

		if ( isset( $user_id ) ) {
			$args['object_id'] = absint( $user_id );
		}

		if ( $user_ip ) {
			$args['user_ip'] = $user_ip;
		}

		$rating = rtng_get_user_rating( $post_id, $args );

		if ( ! $rating ) {
			$args['type'] = 'comment';
			$rating       = rtng_get_user_rating( $post_id, $args );
		}

		/* already rated */
		if ( $rating ) {
			return false;
		}

		/* rating is allowed */
		return true;
	}
}

if ( ! function_exists( 'rtng_get_post_rating' ) ) {
	function rtng_get_post_rating( $post_id = false ) {
		global $wpdb;
		if ( false !== $post_id ) {
			$post_id = absint( $post_id );

			$total = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT `rating`
				FROM `' . $wpdb->prefix . 'bws_rating`
				WHERE `post_id` = %d',
					$post_id
				),
				ARRAY_A
			);

			if ( empty( $total ) ) {
				$total['count']   = 0;
				$total['total']   = 0;
				$total['average'] = 0;
			} else {
				$sum = 0;
				foreach ( $total as $key => $instance ) {
					$instance['rating'] = maybe_unserialize( $instance['rating'] );
					if ( is_array( $instance['rating'] ) ) {
						$sum += array_sum( $instance['rating'] ) / count( $instance['rating'] );
					} else {
						$sum += $instance['rating'];
					}
				}
				$total['count'] = count( $total );
				/* We need $sum var because without declaring $total['total'] as 0 PHP will throw an error and if we declare it foreach will throw an error */
				$total['total']   = $sum;
				$total['average'] = $total['total'] / $total['count'];
			}

			return $total;
		}
		return array(
			'count'   => 0,
			'total'   => 0,
			'average' => 0,
		);
	}
}

if ( ! function_exists( 'rtng_get_formatted_rating' ) ) {
	function rtng_get_formatted_rating( $rating = 0 ) {
		global $rtng_options;
		return rtrim( number_format( ( $rating / 100 ) * 5, 1 ), '.0' );
	}
}

if ( ! function_exists( 'rtng_get_user_rating' ) ) {
	function rtng_get_user_rating( $post_id = false, $args = array() ) {
		global $wpdb;

		$rating = 0;
		$error  = new WP_Error();

		$defaults = array(
			'object_id' => false,
			'user_ip'   => rtng_get_user_ip(),
			'type'      => 'post',
			'key'       => 0,
		);

		$args = wp_parse_args( $args, $defaults );
		
		extract( $args );

		/* post ID is required! */
		if ( empty( $post_id ) ) {
			$error->add( 'rtng_post_id_error', __( 'Post ID is required', 'rating-bws' ) );
		}

		if ( $user_ip && ! filter_var( $user_ip, FILTER_VALIDATE_IP ) ) {
			$error->add( 'rtng_user_ip_error', __( 'Specified User IP is invalid', 'rating-bws' ) );
		}

		if ( ! empty( $error->errors ) ) {
			return $rating;
		}

		$cookie_rating = rtng_get_cookie_rating( $post_id, $object_id );
		if ( $cookie_rating ) {
			$rating_arr = maybe_unserialize( $cookie_rating );
			return is_array( $rating_arr ) && isset( $rating_arr[ $key ] ) ? $rating_arr[ $key ] : $rating_arr;
		}

		if ( ! in_array( $type, array( 'post', 'comment' ) ) ) {
			$type = 'post';
		}

		if ( isset( $object_id ) ) {
			$object_id = absint( $object_id );
			if ( 0 === $object_id ) {
				$object_id = false;
			}
		}

		if ( false === $object_id && is_user_logged_in() ) {
			$user      = wp_get_current_user();
			$object_id = $user->ID;
		}

		$where = ';';

		if ( 'post' === $type ) {
			if ( false !== $object_id ) {
				$where = sprintf( ' `object_id` = \'%d\'', $object_id );
				if ( apply_filters( 'rtng_check_ip', $user_ip ) ) {
					$where .= sprintf( ' OR `user_ip` = \'%s\'', esc_sql( $user_ip ) );
				}
				$where = 'AND (' . $where . ');';
			} elseif ( apply_filters( 'rtng_check_ip', $user_ip ) ) {
				$where = sprintf( ' AND `user_ip` = \'%s\';', esc_sql( $user_ip ) );
			}

			$query = $wpdb->prepare(
				'SELECT `rating`
				FROM `' . $wpdb->prefix . "bws_rating`
				WHERE `post_id` = %d
					AND `object_type` = 'post' " .
					$where,
				$post_id
			);

			$rating = $wpdb->get_var( $query );
		} else {
			if ( false !== $object_id ) {
				$where = sprintf( ' `' . $wpdb->prefix . 'comments`.`user_id` = %d', $object_id );
				if ( apply_filters( 'rtng_check_ip', $user_ip ) ) {
					$where .= sprintf( ' OR `' . $wpdb->prefix . "bws_rating`.`user_ip` = '%s'", esc_sql( $user_ip ) );
				}
				$where = 'AND (' . $where . ');';
			} elseif ( apply_filters( 'rtng_check_ip', $user_ip ) ) {
				$where = sprintf( ' AND `' . $wpdb->prefix . "bws_rating`.`user_ip` = '%s';", esc_sql( $user_ip ) );
			}

			$query = $wpdb->prepare(
				'SELECT `' . $wpdb->prefix . 'bws_rating`.`rating`
				FROM `' . $wpdb->prefix . 'bws_rating`, `' . $wpdb->prefix . 'comments`
				WHERE `' . $wpdb->prefix . 'comments`.`comment_post_ID` = %d
					AND `' . $wpdb->prefix . 'bws_rating`.`post_id` = %d
					AND `' . $wpdb->prefix . 'bws_rating`.`object_id` = `' . $wpdb->prefix . 'comments`.`comment_id`
					AND `' . $wpdb->prefix . "bws_rating`.`object_type` = 'comment'" .
					$where,
				$post_id,
				$post_id
			);

			$rating = $wpdb->get_var( $query );
		}

		$rating_arr = maybe_unserialize( $rating );

		return is_array( $rating_arr ) && isset( $rating_arr[ $key ] ) ? $rating_arr[ $key ] : $rating_arr;
	}
}

/* requires: post_id, object_id, rating */
if ( ! function_exists( 'rtng_add_user_rating' ) ) {
	function rtng_add_user_rating( $args = array() ) {
		global $wpdb, $rtng_options;

		$rating = false;
		$error  = new WP_Error();

		$defaults = array(
			'post_id'   => false,
			'object_id' => false,
			'rating'    => false,
			'user_ip'   => rtng_get_user_ip(),
			'type'      => 'post',
		);

		$args = wp_parse_args( $args, $defaults );
		extract( $args );

		/* post ID is required! */
		if ( empty( $post_id ) ) {
			$error->add( 'rtng_post_id_error', __( 'Post ID is required', 'rating-bws' ) );
		}
		/* object_id is required! */
		if ( false === $object_id ) {
			$error->add( 'rtng_object_id_error', __( 'User or comment ID is required', 'rating-bws' ) );
		} else {
			$object_id = absint( $object_id );
		}

		/* rating is required! */
		if ( empty( $rating ) ) {
			$error->add( 'rtng_rating_error', __( 'Rating is required', 'rating-bws' ) );
		}

		/* validate IP if specified */
		if ( $user_ip && ! filter_var( $user_ip, FILTER_VALIDATE_IP ) ) {
			$error->add( 'rtng_user_ip_error', __( 'Specified User IP is invalid', 'rating-bws' ) );
		}

		if ( 0 == $object_id ) {
			$old_rating = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT `rating`
					FROM `' . $wpdb->prefix . 'bws_rating`
					WHERE `post_id` = %d AND
					`object_id` = %d AND 
					`user_ip` = %s',
					$post_id,
					$object_id,
					$user_ip
				)
			);
		} else {
			$old_rating = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT `rating`
					FROM `' . $wpdb->prefix . 'bws_rating`
					WHERE `post_id` = %d AND
					`object_id` = %d',
					$post_id,
					$object_id
				)
			);
		}

		if ( ( ! empty( $error->errors ) || ! rtng_is_rating_allowed( $post_id, $object_id ) || 'comment' === $type ) && 0 !== absint( $rtng_options['always_clickable'] ) && null !== $old_rating ) {
			$wpdb->update(
				$wpdb->prefix . 'bws_rating',
				array(
					'rating'    => maybe_serialize( $rating ),
					'datetime'  => current_time( 'mysql' ),
					'object_id' => $object_id,
					'user_ip'   => $user_ip,
				),
				array(
					'post_id'     => $post_id,
					'object_type' => $type,
				)
			);
			return true;
		}

		/* Merge arrays without losing numeric keys */
		if ( ! empty( $old_rating ) ) {
			$new_rating = maybe_unserialize( $old_rating );
			if ( is_array( $new_rating ) && is_array( $rating ) ) {
				$rating = $new_rating + $rating;
			} elseif( is_array( $new_rating ) ) {
				$rating = $new_rating + (array) $rating;
			} elseif( is_array( $rating ) ) {
				$rating = (array)$new_rating + $rating;
			} else {
				$rating = $new_rating + $rating;
			}
		}

		rtng_set_cookie( $post_id, $object_id, $rating );

		$wpdb->replace(
			$wpdb->prefix . 'bws_rating',
			array(
				'post_id'     => $post_id,
				'object_id'   => $object_id,
				'rating'      => maybe_serialize( $rating ),
				'datetime'    => current_time( 'mysql' ),
				'object_type' => $type,
				'user_ip'     => $user_ip,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return true;
	}
}

if ( ! function_exists( 'rtng_get_cookies' ) ) {
	function rtng_get_cookies() {
		try {
			if ( ! isset( $_COOKIE['bws_rtng'] ) ) {
				$cookies = array();
			} else {
				$cookies = json_decode( sanitize_text_field( wp_unslash( $_COOKIE['bws_rtng'] ) ), true );
			}
		} catch ( Exception $e ) {
			$cookies = array();
		}

		return $cookies;
	}
}

if ( ! function_exists( 'rtng_set_cookie' ) ) {
	function rtng_set_cookie( $post_id = false, $object_id = false, $rating = 100 ) {
		if ( false !== $post_id && false !== $object_id ) {
			$cookies                           = rtng_get_cookies();
			$cookies[ $post_id ][ $object_id ] = $rating;
			$cookies                           = wp_json_encode( $cookies );
			$host                              = wp_parse_url( home_url(), PHP_URL_HOST );
			setcookie( 'bws_rtng', maybe_serialize( $cookies ), 2147483647, '/', '.' . $host, is_ssl() );
		}
	}
}

if ( ! function_exists( 'rtng_clear_cookie' ) ) {
	function rtng_clear_cookie( $post_id = false ) {
		if ( false === $post_id ) {
			/* remove all cookies */
			$cookies = array();
		} else {
			/* remove only specified post id from cookies */
			$cookies = rtng_get_cookies();
			if ( isset( $cookies[ $post_id ] ) ) {
				unset( $cookies[ $post_id ] );
			}
		}
		$cookies = wp_json_encode( $cookies );
		$host    = wp_parse_url( home_url(), PHP_URL_HOST );
		setcookie( 'bws_rtng', $cookies, 2147483647, '/', '.' . $host, is_ssl() );
	}
}

if ( ! function_exists( 'rtng_is_cookie_set' ) ) {
	function rtng_is_cookie_set( $post_id = false ) {
		if ( false !== $post_id ) {
			$cookies = rtng_get_cookies();
			if ( is_array( $cookies ) && isset( $cookies[ $post_id ] ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'rtng_get_cookie_rating' ) ) {
	function rtng_get_cookie_rating( $post_id = false, $object_id = false ) {
		$cookies = rtng_get_cookies();
		if ( false !== $post_id && false !== $object_id && isset( $cookies[ $post_id ][ $object_id ] ) ) {
			return maybe_unserialize( $cookies[ $post_id ][ $object_id ] );
		}
		return false;
	}
}

/* Positioning in the page/post/comment */
if ( ! function_exists( 'rtng_add_rating_to_content' ) ) {
	function rtng_add_rating_to_content( $content ) {
		global $post, $rtng_options, $wp, $posts, $rtng_added_schemas;

		if ( is_feed() ) {
			return $content;
		}

		if ( is_page() ) {  /* pages */
			if ( ! in_array( 'page', $rtng_options['use_post_types'] ) ) {
				return $content;
			}
		} elseif ( is_single() || is_attachment() ) { /* posts */
			$post_type = get_post_type( $post->ID );
			if ( ! in_array( $post_type, $rtng_options['use_post_types'] ) ) {
				return $content;
			}
		}

		if (
			in_array( 'before', $rtng_options['rate_position'] ) ||
			in_array( 'after', $rtng_options['rate_position'] )
		) {
			if ( isset( $_POST['rtng_add_button'] ) ) {
				rtng_add_rating_db();
			}
		}

		$before      = $after = $schema = '';
		$rtng_schema = get_post_meta( $post->ID, 'rtng_exclude_schema', true );
		if ( ! empty( $rtng_options['add_schema'] ) && is_singular() && empty( $rtng_schema ) ) {
			if ( empty( $rtng_added_schemas ) ) {
				$rtng_added_schemas = array();
			}
			if ( ! in_array( $post->ID, $rtng_added_schemas ) ) {
				$schema               = rtng_get_post_schema( $post->ID );
				$rtng_added_schemas[] = $post->ID;
			}
		}

		if ( in_array( 'before', $rtng_options['average_position'] ) ) {
			$before .= rtng_show_total_rating();
		}

		if ( 1 !== absint( $rtng_options['combined'] ) && in_array( 'before', $rtng_options['rate_position'] ) ) {
			$before .= rtng_show_rating_form( array( 'type' => 'post' ) );
		}

		if ( in_array( 'after', $rtng_options['average_position'] ) ) {
			$after .= rtng_show_total_rating();
		}
		if ( 1 !== absint( $rtng_options['combined'] ) && in_array( 'after', $rtng_options['rate_position'] ) ) {
			$after .= rtng_show_rating_form( array( 'type' => 'post' ) );
		}

		return $schema . $before . $content . $after;
	}
}

/* Returns total rating block or combined rating form */
if ( ! function_exists( 'rtng_show_total_rating' ) ) {
	function rtng_show_total_rating( $args = array() ) {
		global $post, $rtng_options, $wpdb;

		$defaults = array(
			'post_id'           => false,
			'type'              => 'default',
			'show_title'        => true,
			'show_text'         => true,
			'show_review_count' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args );

		$rating = 0;
		$total_rate = '0';

		if ( ! $post_id ) {
			$post_id = $post->ID;
		}

		$total = rtng_get_post_rating( $post_id );

		if ( ! empty( $total['average'] ) ) {
			$rating = $total['average'];
			$total_rate    = rtng_get_formatted_rating( $rating );
			if ( '' === $total_rate ) {
				$total_rate = '0';
			}
		}

		if ( $show_review_count ) {
			$show_title = $show_text = false;
		}

		$title = ( ! $show_title || empty( $rtng_options['result_title'] ) ) ? '' : '<span class="rtng-text rtng-title">' . $rtng_options['result_title'] . '</span>';

		if ( $show_text && ! empty( $rtng_options['total_message'] ) ) {
			$total_message = $rtng_options['total_message'];
			$replacements = array(
				'{total_count}' => $total['count'],
				'{total_rate}'  => $total_rate,
			);
			foreach ( $replacements as $search => $replace ) {
				$total_message = str_replace( $search, $replace, $total_message );
			}
			if ( '' !== $total_message ) {
				$total_message = '<span class="rtng-text rtng-total">' . $total_message . '</span>';
			}
		} elseif ( $show_review_count ) {
			$title         = '<input type="hidden" class="rtng-review-selector" />';
			$total_message = sprintf( _n( '( %s review )', '( %s reviews )', $total['count'], 'rating-bws' ), $total['count'] );
			$total_message = '<span class="rtng-text rtng-review-count">' . $total_message . '</span>';
		} else {
			$total_message = '';
		}

		/* Combined form */
		if (
			'combined' === $type ||
			'default' === $type && 1 === absint( $rtng_options['combined'] )
		) {
			$message_login     = '';
			$current_user_rate = rtng_get_user_rating( $post_id );
			$user_id           = get_current_user_id();
			$user_role         = rtng_get_user_role( $user_id );

			if ( 'guest' === $user_role && ! rtng_is_role_enabled( 'guest' ) ) {
				$message_login = '<div class="rtng-need-login">' . preg_replace( "/{login_link=(\"|')([^(\"|')]*?)(\"|')}/", '<a href="' . wp_login_url() . '">$2</a>', htmlspecialchars_decode( $rtng_options['non_login_message'] ) ) . '</div>';
			}

			if (
				! empty( $message_login ) ||
				( $current_user_rate && ! $rtng_options['always_clickable'] ) ||
				( ! rtng_is_role_enabled() && ! $rtng_options['always_clickable'] )
			) {
				$rating_block = '<div class="rtng-rating-total" data-id="' . $post_id . '">' .
					$title .
					rtng_display_stars( $rating ) .
					$total_message .
					$message_login .
					'</div>';
			} else {
				$rating_block = '<form action="" method="post" class="rtng-form rtng-rating-total rtng-form-combined" data-id="' . $post_id . '">' .
					$title .
					rtng_display_stars( $rating, 'rtng-active' ) .
					'<input type="hidden" name="rtng_object_type" value="post">
					<input type="hidden" name="rtng_object_id" value="' . $user_id . '">
					<input type="hidden" name="rtng_post_id" value="' . $post_id . '">
					<input type="hidden" name="rtng_show_title" value="' . ( $show_title ? '1' : '0' ) . '">' .
					wp_nonce_field( plugin_basename( __FILE__ ), 'rtng_nonce_button', true, false ) .
					'<noscript><input type="submit" name="rtng_add_button" class="rtng-add-button" value="' . __( 'Rate', 'rating-bws' ) . '" /></noscript>' .
					$total_message .
					'</form>';
			}
		} else {
			/* Total rating block */
			if ( ! isset( $total_rate ) || '0' === $total_rate ) {
				$total_message = '<span class="rtng-text rtng-total">' . __( 'No rating yet', 'rating-bws' ) . '</span>';
			}

			$rating_block = '<div class="rtng-rating-total" data-id="' . $post_id . '">' .
				$title .
				rtng_display_stars( $rating ) .
				$total_message .
				'</div>';
		}

		return $rating_block;
	}
}

if ( ! function_exists( 'rtng_get_post_schema' ) ) {
	function rtng_get_post_schema( $post_id = false ) {
		global $rtng_options;
		$schema = '';
		if ( false !== $post_id ) {
			if ( empty( $rtng_options ) ) {
				rtng_settings();
			}

			$rating_data = rtng_get_post_rating( $post_id );

			if ( ( absint( $rating_data['average'] ) / 100 * 5 ) >= $rtng_options['schema_min_rate'] ) {
				$schema = sprintf(
					'<script type="application/ld+json" data-rtng-post-id="%1$d">
						{
							"@context": "http://schema.org/",
							"@type": "WebPage",
							"name": "%2$s",
							"aggregateRating": {
								"@type": "AggregateRating",
								"bestRating": "%3$d",
								"worstRating": "1",
								"ratingValue": "%4$s",
								"ratingCount": "%5$d"
							},
							"url": "%6$s"
						}
					</script>',
					$post_id,
					get_the_title( $post_id ),
					5,
					$rating_data['average'] / 100 * 5,
					$rating_data['count'],
					get_the_permalink( $post_id )
				);
			}
		}
		return $schema;
	}
}

if ( ! function_exists( 'rtng_show_rating_form' ) ) {
	function rtng_show_rating_form( $args = array() ) {
		global $post, $rtng_options;

		$defaults = array(
			'type'        => 'comment',
			'post_id'     => false,
			'show_title'  => true,
			'testimonial' => false,
			'shortcode' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		extract( $args );

		if ( empty( $post_id ) && empty( $post ) ) {
			return;
		}

		$post_id = ! empty( $post_id ) ? absint( $post_id ) : $post->ID;

		if ( 'comment' === $type && ! $testimonial && ! $shortcode ) {
			if ( is_page() ) { /* pages */
				if ( ! in_array( 'page', $rtng_options['use_post_types'] ) ) {
					return '';
				}
			} elseif ( is_single() || is_attachment() ) { /* posts */
				$post_type = get_post_type( $post_id );
				if ( ! in_array( $post_type, $rtng_options['use_post_types'] ) ) {
					return '';
				}
			}
		}

		$current_user_id   = get_current_user_id();
		$current_user_rate = false;

		if ( empty( $current_user_id ) && ! rtng_is_role_enabled( 'guest' ) ) {
			$message = preg_replace( "/{login_link=(\"|')([^(\"|')]*?)(\"|')}/", '<a href="' . wp_login_url() . '">$2</a>', htmlspecialchars_decode( $rtng_options['non_login_message'] ) );
			$message = ' <div class="rtng-need-login">' . $message . '</div>';
			if ( 'comment' === $type ) {
				echo wp_kses_post( $message );
			}
			return $message;
		} elseif ( rtng_is_role_enabled() ) {
			$rating_block = '';

			$rating_block .= '<input type="hidden" name="rtng_show_title" value="' . ( $show_title ? '1' : '0' ) . '">';

			if ( ! $testimonial ) {
				if ( ! empty( $rtng_options['vote_title'] ) && $show_title ) {
					$rating_block .= '<span class="rtng-text rtng-vote-title">' . $rtng_options['vote_title'] . '</span>';
				}

				if ( $rtng_options['always_clickable'] ) {
					$class = 'rtng-active';
					if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
						$current_user_rate = rtng_get_user_rating( $post_id, array( 'type' => $type ) );
					}
				} else {
					$current_user_rate = rtng_get_user_rating( $post_id, array( 'type' => $type ) );
					$class             = ( $current_user_rate || ! rtng_is_role_enabled() ) ? '' : 'rtng-active';
				}

				$rating_block .= rtng_display_stars( $current_user_rate, $class );
			} else if ( 1 === $rtng_options['enable_testimonials'] ) {
				foreach ( (array) $rtng_options['testimonials_titles'] as $key => $title ) {
					$rating_block .= '<div class="rtng_rating_block">';

					if ( ! empty( $title ) && $show_title ) {
						$rating_block .= '<span class="rtng-text rtng-vote-title">' . $title . '</span>';
					}

					if ( $rtng_options['always_clickable'] ) {
						$class = 'rtng-active';
						if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
							$current_user_rate = rtng_get_user_rating(
								$post_id,
								array(
									'type' => $type,
									'key'  => $key,
								)
							);
						}
					} else {
						$current_user_rate = rtng_get_user_rating(
							$post_id,
							array(
								'type' => $type,
								'key'  => $key,
							)
						);
						$class             = ( $current_user_rate || ! rtng_is_role_enabled() ) ? '' : 'rtng-active';
					}

					$rating_block .= rtng_display_stars( $current_user_rate, $class, $key );

					$rating_block .= '</div>';
				}
			}

			if ( 'comment' === $type ) {
				echo $rating_block . wp_nonce_field( plugin_basename( __FILE__ ), 'rtng_nonce_button', true, false );
			} else {
				$rating_block .= '<noscript><input type="submit" name="rtng_add_button" class="rtng-add-button" value="' . __( 'Rate', 'rating-bws' ) . '" /></noscript>
				<input type="hidden" name="rtng_object_type" value="' . $type . '">
				<input type="hidden" name="rtng_object_id" value="' . $current_user_id . '">
				<input type="hidden" name="rtng_post_id" value="' . $post_id . '">' .
				wp_nonce_field( plugin_basename( __FILE__ ), 'rtng_nonce_button', true, false );

				return '<form action="" method="post" class="rtng-form" data-id="' . $post_id . '">' . $rating_block . '</form>';
			}
		}

		return '';
	}
}

if ( ! function_exists( 'rtng_display_stars' ) ) {
	function rtng_display_stars( $rating = 0, $class = '', $key = 0 ) {
		if ( is_wp_error( $rating ) ) {
			$rating = 0;
		}
		global $rtng_options;
		$rating_block = '<div class="rtng-star-rating rtng-no-js ' . $class . '" data-rating="' . $rating . '">';

		$rating = ( $rating / 100 ) * 5;

		if ( '' !== $class ) {
			$full_stars = floor( $rating );
			$half_stars = ceil( $rating - $full_stars );

			$required = $rtng_options['rating_required'] ? 'required="required"' : '';

			for ( $i = 0; $i < $full_stars; $i++ ) {
				$rating_block .= '
					<label class="rtng-star" data-rating="' . ( $i + 1 ) . '">
						<input type="radio" ' . $required . ' name="rtng_rating[' . $key . ']" value="' . ( $i + 1 ) . '" />
						<span class="dashicons dashicons-star-filled"></span>
					</label>';
			}
			if ( $half_stars ) {
				$rating_block .= '
					<label class="rtng-star" data-rating="' . ( $i + 1 ) . '">
						<input type="radio" name="rtng_rating[' . $key . ']" value="' . ( $i + 1 ) . '" />
						<span class="dashicons dashicons-star-half"></span>
					</label>';
				$full_stars++;
			}
			for ( $j = $full_stars; $j < 5; $j++ ) {
				$rating_block .= '<label class="rtng-star" data-rating="' . ( $j + 1 ) . '">
					<input type="radio" ' . $required . ' name="rtng_rating[' . $key . ']" value="' . ( $j + 1 ) . '" />
					<span class="dashicons dashicons-star-empty"></span>
				</label>';
			}
		} else {
			$full_stars = floor( $rating );
			$half_stars = ceil( $rating - $full_stars );

			for ( $i = 0; $i < $full_stars; $i++ ) {
				$rating_block .= '
					<div class="rtng-star" data-rating="' . ( $i + 1 ) . '">
						<span class="dashicons dashicons-star-filled"></span>
					</div>';
			}
			if ( $half_stars ) {
				$rating_block .= '
					<div class="rtng-star" data-rating="' . ( $i + 1 ) . '">
						<span class="dashicons dashicons-star-half"></span>
					</div>';
				$full_stars++;
			}
			for ( $j = $full_stars; $j < 5; $j++ ) {
				$rating_block .= '<div class="rtng-star" data-rating="' . ( $j + 1 ) . '">
					<span class="dashicons dashicons-star-empty"></span>
				</div>';
			}
		}

		$rating_block .= '</div>';
		return $rating_block;
	}
}

/* Function for showing rating in comment */
if ( ! function_exists( 'rtng_show_comment_rating' ) ) {
	function rtng_show_comment_rating( $comment ) {
		global $post, $wpdb, $rtng_options;
		if ( is_page() ) { /* pages */
			if ( ! in_array( 'page', $rtng_options['use_post_types'] ) ) {
				return;
			}
		} elseif ( is_single() || is_attachment() ) { /* posts */
			$post_type = get_post_type( $post->ID );
			if ( ! in_array( $post_type, $rtng_options['use_post_types'] ) ) {
				return;
			}
		}

		if ( ! empty( $post ) ) {

			$comment_id = get_comment_ID();
			$comment_info = get_comment( $comment_id ); 

			$rating = rtng_get_user_rating(
				$post->ID,
				array(
					'type'      => 'comment',
					'object_id' => $comment_id,
					'user_ip'   => $comment_info->comment_author_IP
				)
			);

			if ( $rating ) {
				return rtng_display_stars( $rating ) . $comment;
			}
		}

		return $comment;
	}
}

if ( ! function_exists( 'rtng_rating_shortcode' ) ) {
	function rtng_rating_shortcode( $atts ) {
		global $rtng_options, $post, $rtng_added_schemas;

		$atts = shortcode_atts(
			array(
				'display' => 'default', /* default || stars || rate || both - stars and text || combined */
				'schema'  => 'default', /* default - empty || 1 - include || 0 - exclude */
				'title'   => 'default', /* default - show || 0 - do not show */
				'text'    => 'default', /* default - show || 0 - do not show */
			),
			$atts
		);

		$content = '';

		if (
			is_singular() &&
			'0' !== $atts['schema'] &&
			(
				'1' === $atts['schema'] ||
				( 'default' === $atts['schema'] && ! empty( $rtng_options['add_schema'] ) )
			)
		) {
			if ( empty( $rtng_added_schemas ) ) {
				$rtng_added_schemas = array();
			}
			if ( ! in_array( $post->ID, $rtng_added_schemas ) ) {
				$content             .= rtng_get_post_schema( $post->ID );
				$rtng_added_schemas[] = $post->ID;
			}
		}

		$total_args = $rate_form_args = array(
			'show_title' => ! ! ( 'default' === $atts['title'] ),
			'show_text'  => ! ! ( 'default' === $atts['text'] ),
		);

		$total_args['type']     = 'default';
		$rate_form_args['type'] = 'shortcode';

		if ( 'default' === $atts['display'] ) {
			if ( 1 !== absint( $rtng_options['combined'] ) ) {
				$content .= rtng_show_total_rating( $total_args ) . rtng_show_rating_form( $rate_form_args );
			} else {
				$content .= rtng_show_total_rating( $total_args );
			}
		} elseif ( 'stars' === $atts['display'] ) {
			$total_args['type'] = 'stars';
			$content           .= rtng_show_total_rating( $total_args );
		} elseif ( 'rate' === $atts['display'] ) {
			$content .= rtng_show_rating_form( $rate_form_args );
		} elseif ( 'both' === $atts['display'] ) {
			$total_args['type'] = 'stars';
			$content           .= rtng_show_total_rating( $total_args ) . rtng_show_rating_form( $rate_form_args );
		} elseif ( 'combined' === $atts['display'] ) {
			$total_args['type'] = 'combined';
			$content           .= rtng_show_total_rating( $total_args );
		}

		return $content;
	}
}

if ( ! function_exists( 'rtng_rating_votes_count_shortcode' ) ) {
	function rtng_rating_votes_count_shortcode( $atts ) {
		global $rtng_options, $post;

		$post_rating = rtng_get_post_rating( $post->ID );

		return $post_rating['count'];
	}
}

if ( ! function_exists( 'rtng_rating_value_shortcode' ) ) {
	function rtng_rating_value_shortcode( $atts ) {
		global $rtng_options, $post;

		$post_rating = rtng_get_post_rating( $post->ID );

		return rtng_get_formatted_rating( $post_rating['average'] );
	}
}

if ( ! function_exists( 'rtng_rating_max_shortcode' ) ) {
	function rtng_rating_max_shortcode( $atts ) {
		return 5;
	}
}

if ( ! function_exists( 'rtng_get_rating_block' ) ) {
	function rtng_get_rating_block( $args ) {
		$rating_block = '';

		$defaults = array(
			'display' => 'default', /* default || stars || rate || both - stars and text || combined */
			'schema'  => 'default', /* default - empty || 1 - include || 0 - exclude */
			'title'   => 'default', /* default - show || 0 - do not show */
			'text'    => 'default', /* default - show || 0 - do not show */
		);

		$args = wp_parse_args( $args, $defaults );

		return $rating_block;
	}
}

if ( ! function_exists( 'rtng_add_meta_boxes' ) ) {
	function rtng_add_meta_boxes( $post_type ) {
		global $rtng_options;

		if ( empty( $rtng_options ) ) {
			rtng_settings();
		}

		if (
			in_array( $post_type, $rtng_options['use_post_types'] ) &&
			! empty( $rtng_options['add_schema'] )
		) {
			add_meta_box(
				'rtng_post_metabox',
				__( 'Rating', 'rating-bws' ),
				'rtng_post_metabox'
			);
		}
	}
}

/**
 * Prints the meta box content.
 *
 * @param WP_Post $post The object for the current post/page.
 */
if ( ! function_exists( 'rtng_post_metabox' ) ) {
	function rtng_post_metabox( $post ) {
		global $rtng_options;

		/* Add an nonce field so we can check for it later. */
		wp_nonce_field( 'rtng_options', 'rtng_options_nonce' );
		$metabox_value = get_post_meta( $post->ID, 'rtng_exclude_schema', true );
		?>

		<div class="check-to-display">
			<table class="form-table trng-post-options">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Exclude schema', 'rating-bws' ); ?></th>
					<td scope="row">
						<fieldset>
							<label>
								<input type="checkbox" name="rtng_exclude_schema" <?php checked( $metabox_value ); ?> value="1" />
								<?php
								printf(
									esc_html__( 'Enable to exclude JSON-LD %s markup.', 'rating-bws' ),
									sprintf(
										'<a href="http://schema.org/AggregateRating">%s</a>',
										esc_html__( 'schema', 'rating-bws' )
									)
								);
								?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}

if ( ! function_exists( 'rtng_save_postdata' ) ) {
	function rtng_save_postdata( $post_id ) {
		global $rtng_options;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( empty( $rtng_options ) ) {
			rtng_settings();
		}

		$post_type = get_post_type( $post_id );

		if (
			! in_array( $post_type, $rtng_options['use_post_types'] ) ||
			empty( $rtng_options['add_schema'] ) ||
			! isset( $_POST['rtng_options_nonce'] )
		) {
			return $post_id;
		} else {
			/* Verify that the nonce is valid. */
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rtng_options_nonce'] ) ), 'rtng_options' ) ) {
				return $post_id;
			}
		}

		if ( isset( $_POST['rtng_exclude_schema'] ) ) {
			/* Update the meta field in the database. */
			update_post_meta( $post_id, 'rtng_exclude_schema', 1 );
		} else {
			delete_post_meta( $post_id, 'rtng_exclude_schema' );
		}
	}
}

/* Function for adding rating from posts to db */
if ( ! function_exists( 'rtng_add_rating_db' ) ) {
	function rtng_add_rating_db() {
		global $wpdb, $rtng_options;

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			check_ajax_referer( 'rtng_ajax_nonce', 'rtng_nonce' );
			if ( isset( $_POST['rtng_post_id'] ) && isset( $_POST['rtng_rating_id'] ) && isset( $_POST['rtng_object_id'] ) && isset( $_POST['rtng_rating_val'] ) ) {
				$post_id   = absint( $_POST['rtng_post_id'] );
				$object_id = absint( $_POST['rtng_object_id'] );
				$rating    = absint( $_POST['rtng_rating_val'] );

				/* Get options from the database */
				if ( empty( $rtng_options ) ) {
					rtng_settings();
				}

				if ( 5 < $rating ) {
					$rating = 5;
				}

				$percent_rating = ( $rating / 5 ) * 100;

				$args = array(
					'post_id'   => $post_id,
					'object_id' => $object_id,
					'rating'    => array( absint( $_POST['rtng_rating_id'] ) => number_format( $percent_rating, 2, '.', '' ) ),
				);
				$add  = rtng_add_user_rating( $args );

				$total_args         = array(
					'post_id' => $post_id,
				);
				$rate_form_args     = array(
					'type'    => 'shortcode',
					'post_id' => $post_id,
				);
				$combined_form_args = array(
					'type'    => 'combined',
					'post_id' => $post_id,
				);

				$total    = rtng_show_total_rating( $total_args );
				$rate     = rtng_show_rating_form( $rate_form_args );
				$combined = rtng_show_total_rating( $combined_form_args );

				if ( $add ) {
					$message = $rtng_options['thankyou_message'];
				} elseif ( ! rtng_is_role_enabled() ) {
					$message = $rtng_options['error_message'];
				} else {
					$message = $rtng_options['already_rated_message'];
				}
				$message = '<span class="rtng-text rtng-thankyou"> ' . $message . '</span>';

				echo wp_json_encode( compact( 'total', 'rate', 'combined', 'message' ) );
			}
			die();
		} elseif ( isset( $_REQUEST['rtng_add_button'] ) && check_admin_referer( plugin_basename( __FILE__ ), 'rtng_nonce_button' ) ) {
			if ( rtng_is_role_enabled() && ! empty( $_POST['rtng_post_id'] ) && ! empty( $_POST['rtng_object_id'] ) && ! empty( $_POST['rtng_rating'] ) ) {
				$post_id   = absint( $_POST['rtng_post_id'] );
				$object_id = absint( $_POST['rtng_object_id'] );

				$percent_rating = rtng_get_user_rating( $post_id, array( 'object_id' => $object_id ) );

				if ( empty( $percent_rating ) ) {
					$rating = absint( $_POST['rtng_rating'] );
					if ( 5 < $rating ) {
						$rating = 5;
					}
					$percent_rating = ( absint( $rating ) / 5 ) * 100;

					$args = array(
						'post_id'   => absint( $post_id ),
						'object_id' => absint( $object_id ),
						'rating'    => number_format( $percent_rating, 2, '.', '' ),
					);
					rtng_add_user_rating( $args );
				}
			}
		}
	}
}

/* Function for adding rating from comments to db */
if ( ! function_exists( 'rtng_add_rating_db_comment' ) ) {
	function rtng_add_rating_db_comment( $comment_id ) {
		global $wpdb, $rtng_options;

		if ( rtng_is_role_enabled() && ! empty( $_POST['rtng_rating'] ) && check_admin_referer( plugin_basename( __FILE__ ), 'rtng_nonce_button' ) ) {
			$rating = isset( $_POST['rtng_rating'] ) && is_array( $_POST['rtng_rating'] ) && isset( $_POST['rtng_rating'][0] ) ? absint( $_POST['rtng_rating'][0] ) : absint( $_POST['rtng_rating'] );

			if ( 5 < $rating ) {
				$rating = 5;
			}

			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT `comment_post_ID`
				FROM `' . $wpdb->prefix . 'comments`
				WHERE `comment_ID` = %d',
					$comment_id
				)
			);

			if ( $post_id && $rating ) {
				$percent_rating = ( $rating / 5 ) * 100;

				$args = array(
					'post_id'   => absint( $post_id ),
					'object_id' => absint( $comment_id ),
					'rating'    => number_format( $percent_rating, 2, '.', '' ),
					'type'      => 'comment',
				);
				rtng_add_user_rating( $args );
			}
		} elseif ( empty( $_POST['rtng_rating'] ) && $rtng_options['rating_required'] ) {
			$error = new WP_Error();
			$error->add( 'rtng_rating_error', esc_html__( 'Error: Rating is required', 'rating-bws' ) );
			wp_delete_comment( $comment_id, true );
			wp_die(
				'<p>' . esc_html( $error->get_error_message() ) . '</p>',
				esc_html__( 'Error: Rating is required.', 'rating-bws' ),
				array(
					'back_link' => true,
				)
			);
		}
	}
}

if ( ! function_exists( 'rtng_pagination_callback' ) ) {
	function rtng_pagination_callback( $content ) {
		$content .= "$( '.rtng-star-rating' ).removeClass( 'rtng-no-js' );";
		return $content;
	}
}

/* add shortcode content  */
if ( ! function_exists( 'rtng_shortcode_button_content' ) ) {
	function rtng_shortcode_button_content( $content ) {
		$display_types = array(
			'default'  => __( 'Use plugin settings', 'rating-bws' ),
			'stars'    => __( 'Average Rating Only', 'rating-bws' ),
			'rate'     => __( 'Rate Option Only', 'rating-bws' ),
			'both'     => __( 'Average Rating and Rate Option', 'rating-bws' ),
			'combined' => __( 'Combined', 'rating-bws' ),
		);
		$shema_types   = array(
			'default' => __( 'Use plugin settings', 'rating-bws' ),
			'1'       => __( 'Include', 'rating-bws' ),
			'0'       => __( 'Exclude', 'rating-bws' ),
		);
		?>
		<div id="rtng" style="display:none;">
			<span style="vertical-align: middle;"><?php esc_html_e( 'Select Shortcode', 'rating-bws' ); ?>:</span>&emsp;
			<select class="rtng-shortcode">
				<option value="rating" selected="selected"><?php esc_html_e( 'Rating Block', 'rating-bws' ); ?></option>
				<option value="rating-votes"><?php esc_html_e( 'Total Rates Number', 'rating-bws' ); ?></option>
				<option value="rating-value"><?php esc_html_e( 'Average Rating', 'rating-bws' ); ?></option>
				<option value="rating-max"><?php esc_html_e( 'Max Stars Available', 'rating-bws' ); ?></option>
			</select>
			<div class="rtng-rating rtng-shortcode-block">
				<h4><?php esc_html_e( 'Add Rating block to your page or post', 'rating-bws' ); ?></h4>
				<table>
					<tr>
						<td>
							<span><?php esc_html_e( 'Display', 'rating-bws' ); ?>:</span>&emsp;
						</td>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="rtng_display_title" value="1" <?php checked( true ); ?> />&nbsp;
									<span class="checkbox-title"><?php esc_html_e( 'rating block title', 'rating-bws' ); ?></span>
								</label>
								<br />
								<label>
									<input type="checkbox" name="rtng_display_text" value="1" <?php checked( true ); ?> />&nbsp;
									<span class="checkbox-title"><?php esc_html_e( 'rating block text', 'rating-bws' ); ?></span>
								</label>
							</fieldset>
						</td>
					</tr>
				</table>
				<p>
					<span style="vertical-align: middle;"><?php esc_html_e( 'Output', 'rating-bws' ); ?>:</span>
					<select name="rtng_type_display" >
						<?php foreach ( $display_types as $type => $label ) { ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $type, 'default' ); ?> >
								<?php echo esc_html( $label ); ?>
							</option>
						<?php } ?>
					</select>
				</p>
				<p>
					<span style="vertical-align: middle;"><?php esc_html_e( 'Schema', 'rating-bws' ); ?>:</span>
					<select name="rtng_type_schema" >
						<?php foreach ( $shema_types as $type => $label ) { ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $type, 'default' ); ?> >
								<?php echo esc_html( $label ); ?>
							</option>
						<?php } ?>
					</select>
				</p>
			</div><!-- .rtng-rating -->
			<div class="rtng-rating-votes rtng-shortcode-block" class="hidden">
				<h4><?php esc_html_e( 'Display total number of ratings.', 'rating-bws' ); ?></h4>
			</div><!-- .rtng-rating-total -->
			<div class="rtng-rating-value rtng-shortcode-block" class="hidden">
				<h4><?php esc_html_e( 'Display average post rating.', 'rating-bws' ); ?></h4>
			</div><!-- .rtng-rating-average -->
			<div class="rtng-rating-max rtng-shortcode-block" class="hidden">
				<h4><?php esc_html_e( 'Display available number of stars.', 'rating-bws' ); ?></h4>
			</div><!-- .rtng-rating-max -->
			<input class="bws_default_shortcode" type="hidden" name="default" value="[bws-rating]" />
			<div class="clear"></div>
		</div>
		<script type="text/javascript">
			function rtng_shortcode_init() {
				( function( $ ) {
					$( '.mce-reset select.rtng-shortcode' ).on( 'change', function() {
						var shortcode_block = $( this ).val();
						$( '.mce-reset .rtng-shortcode-block' ).hide();
						$( '.mce-reset .rtng-shortcode-block.rtng-' + shortcode_block ).show();
					} ).trigger( 'change' );

					$( '.mce-reset select.rtng-shortcode, .mce-reset [name^="rtng_type_"], .mce-reset [name^="rtng_display_"]' ).on( 'change', function() {
						var shortcode = $( '.mce-reset select.rtng-shortcode' ).val();
						if ( 'rating' == shortcode ) {
							var result = '';

							display = $( '.mce-reset select[name="rtng_type_display"]' ).val();

							if ( display != 'default' ) {
								result += ' display="' + display +'"';
							}

							schema = $( '.mce-reset select[name="rtng_type_schema"]' ).val();
							if ( schema != 'default' ) {
								result += ' schema="' + schema +'"';
							}

							if ( ! $( '.mce-reset input[name="rtng_display_title"]' ).is( ':checked' ) ) {
								result += ' title="0"';
							}

							if ( ! $( '.mce-reset input[name="rtng_display_text"]' ).is( ':checked' ) ) {
								result += ' text="0"';
							}

							$( '.mce-reset #bws_shortcode_display' ).text( '[bws-rating' + result + ']' );
						} else if ( 'rating-votes' == shortcode ) {
							$( '.mce-reset #bws_shortcode_display' ).text( '[bws-rating-votes]' );
						} else if ( 'rating-value' == shortcode ) {
							$( '.mce-reset #bws_shortcode_display' ).text( '[bws-rating-value]' );
						} else if ( 'rating-max' == shortcode ) {
							$( '.mce-reset #bws_shortcode_display' ).text( '[bws-rating-max]' );
						}
					} );
				} ) ( jQuery );
			}
		</script>
		<?php
	}
}

/* Function to add plugin scripts*/
if ( ! function_exists( 'rtng_admin_head' ) ) {
	function rtng_admin_head() {
		wp_enqueue_style( 'rtng_icon', plugins_url( 'css/icon.css', __FILE__ ), array(), '1.4' );
		if ( isset( $_GET['page'] ) && 'rating.php' === $_GET['page'] ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'rtng_script', plugins_url( 'js/admin_script.js', __FILE__ ), array( 'jquery', 'wp-color-picker' ), '1.4', true );

			bws_enqueue_settings_scripts();
			bws_plugins_include_codemirror();
		}
	}
}

if ( ! function_exists( 'rtng_wp_head' ) ) {
	function rtng_wp_head() {
		global $rtng_options;
		wp_enqueue_style( 'rtng_stylesheet', plugins_url( 'css/style.css', __FILE__ ), array( 'dashicons' ), '1.4' );
		?>
		<style type="text/css">
			.rtng-star .dashicons {
				color: <?php echo esc_attr( $rtng_options['rate_color'] ); ?> !important;
				font-size: <?php echo esc_attr( $rtng_options['rate_size'] ); ?>px;
			}
			.rtng-star .dashicons.rtng-hovered,
			.rtng-star-rating.rtng-no-js .rtng-star input:checked + .dashicons::before {
				color: <?php echo esc_attr( $rtng_options['rate_hover_color'] ); ?> !important;
			}
			.rtng-text {
				color: <?php echo esc_attr( $rtng_options['text_color'] ); ?> !important;
				font-size: <?php echo esc_attr( $rtng_options['text_size'] ); ?>px;
			}
			.rtng-rate-bar-wrap {
				height: 10px;
				border: 1px solid <?php echo esc_attr( $rtng_options['rate_color'] ); ?>;
			}
			.rtng-rate-bar {
				height: 100%;
				background-color: <?php echo esc_attr( $rtng_options['rate_color'] ); ?>;
			}
		</style>
		<?php
	}
}

/* Add necessary js scripts */
if ( ! function_exists( 'rtng_add_scripts' ) ) {
	function rtng_add_scripts() {
		global $rtng_options;

		wp_enqueue_script( 'rtng_bws_cookies', bws_menu_url( 'js/c_o_o_k_i_e.js' ), array( 'jquery' ), '1.4', true );
		wp_enqueue_script( 'rtng_scripts', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery', 'rtng_bws_cookies' ), '1.4', true );
		/* Enqueued script with localized data.*/
		$args = array(
			'ajaxurl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'rtng_ajax_nonce' ),
			'cookies_host' => wp_parse_url( home_url(), PHP_URL_HOST ),
			'secure'       => is_ssl(),
		);
		wp_localize_script( 'rtng_scripts', 'rtng_vars', $args );
	}
}

/* Functions creates other links on plugins page. */
if ( ! function_exists( 'rtng_action_links' ) ) {
	function rtng_action_links( $links, $file ) {
		if ( ! is_network_admin() ) {
			/* Static so we don't call plugin_basename on every plugin row. */
			static $this_plugin;
			if ( ! $this_plugin ) {
				$this_plugin = plugin_basename( __FILE__ );
			}
			if ( $file === $this_plugin ) {
				$settings_link = '<a href="admin.php?page=rating.php">' . __( 'Settings', 'rating-bws' ) . '</a>';
				array_unshift( $links, $settings_link );
			}
		}
		return $links;
	}
}

if ( ! function_exists( 'rtng_links' ) ) {
	function rtng_links( $links, $file ) {
		$base = plugin_basename( __FILE__ );
		if ( $file === $base ) {
			if ( ! is_network_admin() ) {
				$links[] = '<a href="admin.php?page=rating.php">' . __( 'Settings', 'rating-bws' ) . '</a>';
			}
			$links[] = '<a href="https://support.bestwebsoft.com/hc/en-us/sections/203319926" target="_blank">' . __( 'FAQ', 'rating-bws' ) . '</a>';
			$links[] = '<a href="https://support.bestwebsoft.com">' . __( 'Support', 'rating-bws' ) . '</a>';
		}
		return $links;
	}
}

/* add help tab  */
if ( ! function_exists( 'rtng_add_tabs' ) ) {
	function rtng_add_tabs() {
		$screen = get_current_screen();
		$args   = array(
			'id'      => 'rtng',
			'section' => '203319926',
		);
		bws_help_tab( $screen, $args );
	}
}

if ( ! function_exists( 'rtng_plugin_banner' ) ) {
	function rtng_plugin_banner() {
		global $hook_suffix, $rtng_plugin_info;
		if ( 'plugins.php' === $hook_suffix ) {
			if ( ! is_network_admin() ) {
				bws_plugin_banner_to_settings( $rtng_plugin_info, 'rtng_options', 'rating-bws', 'admin.php?page=rating.php' );
			}
		}
		if ( isset( $_REQUEST['page'] ) && 'rating.php' === $_REQUEST['page'] ) {
			bws_plugin_suggest_feature_banner( $rtng_plugin_info, 'rtng_options', 'rating-bws' );
		}
	}
}

/* Function for delete options */
if ( ! function_exists( 'rtng_delete_options' ) ) {
	function rtng_delete_options() {
		global $wpdb;

		if ( is_multisite() ) {
			$old_blog = $wpdb->blogid;
			/* Get all blog ids */
			$blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
			foreach ( $blogids as $blog_id ) {
				switch_to_blog( $blog_id );
				delete_option( 'rtng_options' );
				$wpdb->query( 'DROP TABLE `' . $wpdb->prefix . 'bws_rating`;' );
			}
			switch_to_blog( $old_blog );
		} else {
			delete_option( 'rtng_options' );
			$wpdb->query( 'DROP TABLE `' . $wpdb->prefix . 'bws_rating`;' );
		}

		require_once dirname( __FILE__ ) . '/bws_menu/bws_include.php';
		bws_include_init( plugin_basename( __FILE__ ) );
		bws_delete_plugin( plugin_basename( __FILE__ ) );
	}
}

/* Calling a function add administrative menu. */
add_action( 'admin_menu', 'add_rtng_menu' );
add_action( 'plugins_loaded', 'rtng_plugins_loaded' );
add_action( 'init', 'rtng_init' );
add_action( 'admin_init', 'rtng_admin_init' );
/* Adding stylesheets */
add_action( 'admin_enqueue_scripts', 'rtng_admin_head' );
add_action( 'wp_enqueue_scripts', 'rtng_wp_head' );
add_action( 'wp_footer', 'rtng_add_scripts' );

add_shortcode( 'bws-rating', 'rtng_rating_shortcode' );
add_shortcode( 'bws-rating-votes', 'rtng_rating_votes_count_shortcode' );
add_shortcode( 'bws-rating-value', 'rtng_rating_value_shortcode' );
add_shortcode( 'bws-rating-max', 'rtng_rating_max_shortcode' );
/* Adds a box to the main column on the Posts edit screens. */
add_action( 'add_meta_boxes', 'rtng_add_meta_boxes' );
add_action( 'attachment_updated', 'rtng_save_postdata' );
add_action( 'save_post', 'rtng_save_postdata' );

add_filter( 'pgntn_callback', 'rtng_pagination_callback' );

/* custom filter for bws button in tinyMCE */
add_filter( 'bws_shortcode_button_content', 'rtng_shortcode_button_content' );
/*## Additional links on the plugin page */
add_filter( 'plugin_action_links', 'rtng_action_links', 10, 2 );
add_filter( 'plugin_row_meta', 'rtng_links', 10, 2 );
/* Adding banner */
add_action( 'admin_notices', 'rtng_plugin_banner' );
/* Plugin uninstall function */

add_action( 'wp_ajax_rtng_add_rating_db', 'rtng_add_rating_db' );
add_action( 'wp_ajax_nopriv_rtng_add_rating_db', 'rtng_add_rating_db' );

register_uninstall_hook( __FILE__, 'rtng_delete_options' );
