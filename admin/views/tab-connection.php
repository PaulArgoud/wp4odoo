<?php
/**
 * Connection tab template.
 *
 * @var array  $credentials Decrypted Odoo credentials.
 * @var string $token       Webhook token.
 * @var string $webhook_url Webhook endpoint URL.
 *
 * @package WP4Odoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="options.php">
	<?php settings_fields( 'wp4odoo_connection_group' ); ?>

	<h2><?php esc_html_e( 'Odoo Connection', 'wp4odoo' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="wp4odoo_url"><?php esc_html_e( 'Odoo URL', 'wp4odoo' ); ?></label>
			</th>
			<td>
				<input type="url" id="wp4odoo_url" name="wp4odoo_connection[url]"
					value="<?php echo esc_attr( $credentials['url'] ); ?>"
					class="regular-text" placeholder="https://mycompany.odoo.com" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp4odoo_database"><?php esc_html_e( 'Database', 'wp4odoo' ); ?></label>
			</th>
			<td>
				<input type="text" id="wp4odoo_database" name="wp4odoo_connection[database]"
					value="<?php echo esc_attr( $credentials['database'] ); ?>"
					class="regular-text" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp4odoo_username"><?php esc_html_e( 'Username', 'wp4odoo' ); ?></label>
			</th>
			<td>
				<input type="text" id="wp4odoo_username" name="wp4odoo_connection[username]"
					value="<?php echo esc_attr( $credentials['username'] ); ?>"
					class="regular-text" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp4odoo_api_key"><?php esc_html_e( 'API Key', 'wp4odoo' ); ?></label>
			</th>
			<td>
				<input type="password" id="wp4odoo_api_key" name="wp4odoo_connection[api_key]"
					value="" class="regular-text"
					placeholder="<?php esc_attr_e( 'Leave empty to keep the current key', 'wp4odoo' ); ?>"
					autocomplete="new-password" />
				<p class="description">
					<?php
					if ( ! empty( $credentials['api_key'] ) ) {
						esc_html_e( 'An API key is already configured. Leave empty to keep it.', 'wp4odoo' );
					} else {
						esc_html_e( 'Enter your Odoo API key.', 'wp4odoo' );
					}
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp4odoo_protocol"><?php esc_html_e( 'Protocol', 'wp4odoo' ); ?></label>
			</th>
			<td>
				<select id="wp4odoo_protocol" name="wp4odoo_connection[protocol]">
					<option value="jsonrpc" <?php selected( $credentials['protocol'], 'jsonrpc' ); ?>>
						JSON-RPC (Odoo 17+)
					</option>
					<option value="xmlrpc" <?php selected( $credentials['protocol'], 'xmlrpc' ); ?>>
						XML-RPC (Odoo 14+)
					</option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="wp4odoo_timeout"><?php esc_html_e( 'Timeout (seconds)', 'wp4odoo' ); ?></label>
			</th>
			<td>
				<input type="number" id="wp4odoo_timeout" name="wp4odoo_connection[timeout]"
					value="<?php echo esc_attr( (string) $credentials['timeout'] ); ?>"
					min="5" max="120" class="small-text" />
			</td>
		</tr>
	</table>

	<p>
		<button type="button" id="wp4odoo-test-connection" class="button button-secondary">
			<?php esc_html_e( 'Test connection', 'wp4odoo' ); ?>
		</button>
		<span id="wp4odoo-test-result"></span>
	</p>

	<?php submit_button( __( 'Save', 'wp4odoo' ) ); ?>
</form>

<hr />

<h2><?php esc_html_e( 'Webhook', 'wp4odoo' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Token', 'wp4odoo' ); ?></th>
		<td>
			<code id="wp4odoo-webhook-token"><?php echo esc_html( $token ); ?></code>
			<button type="button" id="wp4odoo-copy-token" class="button button-small">
				<?php esc_html_e( 'Copy', 'wp4odoo' ); ?>
			</button>
			<p class="description">
				<?php
				printf(
					/* translators: %s: header name */
					esc_html__( 'Configure this token in Odoo as the %s header.', 'wp4odoo' ),
					'<code>X-Odoo-Token</code>'
				);
				?>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Webhook URL', 'wp4odoo' ); ?></th>
		<td>
			<code><?php echo esc_url( $webhook_url ); ?></code>
		</td>
	</tr>
</table>
