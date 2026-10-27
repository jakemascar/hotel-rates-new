<?php
// Standalone domain regression tests; no WordPress database or external requests.
define('ABSPATH', __DIR__);
function add_action() {} function add_filter() {} function add_shortcode() {}
function wp_parse_args($a,$b){return array_merge($b,$a);}
function get_option($name,$default=array()){return $GLOBALS['settings'] ?? $default;}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_email($v){return filter_var($v,FILTER_SANITIZE_EMAIL);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function wp_json_encode($v){return json_encode($v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$v));}
function wp_generate_uuid4(){static $n=0;return 'id-'.++$n;}
function get_privacy_policy_url(){return '';}
require __DIR__.'/../includes/class-hotel-rates-oceanjet.php';
if(in_array('--view',$argv,true)){include __DIR__.'/../public/views/oceanjet.php';exit;}
function ok($value,$message){if(!$value){throw new Exception($message);}echo "PASS: $message\n";}
function fails($fn,$message){try{$fn();}catch(Throwable $e){echo "PASS: $message\n";return;}throw new Exception('Expected rejection: '.$message);}
$service=new Hotel_Rates_Oceanjet();
$row=array('id'=>'out','active'=>true,'from'=>'Bacolod','to'=>'Iloilo','code'=>'OJ101','class'=>'Tourist Class','time'=>'08:00','duration'=>60,'start'=>'2099-01-01','end'=>'2099-12-31','blocked'=>'','days'=>array('1','2','3','4','5','6','7'),'fares'=>array('adult'=>66000,'child'=>33000));
$return=$row;$return['id']='back';$return['from']='Iloilo';$return['to']='Bacolod';$return['time']='12:00';
$GLOBALS['settings']=array('sailings'=>array('out'=>$row,'back'=>$return),'ticket_fee'=>'50.00');
$input=array('from'=>'Bacolod','to'=>'Iloilo','date'=>'2099-01-02','return'=>'','counts'=>array('adult'=>3,'child'=>2),'outbound'=>'out');
$q=$service->quote($input);
ok($q['cents']===269000,'three Adults + two Children + one ticketing charge');
$round=$input;$round['return']='2099-01-02';$round['inbound']='back';
ok($service->quote($round)['cents']===533000,'round trip adds two legs but ticket charge only once');
$GLOBALS['settings']['sailings']['back']['time']='08:30';fails(function()use($service,$round){$service->quote($round);},'return before outbound arrival');
$GLOBALS['settings']['sailings']['back']=$return;
$GLOBALS['settings']['sailings']['out']['blocked']='2099-01-02';fails(function()use($service,$input){$service->quote($input);},'blocked date');
$GLOBALS['settings']['sailings']['out']=$row;
$GLOBALS['settings']['sailings']['out']['fares']['child']=null;fails(function()use($service,$input){$service->quote($input);},'missing category fare is unavailable, not free');
$GLOBALS['settings']['sailings']['out']=$row;
$GLOBALS['settings']['sailings']['out']['active']=false;fails(function()use($service,$input){$service->quote($input);},'disabled sailing');
$GLOBALS['settings']['sailings']['out']=$row;
$bad=$input;$bad['date']='2099-02-30';fails(function()use($service,$bad){$service->quote($bad);},'impossible date');
$bad=$input;$bad['counts']['adult']=-1;fails(function()use($service,$bad){$service->quote($bad);},'negative passenger count');
$bad=$input;$bad['counts']['adult']=31;fails(function()use($service,$bad){$service->quote($bad);},'passenger limit');
$bad=$input;$bad['cents']=1;ok($service->quote($bad)['cents']===$q['cents'],'client price ignored');
$GLOBALS['settings']['sailings']['out']['fares']['adult']++;ok($service->quote($input)['signature']!==$q['signature'],'fare changes invalidate review signature');
$GLOBALS['settings']['sailings']['out']=$row;
$person=array('first'=>'Test','last'=>'Passenger','middle'=>'','phone'=>'+639123456789','nationality'=>'Canadian','sex'=>'Female','dob'=>'2018-01-01','email'=>'');
ok(count($service->passengers(array('passengers'=>array($person)),$q))===1,'one passenger details for a five-person booking; staff verify ages');
$child=$input;$child['counts']=array('adult'=>0,'child'=>2);$cq=$service->quote($child);
ok($cq['cents']===71000,'child-only booking uses Child fares without requiring Adult');
ok($service->passengers(array('passengers'=>array(array_merge($person,array('category'=>'child')))),$cq)[0]['category']==='child','child-only passenger details accepted');
$adult=$input;$adult['counts']=array('adult'=>2,'child'=>0);ok($service->quote($adult)['cents']===137000,'adult-only booking excludes Child fare');
$zero=$input;$zero['counts']=array('adult'=>0,'child'=>0);fails(function()use($service,$zero){$service->quote($zero);},'zero total rejected');
$bad=$input;$bad['counts']['student']=1;fails(function()use($service,$bad){$service->quote($bad);},'removed categories require new booking');
$bad=$input;$bad['counts']['child']='1.5';fails(function()use($service,$bad){$service->quote($bad);},'fractional quantities rejected');
$bad=$input;$bad['counts']=array('adult'=>6,'child'=>5);fails(function()use($service,$bad){$service->quote($bad);},'combined passenger limit');
$bad=$input;$bad['type']='round';fails(function()use($service,$bad){$service->quote($bad);},'round trip requires return date');
fails(function()use($service,$q){$service->passengers(array('passengers'=>array()),$q);},'passenger details required');
fails(function()use($service,$person,$q){$service->passengers(array('passengers'=>array($person,$person)),$q);},'only one passenger details collected');
fails(function()use($service,$person,$q){$service->passengers(array('passengers'=>array(array_merge($person,array('dob'=>'2099-01-01')))),$q);},'future birth date rejected');
fails(function()use($service,$person,$q){$service->passengers(array('passengers'=>array(array_merge($person,array('phone'=>'')))),$q);},'follow-up phone required');
fails(function()use($service,$person,$cq){$service->passengers(array('passengers'=>array(array_merge($person,array('category'=>'adult')))),$cq);},'passenger category must be in booked quantities');
$groups=$service->route_groups();ok(count($groups)===1 && count($groups[0]['directions'])===2,'reverse directions grouped into fixed A/B sections');
ok($groups[0]['directions']['b']['times']===array('12:00'),'reverse sailing assigned to section B');
$legacy=$row;$legacy['fares']=array('adult'=>66000,'minor'=>33000,'senior'=>50000);$GLOBALS['settings']['sailings']['out']=$legacy;
ok($service->settings()['sailings']['out']['fares']['child']===33000,'legacy Minor fare migrates to Child without changing ID');
$GLOBALS['settings']['sailings']['out']=$row;
fails(function(){Hotel_Rates_Oceanjet::money('1.001');},'reject fractional cents');
fails(function(){Hotel_Rates_Oceanjet::money('-10');},'reject negative fare');
$expired=$row;$expired['start']='2020-01-01';$expired['end']='2020-12-31';ok(!$service->available($expired,'2020-01-02','Bacolod','Iloilo'),'past sailing unavailable');
$open=$row;$open['start']='';$open['end']='';
ok($service->available($open,'2099-01-02','Bacolod','Iloilo'),'empty dates allow ongoing sailing');
$startOnly=$open;$startOnly['start']='2099-01-02';
ok(!$service->available($startOnly,'2099-01-01','Bacolod','Iloilo') && $service->available($startOnly,'2099-01-02','Bacolod','Iloilo'),'start-only range includes start and excludes earlier dates');
$endOnly=$open;$endOnly['end']='2099-01-02';
ok($service->available($endOnly,'2099-01-02','Bacolod','Iloilo') && !$service->available($endOnly,'2099-01-03','Bacolod','Iloilo'),'end-only range includes end and excludes later dates');
ok(!$service->available($row,'2100-01-01','Bacolod','Iloilo'),'bounded range still expires');
foreach(array(array('',''),array('2099-01-01',''),array('','2099-12-31'),array('2099-01-01','2099-12-31')) as $range){ok(Hotel_Rates_Oceanjet::valid_operating_dates(...$range),'valid optional range: '.implode(' / ',$range));}
ok(!Hotel_Rates_Oceanjet::valid_operating_dates('2099-02-30','') && !Hotel_Rates_Oceanjet::valid_operating_dates('','invalid'),'nonempty dates must be valid');
ok(!Hotel_Rates_Oceanjet::valid_operating_dates('2099-12-31','2099-01-01'),'reversed range rejected');
ok(Hotel_Rates_Oceanjet::operating_dates_overlap($open,$row) && Hotel_Rates_Oceanjet::operating_dates_overlap($row,$open),'unbounded range overlaps existing dates in either order');
ok(Hotel_Rates_Oceanjet::operating_dates_overlap($startOnly,$endOnly),'shared date counts as overlap');
$endOnly['end']='2099-01-01';
ok(!Hotel_Rates_Oceanjet::operating_dates_overlap($startOnly,$endOnly) && !Hotel_Rates_Oceanjet::operating_dates_overlap($endOnly,$startOnly),'separate open-ended ranges do not overlap');
$disabled=$open;$disabled['active']=false;ok(!$service->available($disabled,'2099-01-02','Bacolod','Iloilo'),'empty dates do not override disabled status');
$blocked=$open;$blocked['blocked']='2099-01-02';ok(!$service->available($blocked,'2099-01-02','Bacolod','Iloilo'),'empty dates respect blocked dates');
$noDays=$open;$noDays['days']=array();ok(!$service->available($noDays,'2099-01-02','Bacolod','Iloilo'),'empty dates respect operating weekdays');
ok(!$service->available($open,'2020-01-01','Bacolod','Iloilo'),'empty dates do not allow past departures');
// New settings: every accommodation uses every time, with independent direction fares.
$group=$service->route_groups()[0];
foreach(array('a','b') as $key){
    $group['directions'][$key]['times']=$key==='a'?array('08:00','10:00'):array('12:00','14:00');
    $group['directions'][$key]['accommodations']=array(
        array('id'=>'tourist','name'=>'Tourist Class','fares'=>array('adult'=>'660.00','child'=>'330.00')),
        array('id'=>'open','name'=>'Open Air','fares'=>array('adult'=>'600.00','child'=>'300.00')),
        array('id'=>'business','name'=>'Business Class','fares'=>array('adult'=>$key==='a'?'900.00':'1000.00','child'=>$key==='a'?'450.00':'500.00')),
    );
}
$normalized=$service->normalize_groups(array($group));
$rows=$service->expand_groups($normalized);ok(count($rows)===12,'two directions times two departures times three accommodations');
ok(count($normalized[0]['directions'])===2,'exactly fixed A and B');
$stored=$GLOBALS['settings'];$GLOBALS['settings']=array('groups'=>$normalized,'ticket_fee'=>'50.00');
$out=array_values(array_filter($service->settings()['sailings'],function($r){return $r['from']==='Bacolod'&&$r['class']==='Business Class';}));
ok(count($out)===2 && $out[0]['fares']===$out[1]['fares'],'accommodation fares shared across departure times');
ok($out[0]['start']==='' && $out[0]['end']==='','new schedules have no first/last operating dates');
ok($service->available($out[0],'2100-01-01','Bacolod','Iloilo'),'new schedules remain ongoing beyond legacy effective dates');
$reverse=array_values(array_filter($service->settings()['sailings'],function($r){return $r['from']==='Iloilo'&&$r['class']==='Business Class';}));
$trip=array('from'=>'Iloilo','to'=>'Bacolod','date'=>'2099-01-02','counts'=>array('adult'=>1,'child'=>1),'outbound'=>$reverse[1]['id']);
ok($service->quote($trip)['cents']===155000,'reverse-origin quote uses B accommodation fare');
$trip['outbound']=$out[0]['id'];fails(function()use($service,$trip){$service->quote($trip);},'cannot book section A sailing for reverse route');
$again=$normalized;foreach($again[0]['directions'] as &$direction){foreach($direction['accommodations'] as &$entry){foreach($entry['fares'] as &$fare){$fare=number_format($fare/100,2,'.','');}unset($fare);}unset($entry);}unset($direction);
ok(array_keys($service->expand_groups($service->normalize_groups($again)))===array_keys($rows),'unchanged admin save keeps sailing identifiers stable');
$limit=$group;$limit['directions']['a']['duration']=900;ok($service->normalize_groups(array($limit))[0]['directions']['a']['duration']===900,'900-minute shared duration accepted');
$limit['directions']['a']['duration']=901;fails(function()use($service,$limit){$service->normalize_groups(array($limit));},'shared duration above 900 rejected');
$bad=$group;$bad['directions']['a']['times'][]='08:00';fails(function()use($service,$bad){$service->normalize_groups(array($bad));},'duplicate times rejected');
$bad=$group;$bad['directions']['a']['times']=array('25:00');fails(function()use($service,$bad){$service->normalize_groups(array($bad));},'invalid time rejected');
$bad=$group;unset($bad['directions']['b']);fails(function()use($service,$bad){$service->normalize_groups(array($bad));},'missing B direction rejected');
$bad=$group;$bad['directions']['a']['accommodations'][1]['name']='tourist class';fails(function()use($service,$bad){$service->normalize_groups(array($bad));},'duplicate accommodation names rejected');
$bad=$group;$bad['directions']['a']['blocked']='2099-02-30';fails(function()use($service,$bad){$service->normalize_groups(array($bad));},'invalid blocked date rejected');
$blocked=$out[0];$blocked['blocked']='2099-01-02';ok(!$service->available($blocked,'2099-01-02','Bacolod','Iloilo'),'shared blocked dates honored');
$off=$out[0];$off['days']=array();ok(!$service->available($off,'2099-01-02','Bacolod','Iloilo'),'shared operating weekdays honored');
$GLOBALS['settings']=$stored;
echo "All OceanJet domain checks passed.\n";
