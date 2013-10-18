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
    <form id="movies-filter" method="post">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
  		<?php wp_nonce_field( 'TH_Multisite_Publishing', '_th_nonce_field' ); ?>
    	<?php $list_table->display(); ?>
    </form>
</div>