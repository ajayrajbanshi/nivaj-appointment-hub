<?php
/**
 * Email template: Booking Reminder
 *
 * Available variables: $booking, $booking_type, $settings, $site_name, $site_url
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Locals are scoped inside an include() call from Notification::render_email() and are not actually global.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nivaj_ah_date_formatted = date_i18n( $settings['date_format'], strtotime( $booking['booking_date'] ) );
$nivaj_ah_start_time     = date_i18n( $settings['time_format'], strtotime( $booking['booking_date'] . ' ' . $booking['start_time'] ) );
$nivaj_ah_end_time       = date_i18n( $settings['time_format'], strtotime( $booking['booking_date'] . ' ' . $booking['end_time'] ) );
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
						<td style="background-color: <?php echo esc_attr( $booking_type['color'] ?? '#2563eb' ); ?>; padding: 24px 40px; text-align: center;">
							<h1 style="color: #ffffff; margin: 0; font-size: 22px;"><?php esc_html_e( 'Appointment Reminder', 'nivaj-appointment-hub' ); ?></h1>
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
								<?php esc_html_e( 'This is a friendly reminder about your upcoming appointment:', 'nivaj-appointment-hub' ); ?>
							</p>

							<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; margin-bottom: 24px;">
								<tr>
									<td style="padding: 20px;">
										<p style="margin: 0 0 8px; font-size: 18px; font-weight: 600; color: #333;"><?php echo esc_html( $booking_type['title'] ?? '' ); ?></p>
										<p style="margin: 0 0 4px; font-size: 14px; color: #666;"><?php echo esc_html( $nivaj_ah_date_formatted ); ?></p>
										<p style="margin: 0; font-size: 14px; color: #666;"><?php echo esc_html( $nivaj_ah_start_time . ' - ' . $nivaj_ah_end_time ); ?></p>
									</td>
								</tr>
							</table>

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
