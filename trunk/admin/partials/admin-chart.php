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
?>
<div id="RelevaWrap" class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ) ?></h2>
	<p>
		<h3><?php _e( 'Releva Chart', $this->plugin_name) ?></h3>
	</p>
<div id="RelevaChart" class="stat-block"></div>
<!--  scrolling="no" frameborder="0" -->
<iframe  style="border: 0px; width: 100%;" id="gopolegelcontent"></iframe>
</div>
<!--Init releva stats-->
<script type="text/javascript"><!--
    jQuery( document ).ready(function($) {
		$('#RelevaChart').conversions({apikey:"<?php echo $this->api_key ; ?>"});
     });
//--></script>

