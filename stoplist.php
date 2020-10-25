<?php
/**
 * Stoplist
 *
 * @package           Stoplist
 * @author            Alexander Kadyrov <alexander@kadyrov.dev>
 * @copyright         2020 Alexander Kadyrov
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Stoplist
 * Github URI:        https://github.com/gruz0/stoplist
 * Description:       Change post's status (or remove) if it has forbidden tags
 * Version:           1.0.0
 * Requires at least: 5.1
 * Requires PHP:      7.0
 * Author:            Alexander Kadyrov
 * Author URI:        https://kadyrov.dev/
 * Text Domain:       stoplist
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace Stoplist;

if ( ! defined( 'WPINC' ) ) {
	die();
}

define( 'STOPLIST_OPTION_KEY', 'stoplist_options' );

define( 'STOPLIST_NONCE', 'stoplist_nonce' );

define( 'STOPLIST_UPDATE_OPTIONS_ACTION', 'stoplist_update_options' );

add_filter( 'use_block_editor_for_post', '__return_false' );

/**
 * Checks post is in stoplist
 *
 * @since 1.0.0
 *
 * @param integer $post_id Post ID.
 */
function check_post( $post_id ) {
	$parent_id = wp_is_post_revision( $post_id );

	if ( $parent_id ) {
		$post_id = $parent_id;
	}

	if ( ! is_processable( $post_id ) ) {
		return;
	}

	remove_action( 'save_post', 'Stoplist\check_post' );

	update_post_status( $post_id );

	add_action( 'save_post', 'Stoplist\check_post' );
}

add_action( 'save_post', 'Stoplist\check_post' );

/**
 * Checks is post processable
 *
 * @since 1.0.0
 *
 * @param integer $post_id Post ID.
 *
 * @return boolean
 */
function is_processable( $post_id ) {
	if ( ! is_in_allowed_status( $post_id ) ) {
		return false;
	}

	//phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST ) ) {
		return false;
	}

	if ( ! empty( $_POST['data'] ) && ! empty( $_POST['data']['wp_autosave'] ) ) {
		return false;
	}
	//phpcs:enable WordPress.Security.NonceVerification.Missing

	return is_post_has_forbidden_tags( $post_id );
}

/**
 * Checks if post is in allowed status to perform action
 *
 * @since 1.0.0
 *
 * @param integer $post_id Post ID.
 * @return boolean
 */
function is_in_allowed_status( $post_id ) {
	$status           = get_post_status( $post_id );
	$allowed_statuses = array( 'inherit', 'future', 'publish' );

	return in_array( $status, $allowed_statuses, true );
}

/**
 * Checks is tags in forbidden list
 *
 * @since 1.0.0
 *
 * @param integer $post_id Post ID.
 * @return boolean
 */
function is_post_has_forbidden_tags( $post_id ) {
	$tags_list = tags_list();

	if ( has_tag( $tags_list, $post_id ) ) {
		return true;
	}

	//phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['tax_input'] ) || empty( $_POST['tax_input']['post_tag'] ) ) {
		return false;
	}

	$new_tags = convert_new_tags( $_POST['tax_input']['post_tag'] );
	//phpcs:enable WordPress.Security.NonceVerification.Missing

	return is_new_tags_in_forbidden_tags( $new_tags, $tags_list );
}

/**
 * Stoplisted tags
 *
 * @since 1.0.0
 *
 * @return array
 */
function tags_list() {
	$tags = get_option_by_key( 'tags', array() );

	return array_filter( array_unique( array_map( 'mb_strtolower', $tags ) ) );
}

/**
 * Converts tags from $_POST into an array
 *
 * @since 1.0.0
 *
 * @param string $new_tags New tags.
 * @return array
 */
function convert_new_tags( $new_tags ) {
	$tags = mb_split( ',', $new_tags );
	$tags = array_filter( array_unique( array_map( 'mb_strtolower', $tags ) ) );

	return $tags;
}

/**
 * Checks is new tags in the forbidden tags
 *
 * @since 1.0.0
 *
 * @param array $new_tags New tags.
 * @param array $forbidden_tags Forbidden tags.
 * @return boolean
 */
