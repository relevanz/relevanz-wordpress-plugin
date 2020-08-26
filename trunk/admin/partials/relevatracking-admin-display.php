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

	<div class="stat-block"></div>

<!-- jQuery stuff -->
<script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.0/jquery-ui.min.js"></script>
<!--Sorting tables-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.28.13/js/jquery.tablesorter.min.js"></script>
<!-- Chart -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.4.0/Chart.min.js"></script>
<!--Custom plugin-->
<script src="js/conversions.js"></script>
<!--Init releva stats-->



<script>
    $('.stat-block').conversions({apikey:"CATRUN_FRZUIQWCV"});
</script>
