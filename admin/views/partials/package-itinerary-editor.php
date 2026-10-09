<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$package_itinerary_days_for_admin = array();

foreach ( (array) $editing_itinerary_days as $itinerary_day ) {
	$day = $itinerary_day;

	foreach ( array( 'images', 'icons' ) as $media_group ) {
		foreach ( (array) ( $day[ $media_group ] ?? array() ) as $media_index => $media_item ) {
			$attachment_id = absint( $media_item['attachment_id'] ?? 0 );
			$preview_url   = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';

			$day[ $media_group ][ $media_index ]['preview_url'] = $preview_url ? $preview_url : '';
		}
	}

	$package_itinerary_days_for_admin[] = $day;
}
?>

<section class="hr-package-itinerary-admin" aria-labelledby="hr-package-itinerary-heading">
	<div class="hr-package-itinerary-admin__heading">
		<div>
			<h3 id="hr-package-itinerary-heading"><?php esc_html_e( 'Itinerary Day by Day', 'hotel-rates' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Add manual itinerary content for this Package Deal. Each day requires 2–8 images and 2–8 icon items. This content is independent from package rates and booking calculations.', 'hotel-rates' ); ?>
			</p>
		</div>
		<button type="button" class="button button-secondary hr-package-itinerary-add-day">
			<?php esc_html_e( 'Add Itinerary Day', 'hotel-rates' ); ?>
		</button>
	</div>

	<div class="hr-package-itinerary-days" aria-live="polite"></div>
	<p class="hr-package-itinerary-empty<?php echo empty( $package_itinerary_days_for_admin ) ? '' : ' is-hidden'; ?>">
		<?php esc_html_e( 'No manual itinerary days have been added. Existing Package Deals can be saved without this optional section.', 'hotel-rates' ); ?>
	</p>
</section>

<script type="application/json" id="hr-package-itinerary-existing-data"><?php echo wp_json_encode( $package_itinerary_days_for_admin ); ?></script>
