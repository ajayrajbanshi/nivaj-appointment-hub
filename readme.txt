=== Nivaj Appointment Hub ===
Contributors: ajayrajbanshi
Tags: appointments, booking, scheduling, calendar, reservations
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A flexible appointment booking system for WordPress. Define services, set availability, and let visitors book time slots without leaving your site.

== Description ==

Nivaj Appointment Hub is a self-hosted appointment booking plugin for WordPress. Visitors pick a service, choose a date and time from your availability, and fill in their details — all without needing a third-party scheduling account. Bookings land in your WordPress admin and trigger configurable email notifications.

= Key features =

* Multiple bookable services with per-service duration, colour, location type (phone, in-person, video, custom), and image
* Weekly availability rules — set working hours for each day of the week per service
* Date-specific overrides — block dates (holidays) or open extra dates with custom hours
* Buffer times before/after bookings to prevent back-to-back conflicts
* Daily booking cap per service (optional)
* Minimum booking notice and maximum advance-booking window
* Custom fields per service (text, textarea, select, checkbox, number, phone, URL)
* Email notifications on confirmation, cancellation, and admin alerts, plus reminder emails
* Shortcode `[nivaj_ah_booking]` and a Gutenberg block to embed the widget
* Optional floating "Book Now" popup button site-wide, with fullscreen mode
* CSV export of all bookings
* REST API endpoints under `/wp-json/nivaj-ah/v1/` for public booking actions
* Works with guest visitors — no account required to book

= Booking flow =

1. Visitor selects a service from the list.
2. Visitor picks a date from the horizontal day strip (auto-advances past empty weeks).
3. Visitor picks a time slot, grouped by Morning / Afternoon / Evening.
4. Visitor fills in name, email, phone (optional), notes (optional), and any custom fields.
5. Booking is confirmed on screen and via email.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install from the WordPress.org plugin directory.
2. Activate through the **Plugins** menu in WordPress.
3. Go to **Appointment Hub → Booking Types** and create your first service.
4. Set availability under **Availability** for each service.
5. Configure general options under **Settings**.
6. Embed the widget on a page using the shortcode `[nivaj_ah_booking]` or the "Appointment Booking" Gutenberg block. Optionally enable the floating popup button in Settings.

== Frequently Asked Questions ==

= Does it require a third-party service? =

No. All booking data is stored in your WordPress database. The plugin has no required external dependencies.

= Does the plugin send data to any external service? =

By default, no. The plugin sends email notifications using your site's own `wp_mail()` — same as every other WordPress plugin. No telemetry, no analytics, no remote calls.

If you explicitly enable the **Webhook** integration in Settings and provide your own webhook URL, the plugin will POST booking events (booking created, cancelled, status changed) to that URL. The payload includes booking details such as customer name, email, phone, service, date, time, and status. This is entirely opt-in and configured by the site administrator. No data is sent anywhere unless you turn this on and provide a URL.

= Can visitors book without creating an account? =

Yes. The public booking endpoints (`/wp-json/nivaj-ah/v1/bookings`) accept unauthenticated submissions from guests. Rate limiting (5 attempts per IP per minute) and strict input validation are applied to prevent abuse.

= How is spam handled? =

The plugin enforces a rate limit of 5 booking attempts per IP per minute. For additional protection, use a site-wide spam plugin of your choice.

= Can I customise the email templates? =

Yes. Subject lines for confirmation, reminder, cancellation, and admin alert emails are editable under Settings. Body templates are built from the booking data and respect your site's locale and time format settings.

= Does it support multiple timezones? =

Yes. Availability is interpreted in the site timezone set under Settings. The booking widget displays slot times in the visitor's browser timezone.

= Can I pre-select a service or date via URL? =

Yes. Append `?nivaj_ah_type=<slug>` or `?nivaj_ah_date=YYYY-MM-DD` (and optionally `nivaj_ah_name`, `nivaj_ah_email`, `nivaj_ah_phone`) to a page containing the widget to pre-fill those values.

= How do I cancel a booking? =

Administrators can cancel or change booking status from the admin Bookings screen.

= Where is booking data stored? =

In four custom database tables prefixed with `nivaj_ah_` (bookings, booking types, availability rules, date overrides). Deleting the plugin via the Plugins screen removes these tables and all plugin options.

== Screenshots ==

1. Service selection screen with images and descriptions.
2. Date and time selection with horizontal day strip and grouped time slots.
3. Customer details form with booking summary.
4. Admin bookings list.
5. Service (booking type) editor in admin.

== Changelog ==

= 1.0.0 =
* Initial release.
* Multiple bookable services with per-service duration, colour, location type, and image.
* Weekly availability rules and date-specific overrides per service.
* Buffer times before/after bookings, daily booking caps, min notice, and advance-booking window.
* Custom fields per service (text, textarea, select, checkbox, number, phone, URL).
* Booking flow with combined date and time step, 7-day horizontal strip, and slots grouped by time of day.
* Email notifications for confirmation, cancellation, reminders, and admin alerts.
* Shortcode `[nivaj_ah_booking]` and a Gutenberg block to embed the widget.
* Optional floating "Book Now" popup button site-wide, with fullscreen mode.
* CSV export of bookings and dashboard analytics.
* Optional opt-in webhook integration for booking events.
* REST API endpoints under `/wp-json/nivaj-ah/v1/` for public booking actions.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Privacy ==

This plugin stores personal data submitted by visitors when they book an appointment: name, email address, phone number (optional), any custom field values you have configured, IP address (transiently, for rate limiting), and the booking details themselves.

Emails are sent through your site's mailer (`wp_mail()`) to the visitor's email address and the administrator email configured in Settings. No data is sent to external services by default.

If the **Webhook** integration is enabled in Settings, booking events are POSTed to the URL you configure. Site administrators are responsible for ensuring that the destination complies with their privacy policy and applicable data protection law.

Booking records are retained until manually deleted or until the plugin is uninstalled via the Plugins screen, which drops all plugin tables and options.
