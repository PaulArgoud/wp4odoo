<?php
/**
 * Logs tab template.
 *
 * @var int   $page     Current page number.
 * @var int   $per_page Results per page.
 * @var array $log_data {items: array, total: int, pages: int, page: int}.
 *
 * @package WP4Odoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Logs', 'wp4odoo' ); ?></h2>

<!-- Filter bar -->
<div class="tablenav top wp4odoo-filters">
	<div class="alignleft actions">
		<select id="wp4odoo-log-level">
			<option value=""><?php esc_html_e( 'All levels', 'wp4odoo' ); ?></option>
			<option value="debug"><?php esc_html_e( 'Debug', 'wp4odoo' ); ?></option>
			<option value="info"><?php esc_html_e( 'Info', 'wp4odoo' ); ?></option>
			<option value="warning"><?php esc_html_e( 'Warning', 'wp4odoo' ); ?></option>
			<option value="error"><?php esc_html_e( 'Error', 'wp4odoo' ); ?></option>
			<option value="critical"><?php esc_html_e( 'Critical', 'wp4odoo' ); ?></option>
		</select>

		<select id="wp4odoo-log-module">
			<option value=""><?php esc_html_e( 'All modules', 'wp4odoo' ); ?></option>
			<optgroup label="<?php esc_attr_e( 'System', 'wp4odoo' ); ?>">
				<option value="api"><?php esc_html_e( 'API', 'wp4odoo' ); ?></option>
				<option value="i18n"><?php esc_html_e( 'i18n', 'wp4odoo' ); ?></option>
				<option value="queue"><?php esc_html_e( 'Queue', 'wp4odoo' ); ?></option>
				<option value="reconcile"><?php esc_html_e( 'Reconcile', 'wp4odoo' ); ?></option>
				<option value="system"><?php esc_html_e( 'System', 'wp4odoo' ); ?></option>
			</optgroup>
			<optgroup label="<?php esc_attr_e( 'Modules', 'wp4odoo' ); ?>">
				<option value="acf"><?php esc_html_e( 'ACF', 'wp4odoo' ); ?></option>
				<option value="affiliatewp"><?php esc_html_e( 'AffiliateWP', 'wp4odoo' ); ?></option>
				<option value="amelia"><?php esc_html_e( 'Amelia', 'wp4odoo' ); ?></option>
				<option value="awesome_support"><?php esc_html_e( 'Awesome Support', 'wp4odoo' ); ?></option>
				<option value="bookly"><?php esc_html_e( 'Bookly', 'wp4odoo' ); ?></option>
				<option value="charitable"><?php esc_html_e( 'Charitable', 'wp4odoo' ); ?></option>
				<option value="crm"><?php esc_html_e( 'CRM', 'wp4odoo' ); ?></option>
				<option value="ecwid"><?php esc_html_e( 'Ecwid', 'wp4odoo' ); ?></option>
				<option value="edd"><?php esc_html_e( 'EDD', 'wp4odoo' ); ?></option>
				<option value="events_calendar"><?php esc_html_e( 'Events Calendar', 'wp4odoo' ); ?></option>
				<option value="forms"><?php esc_html_e( 'Forms', 'wp4odoo' ); ?></option>
				<option value="givewp"><?php esc_html_e( 'GiveWP', 'wp4odoo' ); ?></option>
				<option value="learndash"><?php esc_html_e( 'LearnDash', 'wp4odoo' ); ?></option>
				<option value="lifterlms"><?php esc_html_e( 'LifterLMS', 'wp4odoo' ); ?></option>
				<option value="memberpress"><?php esc_html_e( 'MemberPress', 'wp4odoo' ); ?></option>
				<option value="memberships"><?php esc_html_e( 'Memberships', 'wp4odoo' ); ?></option>
				<option value="pmpro"><?php esc_html_e( 'PMPro', 'wp4odoo' ); ?></option>
				<option value="rcp"><?php esc_html_e( 'RCP', 'wp4odoo' ); ?></option>
				<option value="sales"><?php esc_html_e( 'Sales', 'wp4odoo' ); ?></option>
				<option value="shopwp"><?php esc_html_e( 'ShopWP', 'wp4odoo' ); ?></option>
				<option value="simplepay"><?php esc_html_e( 'SimplePay', 'wp4odoo' ); ?></option>
				<option value="sprout_invoices"><?php esc_html_e( 'Sprout Invoices', 'wp4odoo' ); ?></option>
				<option value="supportcandy"><?php esc_html_e( 'SupportCandy', 'wp4odoo' ); ?></option>
				<option value="wc_bookings"><?php esc_html_e( 'WC Bookings', 'wp4odoo' ); ?></option>
				<option value="wc_bundle_bom"><?php esc_html_e( 'WC Bundle BOM', 'wp4odoo' ); ?></option>
				<option value="wc_points_rewards"><?php esc_html_e( 'WC Points & Rewards', 'wp4odoo' ); ?></option>
				<option value="wc_subscriptions"><?php esc_html_e( 'WC Subscriptions', 'wp4odoo' ); ?></option>
				<option value="woocommerce"><?php esc_html_e( 'WooCommerce', 'wp4odoo' ); ?></option>
				<option value="wp_crowdfunding"><?php esc_html_e( 'WP Crowdfunding', 'wp4odoo' ); ?></option>
				<option value="wp_invoice"><?php esc_html_e( 'WP-Invoice', 'wp4odoo' ); ?></option>
				<option value="wp_job_manager"><?php esc_html_e( 'WP Job Manager', 'wp4odoo' ); ?></option>
				<option value="wpai"><?php esc_html_e( 'WP All Import', 'wp4odoo' ); ?></option>
				<option value="wprm"><?php esc_html_e( 'WPRM', 'wp4odoo' ); ?></option>
			</optgroup>
		</select>

		<input type="date" id="wp4odoo-log-date-from" placeholder="<?php esc_attr_e( 'From', 'wp4odoo' ); ?>" />
		<input type="date" id="wp4odoo-log-date-to" placeholder="<?php esc_attr_e( 'To', 'wp4odoo' ); ?>" />

		<button type="button" id="wp4odoo-filter-logs" class="button">
			<?php esc_html_e( 'Filter', 'wp4odoo' ); ?>
		</button>
	</div>
	<div class="alignright">
		<button type="button" id="wp4odoo-purge-logs" class="button button-secondary">
			<?php esc_html_e( 'Purge logs', 'wp4odoo' ); ?>
		</button>
	</div>
	<br class="clear" />
</div>

<!-- Logs table -->
<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Level', 'wp4odoo' ); ?></th>
			<th><?php esc_html_e( 'Module', 'wp4odoo' ); ?></th>
			<th><?php esc_html_e( 'Message', 'wp4odoo' ); ?></th>
			<th><?php esc_html_e( 'Context', 'wp4odoo' ); ?></th>
			<th><?php esc_html_e( 'Date', 'wp4odoo' ); ?></th>
		</tr>
	</thead>
	<tbody id="wp4odoo-logs-tbody">
		<?php if ( empty( $log_data['items'] ) ) : ?>
			<tr>
				<td colspan="5"><?php esc_html_e( 'No log entries.', 'wp4odoo' ); ?></td>
			</tr>
		<?php else : ?>
			<?php foreach ( $log_data['items'] as $log ) : ?>
				<?php
				$context       = $log->context ?? '';
				$context_short = ( mb_strlen( $context ) > 60 ) ? mb_substr( $context, 0, 60 ) . '...' : $context;
				?>
				<tr>
					<td>
						<span class="wp4odoo-badge wp4odoo-badge-<?php echo esc_attr( $log->level ); ?>">
							<?php echo esc_html( $log->level ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $log->module ?: '—' ); ?></td>
					<td><?php echo esc_html( $log->message ); ?></td>
					<td>
						<?php if ( ! empty( $context ) ) : ?>
							<span class="wp4odoo-log-context" title="<?php echo esc_attr( $context ); ?>">
								<?php echo esc_html( $context_short ); ?>
							</span>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $log->created_at ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<!-- Pagination container (AJAX-driven) -->
<div class="tablenav bottom">
	<div class="tablenav-pages" id="wp4odoo-logs-pagination">
		<?php if ( $log_data['pages'] > 1 ) : ?>
			<?php for ( $i = 1; $i <= $log_data['pages']; $i++ ) : ?>
				<?php if ( $i === $log_data['page'] ) : ?>
					<span class="tablenav-pages-navspan button disabled"><?php echo esc_html( (string) $i ); ?></span>
				<?php else : ?>
					<a href="#" class="button wp4odoo-log-page" data-page="<?php echo esc_attr( (string) $i ); ?>">
						<?php echo esc_html( (string) $i ); ?>
					</a>
				<?php endif; ?>
			<?php endfor; ?>
		<?php endif; ?>
	</div>
</div>
