<?php
/*
array (
  'page' => 'relevatracking_menu',
  'settings-updated' => 'true',
)
*/
if ( $once ) return;
if($this->butler_get( 'page' )=='relevatracking_menu' && $this->butler_get( 'settings-updated' )=='true') {
	echo '<div id="message" class="success updated"><p>' . __( 'Settings successfully saved!', $this->plugin_name ) . '</p></div>' . "\n";
	return;
}
if ( ! isset( $this->admin_notices ) or ! $this->admin_notices ): ?>
	<?php
	return;
	?>
<?php endif ?>

<?php foreach ( $this->admin_notices as $admin_notice ): ?>
    <div class="updated">
        <p><?php echo $admin_notice ?></p>
    </div>
<?php endforeach ?>
