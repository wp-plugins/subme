<?php
if ( ! defined('ABSPATH') ) {
	exit();
}

/* Check user's permissions */
$current_user = wp_get_current_user();
if ( ! is_super_admin() && $current_user->ID != $this->sm_options['delegate_queue_to'] ) {
        echo '<p>';
        echo __( 'You do not have the right permissions to view the queue. Please contact the Administrator if you feel that this should not be the case.', 'subme' );  
        echo '</p>';
        
        return;
}  

if ( isset( $_POST['form'] ) ) {
	if ( ! isset( $_POST['subme_queue_nonce'] ) || ! wp_verify_nonce( $_POST['subme_queue_nonce'], 'subme_queue' ) ) {
		exit();
	}

	$this->process_admin_post();
}

global $wpdb;
global $sm_error;
global $sm_updated;

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
	<h2>Queue</h2>
	<form method="post">
		<?php wp_nonce_field( 'subme_queue', 'subme_queue_nonce', true, true ); ?>
		<input type="hidden" name="form" value="queue">

		<div>
			<div class="tablenav top">

			<?php
				$total = absint( $this->total_queue() );
				$max_pages = ceil( $total / $this->sm_options['queue_items_per_page'] );
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
					switch( $_GET['orderby'] ) {
						case 'id':
						case 'from':
						case 'to':
						case 'subject':
							$orderby = $_GET['orderby'];
						break;
						default:
							$orderby = 'id';
					}
				} else {
					$orderby = 'id';
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

			<div class="alignleft actions bulkactions">
				<select name="action">
					<option value="-1" selected><?php echo __( 'Bulk Actions', 'subme' ); ?></option>
					<option value="delete"><?php echo __( 'Delete', 'subme' ); ?></option>
				</select>
				<input type="submit" name="apply" class="button action" value="<?php echo __( 'Apply', 'subme' ); ?>" />
			</div>

			<div class="tablenav-pages">
				<span><i><?php echo esc_html( $total ) . ' ' . ( $total > 1 || 0 == $total ? __( 'items', 'subme' ) : __( 'item', 'subme' ) ) . ' '; ?></i></span>
				<span class="pagination-links">
					<?php
						/* First page */
						$url = add_query_arg( 'page', 'sm_queue', '?' );
						$url = add_query_arg( 'paged', '1', $url );
						$url = add_query_arg( 'orderby', $orderby, $url );
						$url = add_query_arg( 'order', ( 'asc' === $order ? 'asc' : 'desc' ), $url );
					?>
					<a class="first-page<?php echo ( $page < 2 ? ' disabled' : '' ); ?>" title="<?php echo __( 'Go to the first page', 'subme' ); ?>" href="<?php echo esc_url( $url ); ?>">&laquo;</a>

					<?php
						/* Previous page */
						$url = add_query_arg( 'page', 'sm_queue', '?' );
						$url = add_query_arg( 'paged', ($page < 2 ? '1' : $page - 1), $url );
						$url = add_query_arg( 'orderby', $orderby, $url );
						$url = add_query_arg( 'order', ( 'asc' === $order ? 'asc' : 'desc' ), $url );
					?>
					<a class="prev-page<?php echo ( $page < 2 ? ' disabled' : '' ); ?>" title="<?php echo __( 'Go to the previous page', 'subme' ); ?>" href="<?php echo esc_url( $url ); ?>">&lsaquo;</a>

					<?php /* Total pages */ ?>
					<span class="paging-input">
						<span class="total-pages"><?php echo $page . ' of ' . $max_pages; ?></span>
					</span>

					<?php
						/* Next page */
						$url = add_query_arg( 'page', 'sm_queue', '?' );
						$url = add_query_arg( 'paged', ( $page < $max_pages ? $page + 1 : $page ), $url );
						$url = add_query_arg( 'orderby', $orderby, $url );
						$url = add_query_arg( 'order', ( 'asc' === $order ? 'asc' : 'desc' ), $url );
					?>
					<a class="next-page<?php echo ( $page == $max_pages ? ' disabled' : '' ); ?>" title="<?php echo __( 'Go to the next page', 'subme' ); ?>" href="<?php echo esc_url( $url ); ?>">&rsaquo;</a>

					<?php
						/* Last page */
						$url = add_query_arg( 'page', 'sm_queue', '?' );
						$url = add_query_arg( 'paged', $max_pages, $url );
						$url = add_query_arg( 'orderby', $orderby, $url );
						$url = add_query_arg( 'order', ( 'asc' === $order ? 'asc' : 'desc' ), $url );
					?>
					<a class="last-page<?php echo ( $page == $max_pages ? ' disabled' : '' ); ?>" title="<?php echo __( 'Go to the last page', 'subme' ); ?>" href="<?php echo esc_url( $url ); ?>">&raquo;</a>
				</span>
			</div>

			<?php /* tablenav top */ ?>
			</div>

			<table class="wp-list-table widefat fixed pages">
				<?php /* Column heads */ ?>
				<thead>
					<tr>
						<?php /* Check box */ ?>
						<th id="cb" class="manage-column column-cb check-column" style="">
							<input name="cb" type="checkbox" onclick="subme_cb_toggle()" />
						</th>

						<?php 
							/* ID */
							$url = add_query_arg( 'page', 'sm_queue', '?' );
							$url = add_query_arg( 'orderby', 'id', $url );
							$url = add_query_arg( 'order', ( 'id' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );
						?>
						<th class="manage-column column-id <?php echo ( 'id' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'id' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'ID', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>

						<?php 
							/* Email from */
							$url = add_query_arg( 'page', 'sm_queue', '?' );
							$url = add_query_arg( 'orderby', 'from', $url );
							$url = add_query_arg( 'order', ( 'from' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );

						?>
						<th class="manage-column column-email <?php echo ( 'from' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'from' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'From', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>

						<?php 
							/* Email to */
							$url = add_query_arg( 'page', 'sm_queue', '?' );
							$url = add_query_arg( 'orderby', 'to', $url );
							$url = add_query_arg( 'order', ( 'to' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );

						?>
						<th class="manage-column column-email <?php echo ( 'to' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'to' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'To', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>

						<?php 
							/* Subject */
							$url = add_query_arg( 'page', 'sm_queue', '?' );
							$url = add_query_arg( 'orderby', 'subject', $url );
							$url = add_query_arg( 'order', ( 'subject' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );

						?>
						<th class="manage-column column-text <?php echo ( 'subject' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'subject' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'Subject', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>
					</tr>
				</thead>
				
				<tfoot>
					<tr>
						<?php /* Check box */ ?>
						<th id="cb" class="manage-column column-cb check-column" style="">
							<input name="cb" type="checkbox" onclick="subme_cb_toggle()" />
						</th>

						<?php 
							/* ID */
							$url = add_query_arg( 'page', 'sm_queue', '?' );
							$url = add_query_arg( 'orderby', 'id', $url );
							$url = add_query_arg( 'order', ( 'id' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );
						?>
						<th class="manage-column column-id <?php echo ( 'id' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'id' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'ID', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>

						<?php 
							/* Email from */
							$url = add_query_arg( 'page', 'sm_queue', '?' );
							$url = add_query_arg( 'orderby', 'from', $url );
							$url = add_query_arg( 'order', ( 'from' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );

						?>
						<th class="manage-column column-email <?php echo ( 'from' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'from' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'From', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>

						<?php 
							/* Email to */
							$url = add_query_arg( 'page', 'sm_queue', '?' );
							$url = add_query_arg( 'orderby', 'to', $url );
							$url = add_query_arg( 'order', ( 'to' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );

						?>
						<th class="manage-column column-email <?php echo ( 'to' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'to' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'To', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>

						<?php 
							/* Subject */
							$url = add_query_arg( 'page', 'sm_queue', '?' );
							$url = add_query_arg( 'orderby', 'subject', $url );
							$url = add_query_arg( 'order', ( 'subject' === $orderby ? ( 'asc' === $order ? 'desc' : 'asc' ) : 'asc' ), $url );

						?>
						<th class="manage-column column-text <?php echo ( 'subject' === $orderby ? 'sorted' : 'sortable' ); ?> <?php echo ( 'subject' === $orderby ? ( 'asc' === $order ? 'asc' : 'desc' ) : 'desc' ); ?>" style="">
							<a href="<?php echo esc_url( $url ); ?>">
								<span><?php echo __( 'Subject', 'subme' ); ?></span>
								<span class="sorting-indicator"></span>
							</a>
						</th>
					</tr>
				</tfoot>

				<?php
					if ( 'to' === $orderby ) {
						$order_table = 'recipient';
					} elseif ( 'from' === $orderby ) {
						$order_table = 'sender';
					} elseif ( 'subject' === $orderby ) {
						$order_table = 'subject';
					} else {
						$order_table = 'id';
					}
		
					$table = $wpdb->prefix . 'subme_queue';
					$query = $wpdb->prepare( "SELECT id, sender, recipient, subject FROM $table ORDER BY " . $order_table . ' ' . ( 'asc' === $order ? 'ASC' : 'DESC' ) . " LIMIT %d, %d",
						( ( $page - 1 ) * $this->sm_options['queue_items_per_page'] ),
						$this->sm_options['queue_items_per_page'] );
					$results = $wpdb->get_results( $query );
					
					if ( $wpdb->num_rows > 0 ) {
						$counter = 0;
						foreach ( $results as $result ) {
							echo '<tr' . ( $counter % 2 ? ' class="alternate">' : '>' );
								echo '<td><input name="cb[' . absint( $result->id ) . ']" type="checkbox" value="' . absint( $result->id ) . '"/></td>';						
								echo '<td>';
								echo absint( $result->id );
								echo '</td>';
							
								echo '<td>';
								echo esc_html( $result->sender );
								echo '</td>';

								echo '<td>';
								echo esc_html( $result->recipient );
								echo '</td>';

								echo '<td>';
								echo esc_html( $result->subject );
								echo '</td>';
							echo '<tr/>';

							$counter++;
						}
					} else {
						echo '<tr><td colspan="5"><i>' . __( 'No items found.', 'subme' ) . '</i></td></tr>';
					}	
				?>
			</table>
		</div>
	</form>
</div>
<?php
?>
