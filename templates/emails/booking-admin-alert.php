<?php
/**
 * Email template: Admin Alert - New Booking
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
$nah_end_time       = date_i18n( $settings['time_format'], strtotime( $booking['booking_date'] . ' ' . $booking['end_time'] ) );
$nah_admin_url      = admin_url( 'admin.php?page=nah-bookings' );
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
						<td style="background-color: #1e293b; padding: 24px 40px;">
							<h1 style="color: #ffffff; margin: 0; font-size: 20px;"><?php esc_html_e( 'New Booking Received', 'nivaj-appointment-hub' ); ?></h1>
						</td>
					</tr>
					<tr>
						<td style="padding: 30px 40px;">
							<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px;">
								<tr>
									<td style="padding: 20px;">
										<table width="100%" cellpadding="0" cellspacing="0">
											<tr>
												<td style="padding: 6px 0; color: #666; font-size: 14px; width: 130px;"><?php esc_html_e( 'Service', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 6px 0; color: #333; font-size: 14px; font-weight: 600;"><?php echo esc_html( $booking_type['title'] ?? '' ); ?></td>
											</tr>
											<tr>
												<td style="padding: 6px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Date & Time', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 6px 0; color: #333; font-size: 14px; font-weight: 600;"><?php echo esc_html( $nah_date_formatted . ' ' . $nah_start_time . ' - ' . $nah_end_time ); ?></td>
											</tr>
											<tr>
												<td style="padding: 6px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Customer', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 6px 0; color: #333; font-size: 14px; font-weight: 600;"><?php echo esc_html( $booking['customer_name'] ); ?></td>
											</tr>
											<tr>
												<td style="padding: 6px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Email', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 6px 0; color: #333; font-size: 14px;"><?php echo esc_html( $booking['customer_email'] ); ?></td>
											</tr>
											<?php if ( ! empty( $booking['customer_phone'] ) ) : ?>
											<tr>
												<td style="padding: 6px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Phone', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 6px 0; color: #333; font-size: 14px;"><?php echo esc_html( $booking['customer_phone'] ); ?></td>
											</tr>
											<?php endif; ?>
											<?php if ( ! empty( $booking['customer_notes'] ) ) : ?>
											<tr>
												<td style="padding: 6px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Notes', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 6px 0; color: #333; font-size: 14px;"><?php echo esc_html( $booking['customer_notes'] ); ?></td>
											</tr>
											<?php endif; ?>
											<tr>
												<td style="padding: 6px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Status', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 6px 0; color: #333; font-size: 14px; font-weight: 600;"><?php echo esc_html( ucfirst( $booking['status'] ) ); ?></td>
											</tr>
										</table>
									</td>
								</tr>
							</table>

							<p style="text-align: center; margin: 24px 0 0;">
								<a href="<?php echo esc_url( $nah_admin_url ); ?>" style="display: inline-block; background-color: #1e293b; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 4px; font-size: 14px;">
									<?php esc_html_e( 'View in Dashboard', 'nivaj-appointment-hub' ); ?>
								</a>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
