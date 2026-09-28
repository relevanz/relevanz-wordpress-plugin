<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://releva.nz
 * @since      1.0.0
 *
 * @package    Relevatracking
 * @subpackage Relevatracking/admin/partials
 */
$scope_label = $this->get_scope_label();
?>
<div id="RelevaWrap" class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ) ?></h2>
	<?php if ( $scope_label ): ?>
		<p><em><?php echo esc_html( $scope_label ) ?></em></p>
	<?php endif ?>
	<iframe src="<?php echo esc_url( $this->iframe_url ) ?>" style="border: 0px; width: 100%; min-height: 800px; height: calc(100vh - 160px);" id="gopolegelcontent"></iframe>
</div>
