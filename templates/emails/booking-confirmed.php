<?php
/**
 * Email template: Booking Confirmed
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
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
	<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f7; padding: 40px 0;">
		<tr>
			<td align="center">
				<table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
					<!-- Header -->
					<tr>
						<td style="background-color: <?php echo esc_attr( $booking_type['color'] ?? '#2563eb' ); ?>; padding: 30px 40px; text-align: center;">
							<h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">
								<?php esc_html_e( 'Booking Confirmed', 'nivaj-appointment-hub' ); ?>
							</h1>
						</td>
					</tr>

					<!-- Body -->
					<tr>
						<td style="padding: 40px;">
							<p style="color: #333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
								<?php
								printf(
									/* translators: %s: customer name */
									esc_html__( 'Hi %s,', 'nivaj-appointment-hub' ),
									esc_html( $booking['customer_name'] )
								);
								?>
							</p>
							<p style="color: #333; font-size: 16px; line-height: 1.5; margin: 0 0 30px;">
								<?php esc_html_e( 'Your booking has been confirmed. Here are the details:', 'nivaj-appointment-hub' ); ?>
							</p>

							<!-- Booking Details -->
							<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; margin-bottom: 30px;">
								<tr>
									<td style="padding: 20px;">
										<table width="100%" cellpadding="0" cellspacing="0">
											<tr>
												<td style="padding: 8px 0; color: #666; font-size: 14px; width: 120px;"><?php esc_html_e( 'Service', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;"><?php echo esc_html( $booking_type['title'] ?? '' ); ?></td>
											</tr>
											<tr>
												<td style="padding: 8px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Date', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;"><?php echo esc_html( $nivaj_ah_date_formatted ); ?></td>
											</tr>
											<tr>
												<td style="padding: 8px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Time', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;"><?php echo esc_html( $nivaj_ah_start_time . ' - ' . $nivaj_ah_end_time ); ?></td>
											</tr>
											<tr>
												<td style="padding: 8px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Duration', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">
													<?php
													printf(
														/* translators: %d: number of minutes */
														esc_html__( '%d minutes', 'nivaj-appointment-hub' ),
														(int) $booking_type['duration']
													);
													?>
												</td>
											</tr>
											<?php if ( ! empty( $booking_type['location_data'] ) ) : ?>
											<tr>
												<td style="padding: 8px 0; color: #666; font-size: 14px;"><?php esc_html_e( 'Location', 'nivaj-appointment-hub' ); ?></td>
												<td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;"><?php echo esc_html( $booking_type['location_data'] ); ?></td>
											</tr>
											<?php endif; ?>
										</table>
									</td>
								</tr>
							</table>

						</td>
					</tr>

					<!-- Footer -->
					<tr>
						<td style="padding: 20px 40px; background-color: #f8f9fa; text-align: center; border-top: 1px solid #eee;">
							<p style="color: #999; font-size: 12px; margin: 0;">
								<?php echo esc_html( $site_name ); ?>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
