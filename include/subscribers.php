<?php
if ( ! defined('ABSPATH') ) {
	exit();
}

/* Check user's permissions */
$current_user = wp_get_current_user();
if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_subscribers_to'] ) {
	echo '<p>';
	echo __( 'You do not have the right permissions to view the subscribers. Please contact the Administrator if you feel that this should not be the case.', 'subme' );
	echo '</p>';

	return;
}

global $wpdb;
global $sm_error;
global $sm_updated;

if ( isset( $_POST['form'] ) ) {
	/* Check nonce */
	if ( ! isset( $_POST['subme_subscribers_nonce'] ) || ! wp_verify_nonce( $_POST['subme_subscribers_nonce'], 'subme_subscribers' ) ) {
		exit();
	}

	$this->process_admin_post();
}

/* Display updated if any */
if ( strlen( $sm_updated ) > 0 ) {
	$this->display_admin_updated( $sm_updated );
	$sm_updated = '';
}

/* Display error if any */
if ( strlen( $sm_error ) > 0 ) {
	$this->display_admin_error( $sm_error );
	$sm_error = '';
}

?>

<div class="wrap">
	<h2>Subscribers</h2>

	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'subme_subscribers', 'subme_subscribers_nonce', true, true ); ?>
		<input type="hidden" name="form" value="subscribers">

		<table style="border: none">
			<tr>
				<td>
					<h3><?php echo __( 'Add a single subscriber', 'subme' ); ?></h3>
				</td>
				<td>
					<h3><?php echo __( 'Import CSV file', 'subme' ); ?></h3>
				</td>
			</tr>
			<tr>
				<td>
					<?php echo __( 'The email address that you would like to add:', 'subme' ); ?>
				</td>
				<td>
					<label><input name="file" type="file" value="" /></label>
				</td>
			</tr>
			<tr>
				<td style="padding-right: 5em;">
					<label><input type="text" name="email" style="width: 20em;"></label>
				</td>
				<td>
					<i><?php echo __( 'First line is considered to contain the headers. Each record should be on a separate line.', 'subme' ); ?></i><br />
				</tr>
			</tr>
			<tr>
				<td colspan="2">
					<br class="clear" />
				</td>
			</tr>
			<tr>
				<td>
					<label><input type="submit" class="button-primary" name="subscribe" value="<?php echo __( 'Add', 'subme' ); ?>"></label>
				</td>
				<td>
					<label><input type="submit" class="button-primary" name="import" value="<?php echo __( 'Upload and import CSV file', 'subme' ); ?>" /></label>
				</td>
			</tr>
		</table>
		<hr />

		<?php /* Manage subscribers */ ?>
		<div>
			<h3><?php echo __( 'Subscribers', 'subme' ); ?></h3>
		
			<div class="tablenav top">

			<?php
				$total = absint( $this->total_subscribers() );
				$max_pages = ceil( $total / $this->sm_options['subscriber_items_per_page'] );
				if ( $max_pages < 1 ) {
					$max_pages = 1;
				}

				/* Get the current pagination page */
				if ( isset( $_GET['paged'] ) ) {
					$page = absint( $_GET['paged'] );
					if ( $page < 1 ) {
						$page = 1;
					}
					if ( $page > $max_pages ) {
						$page = $max_pages;
					}
				} else {
					$page = 1;
				}

				/* Get the sorting column */
				if ( isset( $_GET['orderby'] ) ) {
					if ( 'date' === $_GET['orderby'] ) {
						$orderby = 'date';
					} else {
						$orderby = 'email';
					}
				} else {
					$orderby = 'date';
				}

				/* Get the sorting order */
				if ( isset ( $_GET['order'] ) ) {
					if ( 'desc' === $_GET['order'] ) {
						$order = 'desc';
					} else {
						$order = 'asc';
					}
				} else {
					$order = 'desc';
				}
			?>

			<?php /* Bulk actions */ ?>
			<div class="alignleft actions bulkactions">
				<select name="action">
					<option value="-1" selected><?php echo __( 'Bulk Actions', 'subme' ); ?></option>
					<option value="delete"><?php echo __( 'Delete', 'subme' ); ?></option>
					<option value="activate"><?php echo __( 'Activate', 'subme' ); ?></option>
					<option value="deactivate"><?php echo __( 'Deactivate', 'subme' ); ?></option>
				</select>
				<input type="submit" name="apply" class="button action" value="<?php echo __( 'Apply', 'subme' ); ?>">
			</div>

			<?php /* Pagination */ ?>
			<div class="tablenav-pages">
				<span><i><?php echo $total . ' ' . ( $total > 1 || 0 == $total ? __( 'items', 'subme' ) : __( 'item', 'subme' ) ) . ' '; ?></i></span>
				<span class="pagination-links">
					<?php
						/* First page */
						$url = add_query_arg( 'page', 'sm_subscribers', '?' );
						$url = add_query_arg( 'paged', '1', $url );
						$url = add_query_arg( 'orderby', ( 'email' === $orderby ? 'email' : 'date' ), $url );
						$url = add_query_arg( 'order', ( 'asc' === $order ? 'asc' : 'desc' ), $url );
					?>
					<a class="first-page<?php echo ( $page < 2 ? ' disabled' : '' ); ?>" title="<?php echo __( 'Go to the first page', 'subme' ); ?>" href="<?php echo esc_url( $url ); ?>">&laquo;</a>

					<?php
						/* Previous page */
						$url = add_query_arg( 'page', 'sm_subscribers', '?' );
						$url = add_query_arg( 'paged', ($page < 2 ? '1' : $page - 1), $url );
						$url = add_query_arg( 'orderby', ( 'email' === $orderby ? 'email' : 'date' ), $url );
						$url = add_query_arg( 'order', ( 'asc' === $order ? 'asc' : 'desc' ), $url );
					?>
					<a class="prev-page<?php echo ( $page < 2 ? ' disabled' : '' ); ?>" title="<?php echo __( 'Go to the previous page', 'subme' ); ?>" href="<?php echo esc_url( $url ); ?>">&lsaquo;</a>

					<?php /* Total pages */ ?>
					<span class="paging-input">
						<span class="total-pages"><?php echo $page . ' of ' . $max_pages; ?></span>
					</span>

					<?php
						/* Next page */
						$url = add_query_arg( 'page', 'sm_subscribers', '?' );
						$url = add_query_arg( 'paged', ( $page < $max_pages ? $page + 1 : $page ), $url );
						$url = add_query_arg( 'orderby', ( 'email' === $orderby ? 'email' : 'date' ), $url );
						$url = add_query_arg( 'order', ( 'asc' === $order ? 'asc' : 'desc' ), $url );
					?>
					<a class="next-page<?php echo ( $page == $max_pages ? ' disabled' : '' ); ?>" title="<?php echo __( 'Go to the next page', 'subme' ); ?>" href="<?php echo esc_url( $url ); ?>">&rsaquo;</a>

					<?php
						/* Last page */
						$url = add_query_arg( 'page', 'sm_subscribers', '?' );
						$url = add_query_arg( 'paged', $max_pages, $url );
						$url = add_query_arg( 'orderby', ( 'email' === $orderby ? 'email' : 'date' ), $url );
						$url = add_query_arg( 'order', ( 'asc' === $order ? 'asc' : 'desc' ), $url );
					?>
					<a class="last-page<?php echo ( $page == $max_pages ? ' disabled' : '' ); ?>" title="<?php echo __( 'Go to the last page', 'subme' ); ?>" href="<?php echo esc_url( $url ); ?>">&raquo;</a>
				</span>
			</div>

			<?php /* tablenav top */ ?>
			</div>

			<table class="wp-list-table widefat fixed">
				<?php /* Column heads */ ?>
				<thead>
					<tr>
						<?php /* Check box */ ?>
						<th id="cb" class="manage-column column-cb check-column" style="">
							<input name="cb" type="checkbox" onclick="subme_cb_toggle()" />
						</th>

						<?php 
							/* Email column */
							$url = add_query_arg( 'page', 'sm_subscribers', '?' );
							$url = add_query_arg( 'orderby', 'email', $url );
							$url = add_query_arg( 'order', ( 'email' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );

						?>
						<th class="manage-column column-email <?php echo ( 'email' === $orderby  ? 'sorted' : 'sortable' ); ?> <?php echo ( 'email' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'Email', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>

						<?php
							/* Date column */
							$url = add_query_arg( 'page', 'sm_subscribers', '?' );
							$url = add_query_arg( 'orderby', 'date', $url );
							$url = add_query_arg( 'order', ( 'date' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );
						?>
						<th class="manage-column column-date <?php echo ( 'date' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'date' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'Date', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>
					</tr>
				</thead>
				
				<?php /* Column heads */ ?>
				<tfoot>
					<tr>
						<?php /* Check box */ ?>
						<th id="cb" class="manage-column column-cb check-column" style="">
							<input name="cb" type="checkbox" onclick="subme_cb_toggle()" />
						</th>

						<?php 
							/* Email column */
							$url = add_query_arg( 'page', 'sm_subscribers', '?' );
							$url = add_query_arg( 'orderby', 'email', $url );
							$url = add_query_arg( 'order', ( 'email' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );

						?>
						<th class="manage-column column-email <?php echo ( 'email' === $orderby  ? 'sorted' : 'sortable' ); ?> <?php echo ( 'email' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'Email', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>

						<?php
							/* Date column */
							$url = add_query_arg( 'page', 'sm_subscribers', '?' );
							$url = add_query_arg( 'orderby', 'date', $url );
							$url = add_query_arg( 'order', ( 'date' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );
						?>
						<th class="manage-column column-date <?php echo ( 'date' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'date' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'Date', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>
					</tr>
				</tfoot>

				<tbody>
				<?php
					$table = $wpdb->prefix . 'subme';
					$query = $wpdb->prepare( "SELECT id, active, email, timestamp FROM $table ORDER BY active, " . ( 'email' === $orderby ? 'email' : 'timestamp' ) . ' ' . ( 'asc' === $order ? 'ASC' : 'DESC' ) . ' ' . "LIMIT %d, %d",
						( ( $page - 1 ) * $this->sm_options['subscriber_items_per_page'] ),
						$this->sm_options['subscriber_items_per_page'] );
					$results = $wpdb->get_results( $query );
					
					if ( $wpdb->num_rows > 0 ) {
						$counter = 0;
						foreach ( $results as $result ) {
							echo '<tr' . ( $counter % 2 ? ' class="alternate">' : '>' );
								echo '<td><input name="cb[' . absint( $result->id ) . ']" type="checkbox" value="' . absint( $result->id ) . '"></td>';	
								echo '<td class="email column-email">';
								echo ( $result->active ? '<span style="color:#000000">' : '<span style="color:#FF0000">' );
								echo esc_html( $result->email );
								echo '</span>';
								echo '</td>';
	
								echo '<td class="date column-date">';
								echo date( "Y-m-d", $result->timestamp );
								echo '</td>';
							echo '<tr/>';
	
							$counter++;
						}
					} else {
						echo '<tr><td colspan="3"><i>' . __( 'No items found.', 'subme' ) . '</i></td></tr>';
					}	
				?>
				</tbody>
			</table>

			<?php /* Export to CSV button */ ?>
			<div>
			<br />
			<input type="submit" class="button-primary" name="export" value="<?php echo __( 'Export as CSV', 'subme' ); ?>">	
			</div>
		</div>
	</form>
</div>
<?php
?>
