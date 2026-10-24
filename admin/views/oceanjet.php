<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$render_row = function ( $index, $row = array() ) {
    $row = wp_parse_args( $row, array( 'id' => '', 'active' => true, 'from' => '', 'to' => '', 'code' => '', 'class' => 'Tourist Class', 'time' => '08:00', 'duration' => 60, 'start' => '', 'end' => '', 'blocked' => '', 'days' => array( '1','2','3','4','5','6','7' ), 'fares' => array() ) );
    $base = 'sailings[' . $index . ']';
    ?>
    <fieldset class="oj-sailing" style="border:1px solid #ccd0d4;padding:16px;margin:16px 0;background:white">
        <legend><strong>Sailing / accommodation</strong></legend>
        <input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $row['id'] ); ?>">
        <label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[active]" value="1" <?php checked( $row['active'] ); ?>> Enabled</label>
        <button type="button" class="button oj-remove" style="float:right">Remove sailing</button>
        <div style="display:flex;flex-wrap:wrap;gap:14px;margin:14px 0">
        <?php foreach ( array( 'from'=>'From port', 'to'=>'To port', 'code'=>'Trip code', 'class'=>'Accommodation', 'time'=>'Departure (Philippine time)', 'duration'=>'Duration (minutes)', 'start'=>'First operating date (optional)', 'end'=>'Last operating date (optional)', 'blocked'=>'Blocked dates (YYYY-MM-DD, comma-separated)' ) as $key => $label ) :
            $type = in_array( $key, array( 'start','end' ), true ) ? 'date' : ( 'time' === $key ? 'time' : ( 'duration' === $key ? 'number' : 'text' ) ); ?>
            <label><?php echo esc_html( $label ); ?><br><input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $base . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $row[ $key ] ); ?>" <?php echo ! in_array( $key, array( 'blocked', 'start', 'end' ), true ) ? 'required' : ''; ?> <?php echo 'duration' === $key ? 'min="1" max="2880"' : ''; ?>></label>
        <?php endforeach; ?>
        </div>
        <p>Leave both dates empty for an ongoing sailing. First date only: available from that date onward. Last date only: available through that date. Enabled, operating days, blocked dates and booking cutoff still apply.</p><p>Operating days:
        <?php foreach ( array( 1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun' ) as $day=>$name ) : ?>
            <label style="margin-right:12px"><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[days][]" value="<?php echo esc_attr( $day ); ?>" <?php checked( in_array( (string) $day, $row['days'], true ) ); ?>><?php echo esc_html( $name ); ?></label>
        <?php endforeach; ?></p>
        <p>Final fares per passenger in PHP. Blank disables a category; zero is a free fare. Enter verified fares, including any supplier taxes/charges you intend to include. Discounted fares require staff ID verification.</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
        <?php foreach ( Hotel_Rates_Oceanjet::CATEGORIES as $key=>$label ) : ?>
            <label><?php echo esc_html( $label ); ?><br><input style="width:110px" type="number" min="0" max="99999999.99" step="0.01" name="<?php echo esc_attr( $base ); ?>[fares][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( isset( $row['fares'][ $key ] ) ? number_format( $row['fares'][ $key ] / 100, 2, '.', '' ) : '' ); ?>" <?php echo 'adult' === $key ? 'required' : ''; ?>></label>
        <?php endforeach; ?></div>
    </fieldset>
    <?php
};
?>
<div class="wrap">
<h1>OceanJet</h1>
<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success"><p>OceanJet settings saved.</p></div><?php endif; ?>
<p>Admin-managed booking requests. Schedules and fares are not synchronized with OceanJet. Staff must secure the supplier ticket and verify any discounted passenger IDs. No live seat inventory is advertised.</p>
<p>Place <code>[oceanjet_booking]</code> on an Elementor page using its Shortcode widget. Saving these settings creates a hidden virtual WooCommerce product. Use this page for bookings rather than the product's default form.</p>
<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
<input type="hidden" name="action" value="hr_oceanjet_save">
<?php wp_nonce_field( 'hr_oceanjet_save' ); ?>
<table class="form-table">
<?php foreach ( array( 'cutoff'=>array('Booking cutoff before departure (minutes)',0,10080), 'max_pax'=>array('Maximum passengers, excluding infants',1,30), 'infant_max'=>array('Maximum infants',0,10), 'minor_max'=>array('Maximum minor age (infants are under 2)',2,17), 'senior_min'=>array('Minimum senior age',18,100), 'ticket_fee'=>array('Ticketing charge per booking in PHP (once, including round trips)',0,99999999) ) as $key=>$field ) : ?>
<tr><th><label for="oj-<?php echo esc_attr($key); ?>"><?php echo esc_html($field[0]); ?></label></th><td><input id="oj-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" type="number" min="<?php echo esc_attr($field[1]); ?>" max="<?php echo esc_attr($field[2]); ?>" step="<?php echo 'ticket_fee'===$key?'0.01':'1'; ?>" required value="<?php echo esc_attr($settings[$key]); ?>"></td></tr>
<?php endforeach; ?>
</table>
<p>The global convenience fee applies separately at checkout. This module's ticketing charge is included in the booking subtotal and therefore in the convenience-fee base. Leave it zero when already included in the fares. Adult/student/PWD categories start above the configured minor age.</p>
<h2>Sailings and fares</h2>
<p>Add one record per direction, departure time, accommodation, and effective date range. Use identical port names consistently. For price changes, use non-overlapping effective date ranges. Duplicate a sailing's details for Tourist Class, Open Air, and Business Class only when available.</p>
<div id="oj-sailings"><?php $index=0; foreach($settings['sailings'] as $row){ $render_row($index++,$row); } ?></div>
<template id="oj-template"><?php $render_row('__INDEX__'); ?></template>
<button type="button" class="button" id="oj-add">Add sailing / accommodation</button>
<input type="hidden" name="complete_form" value="1">
<?php submit_button('Save OceanJet settings'); ?>
</form>
</div>
<script>
(function(){
    var index=<?php echo (int)$index; ?>, root=document.getElementById('oj-sailings');
    document.getElementById('oj-add').addEventListener('click',function(){root.insertAdjacentHTML('beforeend',document.getElementById('oj-template').innerHTML.replace(/__INDEX__/g,String(index++)));});
    root.addEventListener('click',function(e){if(e.target.classList.contains('oj-remove') && confirm('Remove this sailing when settings are saved?')){e.target.closest('fieldset').remove();}});
}());
</script>