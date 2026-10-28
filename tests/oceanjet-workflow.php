<?php
// Isolated workflow fixtures: real OceanJet handlers, minimal WP/WooCommerce substitutes.
define('ABSPATH', __DIR__);
define('HR_PLUGIN_DIR', dirname(__DIR__) . '/');
define('HR_PLUGIN_URL', 'https://booking.test/');define('HR_VERSION','test');
function wp_enqueue_style(){} function wp_enqueue_script(){}
function wp_create_nonce(){return 'test';}
function wp_localize_script($handle,$name,$data){$GLOBALS['config']=$data;}
function get_privacy_policy_url(){return '';}
function wc_get_page_permalink(){return '';}
function add_action() {} function add_filter() {} function add_shortcode() {}
function wp_parse_args($a,$b){return array_merge($b,$a);}
function get_option($name,$default=array()){return $GLOBALS['settings'] ?? $default;}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_key($v){return strtolower(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$v));}
function sanitize_email($v){return filter_var($v,FILTER_SANITIZE_EMAIL);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function wp_json_encode($v){return json_encode($v);}
function current_user_can(){return true;}
function check_admin_referer(){} function check_ajax_referer(){}
function wp_unslash($v){return $v;}
function wp_generate_uuid4(){static $n=0;return 'new-'.++$n;}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_attr($v){return esc_html($v);} function esc_url($v){return esc_html($v);}
function checked($a,$b=true){if($a==$b)echo 'checked';} function selected($a,$b){if($a==$b)echo 'selected';}
function wp_nonce_field(){} function submit_button(){echo '<button>Save OceanJet settings</button>';}
function admin_url($v){return $v;}
function wc_get_checkout_url(){return 'https://booking.test/checkout';}
function wc_get_product(){return new TestProduct();}
function get_post_meta(){return 'yes';}
function wc_add_notice($message){$GLOBALS['notices'][]=$message;}
function WC(){return $GLOBALS['wc'];}
function expect($condition,$message){if(!$condition){fwrite(STDERR,'FAIL: '.$message."\n");exit(1);}}
class WooCommerce {}
class TestProduct {public $price=0;function is_purchasable(){return true;}function set_price($value){$this->price=$value;}}
class TestCart {
    public $cart_contents=array();
    function get_cart(){return $this->cart_contents;}
    function add_to_cart($id,$qty,$variation,$attributes,$data){
        expect($GLOBALS['service']->guard_product(true,$id,$qty),'booking product accepts authorized handler');
        $this->cart_contents['item']=$data+array('product_id'=>$id,'quantity'=>$qty,'data'=>new TestProduct());return 'item';
    }
    function calculate_totals(){$GLOBALS['service']->cart_prices($this);}
    function set_session(){}
}
class TestCustomer {
    public $email='checkout@example.com';
    function set_billing_first_name($v){} function set_billing_last_name($v){} function set_billing_phone($v){}
    function set_billing_email($v){$this->email=$v;} function save(){}
}
class TestItem {public $meta=array();function add_meta_data($key,$value){$this->meta[$key]=$value;}}
require HR_PLUGIN_DIR.'includes/class-hotel-rates-oceanjet.php';
$GLOBALS['service']=$service=new Hotel_Rates_Oceanjet();
$row=array('id'=>'out','active'=>true,'from'=>'Dumaguete','to'=>'Siquijor','from_code'=>'DUM','to_code'=>'SIQ','code'=>'OJ501','class'=>'Tourist Class','time'=>'08:00','duration'=>60,'start'=>'','end'=>'','blocked'=>'','days'=>array('1','2','3','4','5','6','7'),'fares'=>array('adult'=>66000,'minor'=>33000));
$back=$row;$back['id']='back';$back['from']='Siquijor';$back['to']='Dumaguete';$back['from_code']='SIQ';$back['to_code']='DUM';$back['time']='12:00';
$GLOBALS['settings']=array('product_id'=>99,'ticket_fee'=>'50.00','sailings'=>array('out'=>$row,'back'=>$back));
$mode=$argv[1] ?? 'book';
if('--view'===$mode){$service->admin_page();exit;}
$GLOBALS['expected_error']='';
function wp_die($message){
    expect($GLOBALS['expected_error'] && strpos($message,$GLOBALS['expected_error'])!==false,'expected save rejection: '.$message);
    echo 'PASS: '.$GLOBALS['mode']."\n";exit;
}
function update_option($key,$settings){
    expect(!$GLOBALS['expected_error'],'invalid settings must not be saved');
    expect(isset($settings['groups']) && !isset($settings['sailings']),'shared grouped settings persisted');
    $rows=$GLOBALS['service']->expand_groups($settings['groups']);
    expect(count($rows)===8,'two directions with two times and two accommodations');
    $reverse=array_values(array_filter($rows,function($r){return $r['from']==='Siquijor';}));
    expect($reverse[0]['to_code']==='DUM','reverse port and display code mapping');
    expect($reverse[0]['fares']===array('adult'=>70000,'child'=>35000),'independent B direction fares');
    expect($settings['product_id']===99,'existing product retained');
    if($GLOBALS['mode']==='save-no-code'){expect($settings['groups'][0]['directions']['a']['code']==='' && $settings['groups'][0]['directions']['b']['code']==='','blank optional trip codes saved');}
    echo "PASS: A/B save, multiple times/accommodations, direction fares and existing product\n";
}
function wp_safe_redirect(){}
$GLOBALS['mode']=$mode;
$group=$service->route_groups()[0];
foreach(array('a','b') as $key){
    $group['directions'][$key]['times']=$key==='a'?array('08:00','10:00'):array('12:00','14:00');
    $group['directions'][$key]['accommodations']=array(
        array('id'=>'tourist','name'=>'Tourist Class','fares'=>array('adult'=>$key==='a'?'660.00':'700.00','child'=>$key==='a'?'330.00':'350.00')),
        array('id'=>'business','name'=>'Business Class','fares'=>array('adult'=>$key==='a'?'900.00':'1000.00','child'=>$key==='a'?'450.00':'500.00')),
    );
}
if(in_array($mode,array('save-no-code','book-no-code'),true)){foreach(array('a','b') as $key){$group['directions'][$key]['code']='';}}
if(strpos($mode,'save')===0){
    $_POST=array('complete_form'=>'1','groups'=>array($group),'ticket_fee'=>'50.00');
    if($mode==='save-duplicate'){$_POST['groups'][]=$group;$GLOBALS['expected_error']='Combine both directions';}
    if($mode==='save-missing-fare'){$_POST['groups'][0]['directions']['a']['accommodations'][0]['fares']['child']='';$GLOBALS['expected_error']='Enter both Adult and Child';}
    if($mode==='save-overlap'){$_POST['groups'][0]['directions']['a']['times'][]='08:00';$GLOBALS['expected_error']='Duplicate departure';}
    if($mode==='save-truncated'){unset($_POST['complete_form']);$GLOBALS['expected_error']='truncated';}
    $service->save_settings();exit;
}
$GLOBALS['settings']['groups']=$service->normalize_groups(array($group));
$rows=$service->settings()['sailings'];$ids=array();
foreach($rows as $r){$ids[$r['from'].'/'.$r['class'].'/'.$r['time']]=$r['id'];}
if($mode==='--config-json'){$service->shortcode();echo json_encode($GLOBALS['config']);exit;}
if(in_array($mode,array('--search-json','--quote-json'),true)){$_POST=array('mode'=>$mode==='--search-json'?'search':'quote','payload'=>$argv[2]);$service->ajax();exit;}
$GLOBALS['wc']=(object)array('cart'=>new TestCart(),'customer'=>new TestCustomer());
$input=array('from'=>'Dumaguete','to'=>'Siquijor','date'=>'2099-01-02','return'=>'2099-01-03','counts'=>array('adult'=>3,'child'=>2),'outbound'=>$ids['Dumaguete/Tourist Class/08:00'],'inbound'=>$ids['Siquijor/Tourist Class/12:00']);
$GLOBALS['expected_price']=5490;
if($mode==='book-reverse'){$input['from']='Siquijor';$input['to']='Dumaguete';$input['return']='';$input['outbound']=$ids['Siquijor/Tourist Class/14:00'];$GLOBALS['expected_price']=2850;}
if($mode==='book-business'){$input['outbound']=$ids['Dumaguete/Business Class/10:00'];$input['inbound']=$ids['Siquijor/Business Class/14:00'];$GLOBALS['expected_price']=7650;}
$quote=$service->quote($input);
$person=array('category'=>'child','first'=>'Test','last'=>'Passenger','middle'=>'','phone'=>'+639123456789','nationality'=>'Canadian','sex'=>'Female','dob'=>'2018-01-01','email'=>'');
$input['passengers']=array($person);$input['consent']=true;$input['signature']=$quote['signature'];
if($mode==='book-changed'){ $input['signature']='old-price';$GLOBALS['expected_error']='Fares changed'; }
if($mode==='book-no-consent'){ $input['consent']=false;$GLOBALS['expected_error']='Accept the booking terms'; }
$_POST=array('mode'=>'book','payload'=>json_encode($input));
function wp_send_json_error($data){expect($GLOBALS['expected_error'] && strpos($data['message'],$GLOBALS['expected_error'])!==false,'expected booking rejection: '.$data['message']);expect(!WC()->cart->get_cart(),'invalid booking not added');echo 'PASS: '.$GLOBALS['mode']."\n";exit;}
function wp_send_json_success($data){
    if(in_array($GLOBALS['mode'],array('--search-json','--quote-json'),true)){echo json_encode($data);exit;}
    expect(!$GLOBALS['expected_error'],'invalid request must fail');
    expect($data['redirect']==='https://booking.test/checkout','checkout redirect');
    $cart=WC()->cart;$line=$cart->cart_contents['item'];
    expect($line['quantity']===1 && $line['data']->price===$GLOBALS['expected_price'],'one cart booking at server-calculated round-trip price');
    expect(count($line['hr_oceanjet']['passengers'])===1,'single supplied passenger saved');
    expect(WC()->customer->email==='checkout@example.com','optional empty email does not erase billing email');
    $GLOBALS['service']->check_cart();expect(empty($GLOBALS['notices']),'cart revalidation succeeds');
    $item=new TestItem();$GLOBALS['service']->order_item($item,'item',$line,null);
    expect(isset($item->meta['_hr_oceanjet']) && $item->meta['Passenger quantities']==='Adult: 3, Child: 2','private booking and public counts copied to order');
    expect(strpos($item->meta['Passenger follow-up'],'remaining passenger')!==false,'order flags staff follow-up');
    if($GLOBALS['mode']==='book-no-code'){
        expect($item->meta['_hr_oceanjet']['quote']['legs'][0]['code']==='','booking accepts blank trip code');
        expect(strpos($item->meta['Departure'],' |  | ')===false,'blank trip code leaves a clean order summary');
    }
    expect(!$GLOBALS['service']->guard_product(true,99,1),'direct product add blocked');
    echo "PASS: booking handler, checkout redirect, cart price/revalidation, order metadata and follow-up\n";exit;
}
$service->ajax();
