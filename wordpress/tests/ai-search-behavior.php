<?php
/** Offline provider tests: never contacts Google or uses a real API key. */
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS',60); define('HOUR_IN_SECONDS',3600);
$options=[];$transients=[];$calls=[];$posts=[];$responses=[];$hooks=[];$queries=[];
class WP_Error { public $code; public $message; function __construct($c,$m){$this->code=$c;$this->message=$m;} function get_error_message(){return $this->message;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,$a=false){$GLOBALS['options'][$k]=$v;}
function add_option($k,$v,$x='',$a=false){if(!isset($GLOBALS['options'][$k]))$GLOBALS['options'][$k]=$v;}
function add_action($h,$cb){$GLOBALS['hooks'][$h][]=$cb;}
function add_filter($h,$cb,$p=10,$a=1){$GLOBALS['hooks'][$h][]=$cb;}
function wp_json_encode($v){return json_encode($v);}
function wp_remote_post($url,$args){$GLOBALS['calls'][]=[$url,$args];return array_shift($GLOBALS['responses'])??new WP_Error('offline','Offline');}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_strip_all_tags($v){return strip_tags($v);}
function strip_shortcodes($v){return preg_replace('/\[.*?\]/','',$v);}
function wp_html_excerpt($v,$n,$more){return mb_substr($v,0,$n);}
function sanitize_text_field($v){return is_scalar($v)?strip_tags($v):'';}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=$v;}
function wp_salt($scheme){return 'test-salt';}
function wp_hash($v){return hash('sha256',$v);}
function is_post_publicly_viewable($p){return $p->post_status==='publish';}
class WP_Query {
    public $posts;
    function __construct($args){
        $GLOBALS['queries'][]=$args;
        $this->posts=array_values(array_filter($GLOBALS['posts'],function($p)use($args){
            return $p->post_status===$args['post_status'] && $p->post_password==='' && in_array($p->post_type,$args['post_type'],true);
        }));
    }
}
class FakeDB {
    public $options='wp_options'; public $count=0; public $prepared='';
    function prepare($sql,...$args){$this->prepared=$sql;return $args;}
    function query($args){if($this->count >= $args[1])return 0;$this->count++;return 1;}
}
$wpdb=new FakeDB;
require dirname(__DIR__, 2) . '/wp-content/themes/dedenik/inc/ai-search.php';
$checks=0;
function ai_check($v,$m){$GLOBALS['checks']++;if(!$v)throw new RuntimeException($m);}
function answer($value,$finish='STOP'){
    return ['response'=>['code'=>200],'body'=>json_encode(['candidates'=>[['finishReason'=>$finish,'content'=>['parts'=>[['text'=>json_encode($value)]]]]]])];
}
function fixture($id,$status='publish',$password='',$type='post'){
    return (object)['ID'=>$id,'post_type'=>$type,'post_status'=>$status,'post_password'=>$password,'post_title'=>'Kočky a psi','post_date'=>'2026-04-14 10:00:00','post_content'=>'Veřejný příběh o soužití koček a psů.'];
}
ai_check(is_wp_error(dedenik_ai_search('příběh o kočkách')) && !$calls,'Disabled search must make no paid call');
$options=['dedenik_ai_enabled'=>true,'dedenik_ai_key'=>'fake-key-for-tests-only','dedenik_ai_daily_limit'=>2];
ai_check(is_wp_error(dedenik_ai_search('ab')) && !$calls,'Invalid query must make no paid call');
$posts=[fixture(10),fixture(20,'draft'),fixture(30,'private'),fixture(40,'publish','secret'),fixture(50,'publish','','attachment')];
$responses=[answer(['terms'=>['kočk','pes']]),answer(['ids'=>[999,20,30,40,50,10,10,'10']])];
$ids=dedenik_ai_search('příběhy o soužití');
ai_check($ids===[10],'Model IDs must be restricted to public candidate IDs and deduplicated');
ai_check(count($calls)===2,'A new matching search uses exactly two bounded calls');
$body=json_decode($calls[1][1]['body'],true);
$sent=json_decode($body['contents'][0]['parts'][0]['text'],true);
ai_check(array_column($sent['records'],'id')===[10],'Private, draft, password and attachment data must not leave WordPress');
ai_check(!isset($body['tools']),'No web search or executable model tools');
ai_check($body['generationConfig']['maxOutputTokens']===1024 && $body['generationConfig']['thinkingConfig']['thinkingLevel']==='MINIMAL','Bounded output and documented REST thinking enum');
ai_check($calls[0][0]===dedenik_ai_endpoint() && $calls[0][1]['sslverify'] && $calls[0][1]['redirection']===0,'Fixed provider with verified TLS and no redirect');
ai_check(!str_contains($calls[0][0],'fake-key'),'API key must not be placed in URL');
ai_check(count(dedenik_ai_search('příběhy o soužití'))===1 && count($calls)===2 && $wpdb->count===1,'Cached search must not consume API or daily quota');
ai_check(str_contains($wpdb->prepared,'CAST(option_value AS UNSIGNED) < %d'),'Site quota must use conditional atomic SQL');
foreach($queries as $query){ai_check($query['post_status']==='publish' && $query['has_password']===false,'Retrieval must constrain visibility');}
$responses=[['response'=>['code'=>429],'body'=>'secret upstream details']];
$failure=dedenik_ai_search('jiný dotaz do archivu');
ai_check(is_wp_error($failure) && !str_contains($failure->message,'secret'),'Provider errors must not expose upstream details');
ai_check(is_wp_error(dedenik_ai_search('třetí nový dotaz')) && count($calls)===3,'Exhausted daily quota must prevent provider calls');
$options['dedenik_ai_daily_limit']=10;
ai_check(is_wp_error(dedenik_ai_search('čtvrtý nový dotaz')) && count($calls)===3,'Client rate limit must prevent provider calls');
$responses=[answer(['terms'=>[]],'MAX_TOKENS')];
ai_check(is_wp_error(dedenik_ai_json('test',[],[])),'Truncated model JSON must be rejected');
ai_check(!$GLOBALS['dedenik_ai_inflight'],'In-flight permission must close after provider request');
$filter=$hooks['pre_http_request'][0];$blocked=new WP_Error('staging','blocked');
$GLOBALS['dedenik_ai_inflight']=true;
ai_check($filter($blocked,['headers'=>['x-goog-api-key'=>dedenik_ai_key()]],'https://example.com')===$blocked,'Staging must continue blocking other hosts');
ai_check($filter($blocked,['headers'=>['x-goog-api-key'=>'wrong']],dedenik_ai_endpoint())===$blocked,'Staging exception must require this request key');
ai_check($filter($blocked,['headers'=>['x-goog-api-key'=>dedenik_ai_key()]],dedenik_ai_endpoint())===false,'Only explicit module request may pass staging isolation');
$GLOBALS['dedenik_ai_inflight']=false;
ai_check($filter($blocked,['headers'=>['x-goog-api-key'=>dedenik_ai_key()]],dedenik_ai_endpoint())===$blocked,'Google must remain blocked outside module request');
ai_check(dedenik_ai_valid_key('AQ.'.str_repeat('a',45)), 'Gemini auth keys with AQ. prefix are accepted');
ai_check(dedenik_ai_valid_key('AIza'.str_repeat('b',35)), 'Legacy key format remains accepted');
ai_check(!dedenik_ai_valid_key("AQ.".str_repeat('a',45)."\n") && !dedenik_ai_valid_key('AQ.invalid.key'), 'Reject whitespace and unexpected punctuation in keys');
echo "AI search: $checks checks passed (mocked Google, no external calls).\n";
