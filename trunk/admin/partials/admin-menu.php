<?php
/**
 * Render admin menu page with options, if you have
 *
 * @version 1.1.0
 */

// Check that the user is allowed to update options
if ( current_user_can( 'manage_options' ) == false ) {
	wp_die('You do not have sufficient permissions to access this page.');
}
$scope_label   = $this->get_scope_label();
$shared_sites  = $this->get_sites_sharing_api_key();
$client_id     = (string) get_option( 'relevatracking_client_id' );
$last_callback = (int) get_option( 'relevatracking_last_callback' );
?>
<div class="wrap">

	<h2><?php echo __( 'releva.nz', $this->plugin_name) ;//esc_html( get_admin_page_title() ) ?></h2>

	<p>
		<h3><?php
		// Einstellungen
		_e( 'Settings', $this->plugin_name) ?></h3>
	</p>

	<?php if ( $scope_label ): ?>
		<p><em><?php echo esc_html( $scope_label ) ?></em></p>
	<?php endif ?>

	<?php if ( $shared_sites ): ?>
		<div class="notice notice-warning inline"><p>
			<?php echo esc_html( __( 'This API key is also configured on other sites of this network. Every shop needs its own releva.nz API key, otherwise tracking and product data of the shops get mixed up:', $this->plugin_name ) ) ?>
			<?php echo esc_html( implode( ', ', $shared_sites ) ) ?>
		</p></div>
	<?php endif ?>

	<?php if ( $this->options ): ?>

		<p>
			<form method="post" action="options.php">

				<?php settings_fields( $this->get_id() . '_group' ) ?>
				<?php do_settings_sections( $this->get_id() . '_group' ) ?>

				<!-- // show error/update messages -->
				<?php settings_errors( $this->plugin_name) ; ?>


				<table class="form-table">
					<tbody>
						<?php foreach ( $this->options as $option ): ?>

							<?php if ( $option['type'] == 'text' ): ?>

								<tr valign="top">
									<th scope="row">
										<label for="<?php echo esc_attr( $option['id'] ) ?>"><?php echo esc_html( $option['label'] ) ?></label>
									</th>
									<td>
										<input type="text" name="<?php echo esc_attr( $option['id'] ) ?>" id="<?php echo esc_attr( $option['id'] ) ?>" value="<?php echo esc_attr( get_option( $option['id'] ) ) ?>" size="40">
										<?php if ( $option['hint'] ): ?>
											<small><?php echo esc_html( $option['hint'] ) ?></small>
										<?php endif ?>
									</td>
								</tr>

								<?php if ( $option['name'] == 'api_key' ): ?>
									<tr valign="top">
										<th scope="row"><?php _e( 'Campaign ID', $this->plugin_name ) ?></th>
										<td>
											<input type="text" id="relevatracking_client_id" value="<?php echo esc_attr( $client_id ) ?>" size="10" readonly="readonly">
											<small>
												<?php echo esc_html( $client_id !== '' ? __( 'API key validated', $this->plugin_name ) : __( 'API key not validated yet', $this->plugin_name ) ) ?>
												<?php if ( $last_callback ): ?>
													&middot; <?php /* translators: %s: date and time of the last callback */ echo esc_html( sprintf( __( 'Last call from releva.nz: %s', $this->plugin_name ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_callback + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ) ) ?>
												<?php endif ?>
											</small>
										</td>
									</tr>
								<?php endif ?>

							<?php elseif ( $option['type'] == 'checkbox' ): ?>

								<tr valign="top">
									<th scope="row">
										<label for="<?php echo esc_attr( $option['id'] ) ?>"><?php echo esc_html( $option['label'] ) ?></label>
									</th>
									<td>
										<input type="checkbox" name="<?php echo esc_attr( $option['id'] ) ?>" id="<?php echo esc_attr( $option['id'] ) ?>" value="1" <?php if ( $option['value'] ) echo 'checked=checked' ?>>
										<?php if ( $option['hint'] ): ?>
											<small><?php echo esc_html( $option['hint'] ) ?></small>
										<?php endif ?>
									</td>
								</tr>

							<?php elseif ( $option['type'] == 'textarea' ): ?>

								<tr valign="top">
									<th scope="row">
										<label for="<?php echo esc_attr( $option['id'] ) ?>"><?php echo esc_html( $option['label'] ) ?></label>
									</th>
									<td>
										<textarea name='<?php echo esc_attr( $option['id'] ) ?>' id='<?php echo esc_attr( $option['id'] ) ?>' rows="5" cols="80"<?php if ( ! current_user_can( 'unfiltered_html' ) ) echo ' readonly="readonly"' ?>><?php echo esc_textarea( $option['value'] ) ?></textarea>
										<?php if ( $option['hint'] ): ?>
											<small><?php echo esc_html( $option['hint'] ) ?></small>
										<?php endif ?>
									</td>
								</tr>

							<?php endif ?>

						<?php endforeach ?>

					</tbody>
				</table>

				<?php submit_button() ?>

			</form>
		</p>

	<?php endif ?>

</div>