function is_new_tags_in_forbidden_tags( $new_tags, $forbidden_tags ) {
	foreach ( $new_tags as $tag ) {
		if ( in_array( $tag, $forbidden_tags, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Updates or removes post depends on required action from the settings
 *
 * @since 1.0.0
 *
 * @param integer $post_id Post ID.
 */
function update_post_status( $post_id ) {
	switch ( get_option_by_key( 'action', 'nothing' ) ) {
		case 'draft':
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				)
			);
			break;
		case 'private':
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'private',
				)
			);
			break;
		case 'trash':
			wp_trash_post( $post_id );
			break;
	}
}

/**
 * Adds menu
 *
 * @since 1.0.0
 */
function add_menu() {
	$page_title = 'Stoplist Options';
	$menu_title = 'Stoplist';
	$capability = 'manage_options';
	$function   = 'Stoplist\render_options_page';

	add_submenu_page( 'options-general.php', $page_title, $menu_title, $capability, 'stoplist', $function );
}

add_action( 'admin_menu', 'Stoplist\add_menu' );

/**
 * Updates options
 *
 * @since 1.0.0
 */
function update_options() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized user' );
	}

	if ( empty( $_POST[ STOPLIST_NONCE ] ) ) {
		wp_die( 'Nonce must be set' );
	}

	if ( ! wp_verify_nonce( $_POST[ STOPLIST_NONCE ], 'stoplist' ) ) {
		wp_die( 'Invalid nonce' );
	}

	$new_options = array_merge( get_plugin_options(), $_POST['stoplist'] );

	// Action.
	$action                = trim( $new_options['action'] );
	$allowed_actions       = array( 'nothing', 'draft', 'private', 'trash' );
	$new_options['action'] = in_array( $action, $allowed_actions, true ) ? $action : 'nothing';

	// Tags.
	$tags = array_filter( array_unique( array_map( 'trim', mb_split( "\n", $new_options['tags'] ) ) ) );
	sort( $tags );
	$new_options['tags'] = $tags;

	update_option( STOPLIST_OPTION_KEY, $new_options );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'stoplist',
				'updated' => true,
			),
			admin_url( 'options-general.php' )
		),
		303
	);

	exit;
}

add_action( 'admin_post_' . STOPLIST_UPDATE_OPTIONS_ACTION, 'Stoplist\update_options' );

/**
 * Render options page
 *
 * @since 1.0.0
 */
function render_options_page() {
	$action = get_option_by_key( 'action', '' );
	$tags   = get_option_by_key( 'tags', array() );
	?>
	<div class="wrap">
	<h1>Stoplist</h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'stoplist', STOPLIST_NONCE ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( STOPLIST_UPDATE_OPTIONS_ACTION ); ?>" />

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">Change post status to</th>
					<td>
						<select name="stoplist[action]">
							<option value="nothing" <?php selected( 'nothing' === $action ); ?>>Nothing</option>
							<option value="draft" <?php selected( 'draft' === $action ); ?>>Draft</option>
							<option value="private" <?php selected( 'private' === $action ); ?>>Private</option>
							<option value="trash" <?php selected( 'trash' === $action ); ?>>Trash</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Tags (one per line)</th>
					<td>
						<textarea rows="10" cols="50" name="stoplist[tags]"><?php echo esc_html( join( "\n", $tags ) ); ?></textarea>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<input type="submit" value="Save Changes" class="button button-primary button-large">
		</p>
	</form>
	<?php
}

/**
 * Returns options otherwise returns an empty array
 *
 * @since 1.0.0
 *
 * @return array
 */
function get_plugin_options() {
	return get_option( STOPLIST_OPTION_KEY, array() );
}

/**
 * Returns option's value by key
 *
 * @since 1.0.0
 *
 * @param string $key Option key.
 * @param mixed  $default Default value.
 * @return mixed;
 */
function get_option_by_key( $key, $default = null ) {
	$options = get_plugin_options();

	return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

//phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
//phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r
/**
 * Writes content to debug.log
 *
 * @since 1.0.0
 *
 * @param mixed $log Object to log.
 */
function write_log( $log ) {
	if ( is_array( $log ) || is_object( $log ) ) {
		error_log( print_r( $log, true ) );
	} else {
		error_log( $log );
	}
}
//phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
//phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_print_r
