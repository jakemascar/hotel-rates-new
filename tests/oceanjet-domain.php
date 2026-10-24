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
function get_privacy_policy_url(){return '';}
require __DIR__.'/../includes/class-hotel-rates-oceanjet.php';
if(in_array('--view',$argv,true)){include __DIR__.'/../public/views/oceanjet.php';exit;}
function ok($value,$message){if(!$value){throw new Exception($message);}echo "PASS: $message\n";}
function fails($fn,$message){try{$fn();}catch(Throwable $e){echo "PASS: $message\n";return;}throw new Exception('Expected rejection: '.$message);}
$service=new Hotel_Rates_Oceanjet();
$row=array('id'=>'out','active'=>true,'from'=>'Bacolod','to'=>'Iloilo','code'=>'OJ101','class'=>'Tourist Class','time'=>'08:00','duration'=>60,'start'=>'2099-01-01','end'=>'2099-12-31','blocked'=>'','days'=>array('1','2','3','4','5','6','7'),'fares'=>array('adult'=>66000,'minor'=>33000,'infant'=>0,'student'=>50000,'pwd'=>50000,'senior'=>50000));
$return=$row;$return['id']='back';$return['from']='Iloilo';$return['to']='Bacolod';$return['time']='12:00';
$GLOBALS['settings']=array('sailings'=>array('out'=>$row,'back'=>$return),'ticket_fee'=>'50.00');
$input=array('from'=>'Bacolod','to'=>'Iloilo','date'=>'2099-01-02','return'=>'','counts'=>array('adult'=>2,'minor'=>1,'infant'=>1),'outbound'=>'out');
$q=$service->quote($input);
ok($q['cents']===170000,'adult + minor + free infant + one ticketing charge');
$round=$input;$round['return']='2099-01-02';$round['inbound']='back';
ok($service->quote($round)['cents']===335000,'round trip adds two legs but ticket charge only once');
$GLOBALS['settings']['sailings']['back']['time']='08:30';fails(function()use($service,$round){$service->quote($round);},'return before outbound arrival');
$GLOBALS['settings']['sailings']['back']=$return;
$GLOBALS['settings']['sailings']['out']['blocked']='2099-01-02';fails(function()use($service,$input){$service->quote($input);},'blocked date');
$GLOBALS['settings']['sailings']['out']=$row;
$GLOBALS['settings']['sailings']['out']['fares']['minor']=null;fails(function()use($service,$input){$service->quote($input);},'missing category fare is unavailable, not free');
$GLOBALS['settings']['sailings']['out']=$row;
$GLOBALS['settings']['sailings']['out']['active']=false;fails(function()use($service,$input){$service->quote($input);},'disabled sailing');
$GLOBALS['settings']['sailings']['out']=$row;
$bad=$input;$bad['date']='2099-02-30';fails(function()use($service,$bad){$service->quote($bad);},'impossible date');
$bad=$input;$bad['counts']['adult']=-1;fails(function()use($service,$bad){$service->quote($bad);},'negative passenger count');
$bad=$input;$bad['counts']['adult']=31;fails(function()use($service,$bad){$service->quote($bad);},'passenger limit');
$bad=$input;$bad['cents']=1;ok($service->quote($bad)['cents']===$q['cents'],'client price ignored');
$GLOBALS['settings']['sailings']['out']['fares']['adult']++;ok($service->quote($input)['signature']!==$q['signature'],'fare changes invalidate review signature');
$GLOBALS['settings']['sailings']['out']=$row;
$person=array('first'=>'Test','last'=>'Passenger','middle'=>'','phone'=>'+639123456789','nationality'=>'Filipino','sex'=>'Female','dob'=>'2070-01-01','email'=>'');
$people=array($person,$person,array_merge($person,array('dob'=>'2090-01-01')),array_merge($person,array('dob'=>'2098-01-01')));
ok(count($service->passengers(array('passengers'=>$people),$q))===4,'validate all passenger categories');
$people[2]['dob']='2070-01-01';fails(function()use($service,$people,$q){$service->passengers(array('passengers'=>$people),$q);},'adult cannot use minor fare');
$student=$input;$student['counts']=array('student'=>1);$sq=$service->quote($student);
fails(function()use($service,$person,$sq){$service->passengers(array('passengers'=>array(array_merge($person,array('nationality'=>'Canadian')))),$sq);},'discount nationality');
$birthday=$round;$birthday['counts']=array('adult'=>1,'infant'=>1);$birthday['return']='2099-01-04';$bq=$service->quote($birthday);
fails(function()use($service,$person,$bq){$service->passengers(array('passengers'=>array($person,array_merge($person,array('dob'=>'2097-01-03')))),$bq);},'infant turns two before return');
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
echo "All OceanJet domain checks passed.\n";
