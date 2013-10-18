<?php
/**
 * Represents the view for the network administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   TH_Multisite_Publishing
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/thuijssoon
 * @copyright 2013 Your Name or Company Name
 */

?>
<div class="wrap">

	<?php screen_icon( $screen = 'tools' ); ?>
	<h2 class="nav-tab-wrapper">
		<?php echo esc_html( get_admin_page_title() ); ?>&nbsp;&nbsp;
		<?php if (! $tab || 'copy' === $tab ): ?>
			<a class='nav-tab nav-tab-active' href='?page=th-multisite-publishing&tab=copy'>Copy Content</a>
			<a class='nav-tab' href='?page=th-multisite-publishing&tab=cpt'>Post Type Mapping</a>
			<a class='nav-tab' href='?page=th-multisite-publishing&tab=tax'>Taxonomy Mapping</a>
		<?php elseif ( 'cpt' === $tab ): ?>
			<a class='nav-tab' href='?page=th-multisite-publishing&tab=copy'>Copy Content</a>
			<a class='nav-tab nav-tab-active' href='?page=th-multisite-publishing&tab=cpt'>Post Type Mapping</a>
			<a class='nav-tab' href='?page=th-multisite-publishing&tab=tax'>Taxonomy Mapping</a>
		<?php else: ?>
			<a class='nav-tab' href='?page=th-multisite-publishing&tab=copy'>Copy Content</a>
			<a class='nav-tab' href='?page=th-multisite-publishing&tab=cpt'>Post Type Mapping</a>
			<a class='nav-tab nav-tab-active' href='?page=th-multisite-publishing&tab=tax'>Taxonomy Mapping</a>
		<?php endif ?>
	</h2>

	<?php if ( isset( $_GET['published-all'] ) && 'cpt' === $tab ): ?>
		<div class="updated below-h2" id="message"><p>Publishing all custom post types for site.</p></div>
	<?php elseif ( isset( $_GET['published-none'] ) && 'cpt' === $tab ): ?>
		<div class="updated below-h2" id="message"><p>Publishing no custom post types for site.</p></div>
	<?php elseif ( isset( $_GET['published-some'] ) && 'cpt' === $tab ): ?>
		<div class="updated below-h2" id="message"><p>Updated custom post types mappings for sites.</p></div>
	<?php elseif ( isset( $_GET['published-all'] ) && 'tax' === $tab ): ?>
		<div class="updated below-h2" id="message"><p>Publishing all taxonomies for site.</p></div>
	<?php elseif ( isset( $_GET['published-none'] ) && 'tax' === $tab ): ?>
		<div class="updated below-h2" id="message"><p>Publishing no taxonomies for site.</p></div>
	<?php elseif ( isset( $_GET['published-some'] ) && 'tax' === $tab ): ?>
		<div class="updated below-h2" id="message"><p>Updated taxonomy mappings for sites.</p></div>
	<?php elseif ( isset( $_GET['copied'] ) && 'copy' === $tab ): ?>
		<div class="updated below-h2" id="message"><p>Terms sucecessfully copied.</p></div>
	<?php elseif ( isset( $_GET['copy-from-error'] ) && 'copy' === $tab ): ?>
		<div class="error below-h2" id="message"><p>There was a problem with the Copy from field.</p></div>
	<?php elseif ( isset( $_GET['copy-to-error'] ) && 'copy' === $tab ): ?>
		<div class="error below-h2" id="message"><p>There was a problem with the Copy to field.</p></div>

	<?php endif;