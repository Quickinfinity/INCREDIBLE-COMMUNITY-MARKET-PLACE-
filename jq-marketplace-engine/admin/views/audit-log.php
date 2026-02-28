<?php
/**
 * Admin audit log page — immutable record of all platform actions.
 *
 * @package JQ_Marketplace_Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use JQME\AuditLogger;

$action_filter = sanitize_text_field( $_GET['action_type'] ?? '' );
$object_filter = sanitize_text_field( $_GET['object_type'] ?? '' );
$search        = sanitize_text_field( $_GET['s'] ?? '' );
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 50;

$entries = AuditLogger::query( [
	'action'      => $action_filter,
	'object_type' => $object_filter,
	'search'      => $search,
	'limit'       => $per_page,
	'offset'      => ( $paged - 1 ) * $per_page,
] );

$total = AuditLogger::count( [
	'action'      => $action_filter,
	'object_type' => $object_filter,
	'search'      => $search,
] );

// Get distinct values for filter dropdowns.
global $wpdb;
$table = \JQME\Core::table( 'audit_log' );
$actions      = $wpdb->get_col( "SELECT DISTINCT action FROM {$table} ORDER BY action" );
$object_types = $wpdb->get_col( "SELECT DISTINCT object_type FROM {$table} ORDER BY object_type" );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Audit Log', 'jq-marketplace-engine' ); ?></h1>

	<!-- Filters -->
	<form method="get" style="margin-bottom:16px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
		<input type="hidden" name="page" value="jqme-audit-log">

		<select name="action_type">
			<option value=""><?php esc_html_e( 'All Actions', 'jq-marketplace-engine' ); ?></option>
			<?php foreach ( $actions as $a ) : ?>
				<option value="<?php echo esc_attr( $a ); ?>" <?php selected( $action_filter, $a ); ?>>
					<?php echo esc_html( ucwords( str_replace( '_', ' ', $a ) ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="object_type">
			<option value=""><?php esc_html_e( 'All Objects', 'jq-marketplace-engine' ); ?></option>
			<?php foreach ( $object_types as $ot ) : ?>
				<option value="<?php echo esc_attr( $ot ); ?>" <?php selected( $object_filter, $ot ); ?>>
					<?php echo esc_html( ucwords( $ot ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search context...', 'jq-marketplace-engine' ); ?>">

		<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'jq-marketplace-engine' ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=jqme-audit-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'jq-marketplace-engine' ); ?></a>

		<span style="margin-left:auto; color:#666;">
			<?php printf( esc_html__( '%s entries', 'jq-marketplace-engine' ), number_format( $total ) ); ?>
		</span>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th style="width:140px;"><?php esc_html_e( 'Time', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'User', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Action', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Object', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Old', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'New', 'jq-marketplace-engine' ); ?></th>
				<th><?php esc_html_e( 'Context', 'jq-marketplace-engine' ); ?></th>
				<th style="width:100px;"><?php esc_html_e( 'IP', 'jq-marketplace-engine' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $entries ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No log entries found.', 'jq-marketplace-engine' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td>
							<small><?php echo esc_html( date_i18n( 'M j g:ia', strtotime( $entry->created_at ) ) ); ?></small>
						</td>
						<td>
							<?php
							if ( $entry->user_id ) {
								$user = get_userdata( $entry->user_id );
								echo esc_html( $user ? $user->display_name : '#' . $entry->user_id );
							} else {
								esc_html_e( 'System', 'jq-marketplace-engine' );
							}
							?>
						</td>
						<td>
							<code style="font-size:11px;"><?php echo esc_html( $entry->action ); ?></code>
						</td>
						<td>
							<?php if ( $entry->object_type && $entry->object_id ) : ?>
								<small><?php echo esc_html( $entry->object_type ); ?> #<?php echo esc_html( $entry->object_id ); ?></small>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><small><?php echo $entry->old_value ? esc_html( $entry->old_value ) : '—'; ?></small></td>
						<td><small><?php echo $entry->new_value ? esc_html( $entry->new_value ) : '—'; ?></small></td>
						<td><small><?php echo $entry->context ? esc_html( $entry->context ) : '—'; ?></small></td>
						<td><small style="color:#999;"><?php echo esc_html( $entry->ip_address ?: '—' ); ?></small></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php
	$total_pages = ceil( $total / $per_page );
	if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php for ( $i = 1; $i <= min( $total_pages, 20 ); $i++ ) : ?>
					<?php if ( $i === $paged ) : ?>
						<strong><?php echo $i; ?></strong>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo $i; ?></a>
					<?php endif; ?>
				<?php endfor; ?>
				<?php if ( $total_pages > 20 ) : ?>
					<span>...</span>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
