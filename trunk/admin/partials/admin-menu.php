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
?>
<div class="wrap">

	<h2><?php echo __( 'releva.nz', $this->plugin_name) ;//esc_html( get_admin_page_title() ) ?></h2>

	<p>
		<h3><?php
		// Einstellungen
		_e( 'Settings', $this->plugin_name) ?></h3>
	</p>

	<p style="display:none"><strong><?php _e( 'Link to export products', $this->plugin_name ); ?></strong>: <span style="color:#005ebb"><?php echo home_url() ; ?><strong>/?releva_action=jsonexport</strong></span></p><hr />

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
										<label for="<?php echo $option['id'] ?>"><?php echo $option['label'] ?></label>
									</th>
									<td>
										<input type="text" name="<?php echo $option['id'] ?>" id="<?php echo $option['id'] ?>" value="<?php echo get_option( $option['id'] ) ?>" size="40">
										<?php if ( $option['hint'] ): ?>
											<small><?php echo $option['hint'] ?></small>
										</td>
									<?php endif ?>
								</tr>

							<?php elseif ( $option['type'] == 'checkbox' ): ?>

								<tr valign="top">
									<th scope="row">
										<label for="<?php echo $option['id'] ?>"><?php echo $option['label'] ?></label>
									</th>
									<td>
										<input type="checkbox" name="<?php echo $option['id'] ?>" id="<?php echo $option['id'] ?>" value="1" <?php if ( $option['value'] ) echo 'checked=checked' ?>>
									</td>
								</tr>

							<?php elseif ( $option['type'] == 'textarea' ): ?>

								<tr valign="top">
									<th scope="row">
										<label for="<?php echo $option['id'] ?>"><?php echo $option['label'] ?></label>
									</th>
									<td>
										<textarea name='<?php echo $option['id'] ?>' id='<?php echo $option['id'] ?>' rows="5" cols="80"><?php echo $option['value'] ?></textarea>
										<?php if ( $option['hint'] ): ?>
											<small><?php echo $option['hint'] ?></small>
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
