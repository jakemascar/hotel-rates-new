<?php
if ( ! defined('ABSPATH') ) { exit; }
$render_accommodation = function($group,$direction,$index,$entry=array()) {
    $entry=wp_parse_args($entry,array('id'=>'','name'=>'','fares'=>array('adult'=>0,'child'=>0)));
    $base='groups['.$group.'][directions]['.$direction.'][accommodations]['.$index.']'; ?>
    <div class="oj-accommodation-row">
        <input type="hidden" name="<?php echo esc_attr($base); ?>[id]" value="<?php echo esc_attr($entry['id']); ?>">
        <div class="oj-admin-fields">
            <label>Accommodation<input type="text" name="<?php echo esc_attr($base); ?>[name]" value="<?php echo esc_attr($entry['name']); ?>" required></label>
            <?php foreach(Hotel_Rates_Oceanjet::CATEGORIES as $key=>$label): ?>
                <label><?php echo esc_html($label); ?> fare per passenger (PHP)<input type="number" name="<?php echo esc_attr($base.'[fares]['.$key.']'); ?>" min="0" max="99999999.99" step="0.01" required value="<?php echo esc_attr(isset($entry['fares'][$key]) ? number_format($entry['fares'][$key]/100,2,'.','') : ''); ?>"></label>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button oj-remove-accommodation">Remove accommodation</button>
    </div>
    <?php
};
$render_time = function($group,$direction,$index,$time='08:00') {
    $hour=(int)substr($time,0,2);$minute=(int)substr($time,3,2);$period=$hour<12 ? 'AM' : 'PM'; ?>
    <div class="oj-time-row">
        <input class="oj-time-value" type="hidden" name="<?php echo esc_attr('groups['.$group.'][directions]['.$direction.'][times]['.$index.']'); ?>" value="<?php echo esc_attr($time); ?>">
        <div class="oj-time-picker" role="group" aria-label="Departure time in Philippine time">
            <select class="oj-time-hour" aria-label="Departure hour"><?php for($h=1;$h<=12;$h++): ?><option value="<?php echo $h; ?>" <?php selected($h,$hour%12 ?: 12); ?>><?php echo sprintf('%02d',$h); ?></option><?php endfor; ?></select>
            <span aria-hidden="true">:</span>
            <select class="oj-time-minute" aria-label="Departure minute"><?php for($m=0;$m<60;$m++): ?><option value="<?php echo $m; ?>" <?php selected($m,$minute); ?>><?php echo sprintf('%02d',$m); ?></option><?php endfor; ?></select>
            <select class="oj-time-period" aria-label="Departure AM or PM"><option <?php selected($period,'AM'); ?>>AM</option><option <?php selected($period,'PM'); ?>>PM</option></select>
        </div>
        <button type="button" class="button oj-remove-time" aria-label="Remove departure time" title="Remove departure time"><span aria-hidden="true">&times;</span></button>
    </div>
    <?php
};
$render_group = function($index,$group=array()) use($render_accommodation,$render_time) {
    $group=wp_parse_args($group,array('id'=>'','from'=>'','to'=>'','from_code'=>'','to_code'=>'','directions'=>array())); ?>
    <details class="oj-route-group" data-index="<?php echo esc_attr($index); ?>" <?php echo '__GROUP__'===$index ? 'open' : ''; ?>>
        <summary class="oj-route-heading"><span class="oj-route-title"><?php echo esc_html(($group['from'] || $group['to']) ? ($group['from'] ?: 'From').' ↔ '.($group['to'] ?: 'To') : 'New route group'); ?></span><span class="oj-route-toggle" aria-hidden="true"></span></summary>
        <div class="oj-route-content">
        <input type="hidden" name="<?php echo esc_attr('groups['.$index.'][id]'); ?>" value="<?php echo esc_attr($group['id']); ?>">
        <button type="button" class="button oj-remove-group">Remove route group</button>
        <div class="oj-admin-fields">
            <?php foreach(array('from'=>'From port','from_code'=>'From port display code (optional)','to'=>'To port','to_code'=>'To port display code (optional)') as $key=>$label): ?>
                <label><?php echo esc_html($label); ?><input type="text" data-port="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr('groups['.$index.']['.$key.']'); ?>" value="<?php echo esc_attr($group[$key]); ?>" <?php echo in_array($key,array('from','to'),true) ? 'required' : ''; ?>></label>
            <?php endforeach; ?>
        </div>
        <?php foreach(array('a'=>'A','b'=>'B') as $key=>$letter):
            $direction=wp_parse_args($group['directions'][$key] ?? array(),Hotel_Rates_Oceanjet::direction_defaults());
            $base='groups['.$index.'][directions]['.$key.']'; ?>
            <fieldset class="oj-direction" data-direction="<?php echo esc_attr($key); ?>">
                <legend><strong>Section <?php echo esc_html($letter); ?>: <span class="oj-direction-label"><?php echo esc_html(('a'===$key ? ($group['from'] ?: 'From').' → '.($group['to'] ?: 'To') : ($group['to'] ?: 'To').' → '.($group['from'] ?: 'From'))); ?></span></strong></legend>
                <div class="oj-direction-top">
                    <div class="oj-direction-settings">
                <label><input type="checkbox" name="<?php echo esc_attr($base); ?>[active]" value="1" <?php checked($direction['active']); ?>> Enabled</label>
                <div class="oj-admin-fields oj-shared-fields">
                    <label class="oj-code-field">Shared trip code (optional)<input type="text" name="<?php echo esc_attr($base); ?>[code]" value="<?php echo esc_attr($direction['code']); ?>"></label>
                    <label class="oj-duration-field">Shared duration (minutes)<input type="number" name="<?php echo esc_attr($base); ?>[duration]" value="<?php echo esc_attr($direction['duration']); ?>" min="1" max="900" required></label>
                    <label class="oj-blocked-field"><span>Blocked dates</span><input type="text" name="<?php echo esc_attr($base); ?>[blocked]" value="<?php echo esc_attr($direction['blocked']); ?>" placeholder="e.g. 2026-12-24, 2026-12-25"><small>Use YYYY-MM-DD, separated by commas.</small></label>
                </div>
                <p>Operating days:</p><div class="oj-weekdays">
                    <?php foreach(array(1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun') as $day=>$label): ?>
                        <label><input type="checkbox" name="<?php echo esc_attr($base); ?>[days][]" value="<?php echo esc_attr($day); ?>" <?php checked(in_array((string)$day,$direction['days'],true)); ?>><?php echo esc_html($label); ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="oj-accommodation-panel">
                <h3>Accommodations and fares</h3>
                <p>Each accommodation is available at every configured departure time. Fares are per passenger and shared across those times. Zero means free.</p>
                <div class="oj-accommodations"><?php foreach($direction['accommodations'] ?: array(array('name'=>'Tourist Class'),array('name'=>'Open Air'),array('name'=>'Business Class')) as $i=>$entry){$render_accommodation($index,$key,$i,$entry);} ?></div>
                <div class="oj-accommodation-actions">
                    <button type="button" class="button oj-add-accommodation">Add accommodation</button>
                    <?php if('a'===$key): ?>
                    <div class="oj-copy-action">
                        <button type="button" class="button oj-copy-accommodations">Duplicate Accommodations and Fares to Section B</button>
                        <small>Replaces Section B's accommodations and fares. Save settings to apply.</small>
                        <span class="oj-copy-status" role="status" aria-live="polite"></span>
                    </div>
                    <?php endif; ?>
                </div>
                </div>
                    </div>
                    <div class="oj-time-panel">
                <h3>Departure times</h3><p class="oj-field-hint">Philippine time · hour : minute AM/PM</p>
                <div class="oj-times"><?php foreach($direction['times'] ?: array('08:00') as $i=>$time){$render_time($index,$key,$i,$time);} ?></div>
                <button type="button" class="button oj-add-time">Add departure time</button>
                <p>Arrival uses the shared duration.</p>
                <?php if('a'===$key): ?>
                <div class="oj-time-copy">
                    <button type="button" class="button oj-copy-times">Duplicate Departure Times to Section B</button>
                    <small>Replaces Section B's departure times. Save settings to apply.</small>
                    <span class="oj-time-copy-status" role="status" aria-live="polite"></span>
                </div>
                <?php endif; ?>
                    </div>
                </div>
            </fieldset>
        <?php endforeach; ?>
        </div>
    </details>
    <?php
};
?>
<div class="wrap hr-oj-admin">
<h1>OceanJet</h1>
<?php if(isset($_GET['saved'])): ?><div class="notice notice-success"><p>OceanJet settings saved.</p></div><?php endif; ?>
<p>Insert <code>[oceanjet_booking]</code> on any page. Each route group provides both directions automatically. Staff confirm availability, verify ages and collect remaining passenger details.</p>
<?php if(!isset($settings['groups']) && $settings['sailings']): ?>
<div class="notice notice-warning"><p><strong>Review existing settings before saving.</strong> The editor combines each direction's accommodations and departure times. The first record supplies shared trip code, duration, weekdays, blocked dates and the fare for each accommodation. If old records differ, review the originals below and adjust the new sections. Saving removes first/last operating dates and makes every accommodation available at every configured time. Existing cart bookings will need to be submitted again; historical orders are retained.</p>
<details><summary>Original sailing settings</summary><div class="oj-legacy-records">
<?php foreach($settings['sailings'] as $row): ?>
<p><?php echo esc_html($row['from'].' → '.$row['to'].' | '.$row['class'].' | '.$row['time'].' | '.$row['code'].' | '.$row['duration'].' minutes | '.($row['active'] ? 'Enabled' : 'Disabled').' | Weekdays: '.implode(', ',$row['days']).' | Blocked: '.$row['blocked'].' | First: '.$row['start'].' | Last: '.$row['end'].' | Adult PHP '.number_format($row['fares']['adult']/100,2).' | Child PHP '.(isset($row['fares']['child']) ? number_format($row['fares']['child']/100,2) : 'not set')); ?></p>
<?php endforeach; ?></div></details></div>
<?php endif; ?>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
<input type="hidden" name="action" value="hr_oceanjet_save">
<?php wp_nonce_field('hr_oceanjet_save'); ?>
<table class="form-table">
<?php foreach(array('cutoff'=>array('Booking cutoff before departure (minutes)',0,10080),'max_pax'=>array('Maximum total passengers (Adults + Children)',1,30),'ticket_fee'=>array('Ticketing charge per booking in PHP (once for round trips)',0,99999999)) as $key=>$field): ?>
<tr><th><label for="oj-<?php echo esc_attr($key); ?>"><?php echo esc_html($field[0]); ?></label></th><td><input id="oj-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" type="number" min="<?php echo esc_attr($field[1]); ?>" max="<?php echo esc_attr($field[2]); ?>" step="<?php echo 'ticket_fee'===$key ? '0.01' : '1'; ?>" required value="<?php echo esc_attr($settings[$key]); ?>"></td></tr>
<?php endforeach; ?>
</table>
<p>The global convenience fee applies separately at checkout, including to the ticketing charge. Leave the ticketing charge zero when already included in fares.</p>
<div id="oj-groups"><?php $groups=$this->route_groups();foreach($groups as $index=>$group){$render_group($index,$group);} ?></div>
<button type="button" class="button" id="oj-add-group">Add route group</button>
<input type="hidden" name="complete_form" value="1">
<?php submit_button('Save OceanJet settings'); ?>
</form>
<template id="oj-group-template"><?php $render_group('__GROUP__'); ?></template>
<template id="oj-accommodation-template"><?php $render_accommodation('__GROUP__','__DIRECTION__','__ROW__'); ?></template>
<template id="oj-time-template"><?php $render_time('__GROUP__','__DIRECTION__','__ROW__'); ?></template>
</div>
<style>
.hr-oj-admin{color:#1d3548;overflow-wrap:anywhere}
.hr-oj-admin fieldset{min-width:0}
.hr-oj-admin legend{max-width:100%;padding:0 6px;font-size:14px}
.hr-oj-admin h3{font-size:16px;margin:20px 0 10px}
.hr-oj-admin p{line-height:1.6}
.hr-oj-admin .oj-route-group{border:1px solid #9bb3c6;border-radius:8px;margin:16px 0;background:#f5f8fb;min-width:0}
.hr-oj-admin .oj-route-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 20px;list-style:none;cursor:pointer;font-weight:600;font-size:16px;border-radius:8px}
.hr-oj-admin .oj-route-heading::-webkit-details-marker{display:none}
.hr-oj-admin .oj-route-heading:hover{background:#eaf1f7}
.hr-oj-admin .oj-route-heading:focus-visible{outline:2px solid #2271b1;outline-offset:2px}
.hr-oj-admin .oj-route-title{min-width:0;overflow-wrap:anywhere}
.hr-oj-admin .oj-route-toggle{display:flex;align-items:center;justify-content:center;flex:0 0 28px;width:28px;height:28px;border:1px solid #9bb3c6;border-radius:50%;font-size:22px;line-height:1}
.hr-oj-admin .oj-route-toggle::before{content:'\2193'}
.hr-oj-admin .oj-route-group[open]>.oj-route-heading{border-bottom:1px solid #d0dce5;border-radius:8px 8px 0 0}
.hr-oj-admin .oj-route-group[open]>.oj-route-heading .oj-route-toggle::before{content:'\2191'}
.hr-oj-admin .oj-route-content{padding:20px}
.hr-oj-admin .oj-direction{border:1px solid #d0dce5;border-radius:8px;padding:20px;margin:24px 0;background:#fff}
.hr-oj-admin .oj-admin-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:16px 0}
.hr-oj-admin .oj-admin-fields label{display:flex;flex-direction:column;gap:7px;font-weight:500;min-width:0}
.hr-oj-admin input[type=text],.hr-oj-admin input[type=number],.hr-oj-admin select{width:100%;min-width:0;min-height:40px;box-sizing:border-box;border:1px solid #8fa5b6;border-radius:5px;background:#fff;color:#1d3548;padding:7px 10px;margin:0;font-size:14px;line-height:1.5}
.hr-oj-admin input:focus-visible,.hr-oj-admin select:focus-visible,.hr-oj-admin button:focus-visible{outline:2px solid #2271b1;outline-offset:2px}
.hr-oj-admin .oj-direction-top{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;align-items:start}
.hr-oj-admin .oj-direction-settings,.hr-oj-admin .oj-time-panel,.hr-oj-admin .oj-accommodation-panel{min-width:0}
.hr-oj-admin .oj-shared-fields{grid-template-columns:minmax(130px,160px) minmax(150px,170px) minmax(0,1fr)}
.hr-oj-admin .oj-blocked-field small,.hr-oj-admin .oj-field-hint{font-size:12px;color:#52687b;font-weight:400;line-height:1.5}
.hr-oj-admin .oj-time-panel{padding:16px;border:1px solid #d0dce5;border-radius:8px;background:#f5f8fb}
.hr-oj-admin .oj-time-panel h3{margin:0 0 6px}
.hr-oj-admin .oj-field-hint{margin:0 0 12px}
.hr-oj-admin .oj-times{display:flex;flex-direction:column;gap:10px;margin:12px 0;max-height:300px;overflow-y:auto;padding:3px}
.hr-oj-admin .oj-time-row{display:flex;align-items:center;gap:10px;min-width:0}
.hr-oj-admin .oj-time-picker{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr) minmax(0,1.1fr);gap:4px;align-items:center;flex:1;min-width:0}
.hr-oj-admin .oj-time-picker select{padding:6px 18px 6px 6px}
.hr-oj-admin .oj-weekdays{display:flex;flex-wrap:wrap;gap:12px}
.hr-oj-admin .oj-weekdays label{display:flex;align-items:center;gap:4px;min-height:32px}
.hr-oj-admin .oj-accommodation-row{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;border:1px solid #d0dce5;border-radius:6px;padding:16px;margin:12px 0;background:#f7fafc}
.hr-oj-admin .oj-accommodation-row .oj-admin-fields{flex:1 1 420px;grid-template-columns:minmax(120px,1.2fr) repeat(2,minmax(100px,1fr));gap:12px;min-width:0;margin:0}
.hr-oj-admin .oj-remove-accommodation{flex:0 0 auto;margin-left:auto}
.hr-oj-admin .oj-accommodation-actions{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px}
.hr-oj-admin .oj-copy-action{display:flex;flex-direction:column;align-items:flex-end;gap:6px;max-width:100%;margin-left:auto;text-align:right}
.hr-oj-admin .oj-copy-action small{font-size:12px;color:#52687b}
.hr-oj-admin .oj-copy-status:empty{display:none}
.hr-oj-admin .oj-time-copy{display:flex;flex-direction:column;gap:6px;margin-top:16px;padding-top:12px;border-top:1px solid #d0dce5}
.hr-oj-admin .oj-time-copy button{max-width:100%}
.hr-oj-admin .oj-time-copy small{font-size:12px;color:#52687b}
.hr-oj-admin .oj-time-copy-status:empty{display:none}
.hr-oj-admin .oj-legacy-records{max-height:260px;overflow:auto}
.hr-oj-admin button{white-space:normal!important;min-height:40px;border-radius:5px}
.hr-oj-admin .oj-remove-time{display:inline-flex;align-items:center;justify-content:center;flex:0 0 36px;width:36px;height:36px;min-height:36px;padding:0;border:1px solid #d6a8a8;border-radius:50%;background:#fff;color:#a32525;font-size:24px;line-height:1;cursor:pointer}
.hr-oj-admin .oj-remove-time:hover{background:#fceeee;border-color:#a32525;color:#821c1c}
@media(max-width:1100px){.hr-oj-admin .oj-direction-top{grid-template-columns:minmax(0,1fr)}.hr-oj-admin .oj-time-panel{max-width:420px}}
@media(max-width:700px){.hr-oj-admin .oj-shared-fields{grid-template-columns:minmax(0,1fr)}}
@media(max-width:600px){
.hr-oj-admin .oj-route-content,.hr-oj-admin .oj-route-heading,.hr-oj-admin .oj-direction{padding:12px}
.hr-oj-admin .oj-admin-fields{grid-template-columns:minmax(0,1fr)}
.hr-oj-admin .oj-accommodation-row{flex-direction:column;align-items:stretch;padding:12px}
.hr-oj-admin .oj-accommodation-row .oj-admin-fields{flex:initial;grid-template-columns:minmax(0,1fr);width:100%}
.hr-oj-admin .oj-remove-accommodation{align-self:flex-end}
.hr-oj-admin .oj-copy-action{width:100%;align-items:stretch;text-align:left}
.hr-oj-admin .oj-time-panel{padding:12px;max-width:none}
.hr-oj-admin .form-table,.hr-oj-admin .form-table tbody,.hr-oj-admin .form-table tr,.hr-oj-admin .form-table th,.hr-oj-admin .form-table td{display:block;width:auto}
.hr-oj-admin input[type=text],.hr-oj-admin input[type=number],.hr-oj-admin select{font-size:16px}
}
</style>
<script>
(function(){
    const root=document.getElementById('oj-groups');let groupIndex=<?php echo count($groups); ?>,rowIndex=Date.now();
    function labels(group){const from=group.querySelector('[data-port=from]').value,to=group.querySelector('[data-port=to]').value;group.querySelector('.oj-route-title').textContent=from||to?(from||'From')+' ↔ '+(to||'To'):'New route group';group.querySelector('[data-direction=a] .oj-direction-label').textContent=(from||'From')+' → '+(to||'To');group.querySelector('[data-direction=b] .oj-direction-label').textContent=(to||'To')+' → '+(from||'From');}
    function updateTime(row){const hour=Number(row.querySelector('.oj-time-hour').value)%12+(row.querySelector('.oj-time-period').value==='PM'?12:0),minute=Number(row.querySelector('.oj-time-minute').value);row.querySelector('.oj-time-value').value=String(hour).padStart(2,'0')+':'+String(minute).padStart(2,'0');}
    function setTime(row,value){const [hour,minute]=value.split(':').map(Number);row.querySelector('.oj-time-hour').value=String(hour%12||12);row.querySelector('.oj-time-minute').value=String(minute);row.querySelector('.oj-time-period').value=hour<12?'AM':'PM';updateTime(row);}
    function copyTimes(group){
        const source=group.querySelector('[data-direction=a]'),target=group.querySelector('[data-direction=b] .oj-times'),status=source.querySelector('.oj-time-copy-status');
        const rows=[...source.querySelectorAll('.oj-time-row')];
        if(!rows.length){status.textContent='Add at least one departure time to Section A first.';return;}
        const copies=rows.map(row=>{
            updateTime(row);
            const template=document.createElement('template');
            template.innerHTML=document.getElementById('oj-time-template').innerHTML.replace(/__GROUP__/g,group.dataset.index).replace(/__DIRECTION__/g,'b').replace(/__ROW__/g,String(rowIndex++));
            const copy=template.content.firstElementChild;
            setTime(copy,row.querySelector('.oj-time-value').value);
            return copy;
        });
        target.replaceChildren(...copies);
        status.textContent='Departure times copied to Section B. Save OceanJet settings to apply.';
    }
    function copyAccommodations(group){
        const source=group.querySelector('[data-direction=a]'),target=group.querySelector('[data-direction=b] .oj-accommodations'),status=source.querySelector('.oj-copy-status');
        const rows=[...source.querySelectorAll('.oj-accommodation-row')];
        if(!rows.length){status.textContent='Add at least one accommodation to Section A first.';return;}
        for(const input of source.querySelectorAll('.oj-accommodation-row input')){if(!input.reportValidity())return;}
        const existing=new Map([...target.querySelectorAll('.oj-accommodation-row')].map(row=>[row.querySelector('[name$="[name]"]').value.trim().toLowerCase(),row.querySelector('[name$="[id]"]').value]));
        const copies=rows.map(row=>{
            const copy=row.cloneNode(true),inputs=[...row.querySelectorAll('input')],index=String(rowIndex++);
            copy.querySelectorAll('input').forEach((input,i)=>{input.value=inputs[i].value;input.name=input.name.replace(/\[directions\]\[a\]\[accommodations\]\[[^\]]+\]/,'[directions][b][accommodations]['+index+']');});
            copy.querySelector('[name$="[id]"]').value=existing.get(copy.querySelector('[name$="[name]"]').value.trim().toLowerCase())||'';
            return copy;
        });
        target.replaceChildren(...copies);
        status.textContent='Copied to Section B. Save OceanJet settings to apply.';
    }
    function addRow(direction,type){const group=direction.closest('.oj-route-group'),html=document.getElementById('oj-'+type+'-template').innerHTML.replace(/__GROUP__/g,group.dataset.index).replace(/__DIRECTION__/g,direction.dataset.direction).replace(/__ROW__/g,String(rowIndex++));const list=direction.querySelector(type==='time'?'.oj-times':'.oj-accommodations');list.insertAdjacentHTML('beforeend',html);if(type==='time'){const values=[...list.querySelectorAll('.oj-time-value')].slice(0,-1).map(el=>el.value),last=values[values.length-1]||'07:30';let [hour,minute]=last.split(':').map(Number),next=(hour*60+minute+30)%1440;for(let n=0;n<1440;n++,next=(next+1)%1440){const value=String(Math.floor(next/60)).padStart(2,'0')+':'+String(next%60).padStart(2,'0');if(!values.includes(value)){setTime(list.lastElementChild,value);break;}}list.lastElementChild.querySelector('select').focus();}}
    document.getElementById('oj-add-group').addEventListener('click',()=>{root.insertAdjacentHTML('beforeend',document.getElementById('oj-group-template').innerHTML.replace(/__GROUP__/g,String(groupIndex++)));const group=root.lastElementChild;group.open=true;group.querySelector('[data-port=from]').focus();});
    root.addEventListener('invalid',e=>{const group=e.target.closest('.oj-route-group');if(group)group.open=true;},true);
    root.addEventListener('change',e=>{if(e.target.closest('.oj-time-picker'))updateTime(e.target.closest('.oj-time-row'));});
    root.addEventListener('input',e=>{if(e.target.matches('[data-port=from],[data-port=to]'))labels(e.target.closest('.oj-route-group'));});
    root.addEventListener('click',e=>{
        const button=e.target.closest('button');if(!button)return;
        if(button.classList.contains('oj-add-accommodation'))addRow(button.closest('.oj-direction'),'accommodation');
        if(button.classList.contains('oj-copy-accommodations'))copyAccommodations(button.closest('.oj-route-group'));
        if(button.classList.contains('oj-copy-times'))copyTimes(button.closest('.oj-route-group'));
        if(button.classList.contains('oj-add-time'))addRow(button.closest('.oj-direction'),'time');
        if(button.classList.contains('oj-remove-accommodation'))button.closest('.oj-accommodation-row').remove();
        if(button.classList.contains('oj-remove-time'))button.closest('.oj-time-row').remove();
        if(button.classList.contains('oj-remove-group')&&confirm('Remove this route group when settings are saved?'))button.closest('.oj-route-group').remove();
    });
}());
</script>
