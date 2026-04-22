<?php
/**
 * Email template: Booking Cancelled
 *
 * Available variables: $booking, $booking_type, $settings, $site_name, $site_url
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Locals are scoped inside an include() call from Notification::render_email() and are not actually global; they carry the plugin "nah_" prefix.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nah_date_formatted = date_i18n( $settings['date_format'], strtotime( $booking['booking_date'] ) );
$nah_start_time     = date_i18n( $settings['time_format'], strtotime( $booking['booking_date'] . ' ' . $booking['start_time'] ) );
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
	<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f7; padding: 40px 0;">
		<tr>
			<td align="center">
				<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">
					<tr>
						<td style="background-color: #dc2626; padding: 24px 40px; text-align: center;">
							<h1 style="color: #ffffff; margin: 0; font-size: 22px;"><?php esc_html_e( 'Booking Cancelled', 'nivaj-appointment-hub' ); ?></h1>
						</td>
					</tr>
					<tr>
						<td style="padding: 30px 40px;">
							<p style="color: #333; font-size: 16px; line-height: 1.5; margin: 0 0 16px;">
								<?php
								printf(
									/* translators: %s: customer's full name */
									esc_html__( 'Hi %s,', 'nivaj-appointment-hub' ),
									esc_html( $booking['customer_name'] )
								);
								?>
							</p>
							<p style="color: #333; font-size: 16px; line-height: 1.5; margin: 0 0 24px;">
								<?php esc_html_e( 'Your booking has been cancelled:', 'nivaj-appointment-hub' ); ?>
							</p>

							<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef2f2; border-radius: 6px; margin-bottom: 24px;">
								<tr>
									<td style="padding: 20px;">
										<p style="margin: 0 0 8px; font-size: 16px; font-weight: 600; color: #333; text-decoration: line-through;"><?php echo esc_html( $booking_type['title'] ?? '' ); ?></p>
										<p style="margin: 0 0 4px; font-size: 14px; color: #666; text-decoration: line-through;"><?php echo esc_html( $nah_date_formatted ); ?></p>
										<p style="margin: 0; font-size: 14px; color: #666; text-decoration: line-through;"><?php echo esc_html( $nah_start_time ); ?></p>
									</td>
								</tr>
							</table>

							<p style="color: #666; font-size: 14px; text-align: center; margin: 0;">
								<?php esc_html_e( 'If you\'d like to rebook, please visit our booking page.', 'nivaj-appointment-hub' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<td style="padding: 16px 40px; background-color: #f8f9fa; text-align: center; border-top: 1px solid #eee;">
							<p style="color: #999; font-size: 12px; margin: 0;"><?php echo esc_html( $site_name ); ?></p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
