<?php
/**
 * Plugin Name:       Theme.json Manager
 * Description:       Configure and override theme.json settings.
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       theme-json-manager
 *
 * @package           theme json manager
 */

/**
 * Output the theme options page.
 */
function theme_json_manager_options_page_html() {
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php
		if ( ! wp_theme_has_theme_json() ) {
			echo '<p>' . esc_html__( 'This theme does not have a theme.json file.', 'theme-json-manager' ) . '</p>';
			echo '</div>';
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to manage theme.json settings.', 'theme-json-manager' ) . '</p>';
			echo '</div>';
			return;
		}
		if ( isset( $_POST['submit'] )) {
			esc_html_e( 'Settings saved.', 'theme-json-manager' );
		}
		if ( isset( $_POST['reset'] )) {
			esc_html_e( 'Settings reset.', 'theme-json-manager' );
		}
		?>
		<p><?php esc_html_e( 'Configure and override theme.json settings.', 'theme-json-manager' ); ?></p>
		<p><?php esc_html_e( 'Font families, adding custom font sizes, and adding custom spacing presets is not supported.', 'theme-json-manager' ); ?></p>
		<form action="<?php menu_page_url( 'theme_json_manager' ) ?>" method="post">
		<table class="form-table" role="presentation"><tbody>
		<?php
		if ( get_option( 'theme_json_manager' ) && ! empty( get_option( 'theme_json_manager' )['settings']) ) {
			$settings_data = get_option( 'theme_json_manager' );
			$settings_data = $settings_data['settings'];
		} else {
			// If no settings have been saved, use the global theme.json settings.
			$settings_data = wp_get_global_settings();
		}
		foreach ( $settings_data as $key => $value ) {
			// Skip some settings we don't care about:
			if ( $key != 'blocks' && $key != 'layout' && $key != 'appearanceTools' ) {
				if ( ! is_array( $value ) ) {
					$checked = $value === true ? 'checked' : '';
					echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $key ) . '</label></th>
					<td>
					<input type="hidden" name="' . esc_attr( $key ) . '" value="false">
					<input type="checkbox" ' . $checked . ' name="' . esc_attr( $key )  . '" value="true">
					</td>
					</tr>';
				} else {
					echo '<tr><th scope="row"><label for="' . esc_attr( $key )  . '">' . esc_html( $key ) . '</label></th>';
					echo '<td><fieldset><legend class="screen-reader-text"><span>' . esc_html( $key ) . '</span></legend>';
					foreach( $value as $nested_key => $value ) {
						if ( ! is_array( $value ) ) {
							$checked = $value === true ? 'checked' : '';
							echo '<label>
							<input type="hidden" name="' . esc_attr( $key ) . '[' . esc_attr( $nested_key ) . '] " value="false">
							<input type="checkbox" ' . $checked . ' name="' . esc_attr( $key ) . '[' . esc_attr( $nested_key ) . '] " value="true">' .
							$nested_key . '
							</label><br>';
						} else {
							if ( $nested_key === 'fontSizes' ) {
								$checked = is_array( $value ) && ! empty( $value ) ? 'checked' : '';
								echo '<label>
								<input type="hidden" name="' . esc_attr( $key ) . '[' . esc_attr( $nested_key ) . '] " value="[]">
								<input type="checkbox" ' . $checked . ' name="' . esc_attr( $key ) . '[' . esc_attr( $nested_key ) . ']">' .
								$nested_key . '
								</label><br>';
							}
						}
					}
					echo '</fieldset>';
					echo '</td></tr>';
				}
			}
		}
		echo '</tbody></table>';
		wp_nonce_field( 'theme-json-manager', 'theme-json-manager-nonce' );
		submit_button( __( 'Save', 'theme-json-manager' ) );
		echo '<p class="reset"><input type="submit" name="reset" id="reset" class="button button-secondary" value="Reset to theme default"></p>';
		?>
		</form>
	</div>
	<?php
}

/**
 * Add menu item and options page under Appearance.
 */
function theme_json_manager_options_page() {
	$hookname = add_submenu_page(
		'themes.php',
		'Manage theme.json',
		'Manage theme.json',
		'manage_options',
		'theme_json_manager',
		'theme_json_manager_options_page_html'
	);
	add_action( 'load-' . $hookname, 'theme_json_manager_options_page_submit' );
}
add_action('admin_menu', 'theme_json_manager_options_page');

/**
 * Handle form submission.
 */
function theme_json_manager_options_page_submit() {
	if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
		check_admin_referer( 'theme-json-manager', 'theme-json-manager-nonce' );
	} else {
		return;
	}

	if ( isset( $_POST['reset'] ) ) {
		delete_option( 'theme_json_manager' );
		return;
	}

	if ( isset( $_POST['submit'] ) ) {
		$new_settings = array();
		$new_settings['version'] = 2;
		$new_settings['settings'] = $_POST;

		// Unset options unrelated to theme.json:
		unset($new_settings['settings']['_wp_http_referer']);
		unset($new_settings['settings']['submit']);
		unset($new_settings['settings']['theme-json-manager-nonce']);

		foreach ( $new_settings['settings'] as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $nested_key => $nested_value ) {
					if ( $nested_key === 'fontSizes' ) {
						// Font sizes are disabled, add an empty array.
						if ( $nested_value === '[]' ) {
							$new_settings['settings'][$key][$nested_key] = array();
						} else {
							/**
							 * Re-add the theme font sizes.
							 * To use the default font sizes, replace 'theme' with 'default'.
							*/
							$settings_data = wp_get_global_settings();
							$new_settings['settings'][$key][$nested_key] = $settings_data['typography']['fontSizes']['theme'];
						}
					} elseif ( $nested_key === 'fontFamilies' ) {
						// Font families are disabled, add an empty array.
						if ( $nested_value === 'false' ) {
							$new_settings['settings'][$key][$nested_key] = array();
						}
					} else {
						if ( $nested_value === 'true' ) {
							$new_settings['settings'][$key][$nested_key] = true;
						} else {
							$new_settings['settings'][$key][$nested_key] = false;
						}
					}
				}
			} else {
				if ( $value === 'true' ) {
					$new_settings['settings'][$key] = true;
				} else {
					$new_settings['settings'][$key] = false;
				}
			}
		}
		if ( get_option( 'theme_json_manager' ) ) {
			update_option( 'theme_json_manager', $new_settings );
		} else {
			add_option( 'theme_json_manager', $new_settings );
		}
	}
}

/**
 * Filter the theme.json data.
 */
function theme_json_manager_filter( $theme_json ) {
	if ( get_option( 'theme_json_manager' ) ) {
		$new_data = get_option( 'theme_json_manager' );
		return $theme_json->update_with( $new_data );
	}
	return $theme_json;
}
add_filter( 'wp_theme_json_data_theme', 'theme_json_manager_filter' );

/**
 * Delete the option when the plugin is deactivated.
 */
register_deactivation_hook(
	__FILE__,
	'theme_json_manager_deactivate'
);
function theme_json_manager_deactivate() {
	delete_option( 'theme_json_manager' );
}
