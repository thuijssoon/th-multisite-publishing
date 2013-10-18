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
	<form action="<?php echo admin_url( 'admin-post.php?action=th-msp-copy-terms' ); ?>" method="post">
  		<?php wp_nonce_field( 'TH_Multisite_Publishing' ); ?>
  		<h3><?php _e( 'Copy taxonomy terms', 'th_mtb' ); ?></h3>
		<p><?php _e( 'Select the sites in your network you would like to copy terms from and copy terms to.', 'th_mtb' ); ?></p>

		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php _e( 'Copy from', 'th_mtb' ); ?></th>
					<td>
						<select name="copy_from" id="copy_from">
							<option value="0">— Select —</option>
							<?php foreach ( $blog_ids as $copy_from_id ) { ?>
								<option class="level-0" value="<?php echo $copy_from_id; ?>"><?php echo get_blog_details( $copy_from_id )->blogname; ?></option>
							<?php } ?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Copy to</th>
					<td>
						<select name="copy_to" id="copy_to">
							<option value="0">— Select —</option>
							<?php foreach ( $blog_ids as $copy_to_id ) { ?>
								<option class="level-0" value="<?php echo $copy_to_id; ?>"><?php echo get_blog_details( $copy_to_id )->blogname; ?></option>
							<?php } ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( 'Copy terms' ); ?>
	</form>
</div>