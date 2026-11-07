<?php
/** Run only against the isolated local WordPress; provider responses are mocked. */
if (PHP_SAPI !== 'cli' || empty($argv[1])) exit("Usage: php ai-search-integration.php /path/to/local/wp-load.php [--keep-ui]\n");
define('WP_DISABLE_FATAL_ERROR_HANDLER',true);
require $argv[1];
if(wp_get_environment_type()!=='local' || DB_NAME!=='dedenik_local') exit("Local test DB required.\n");
$saved=[];$created=[];$checks=0;$calls=0;
$old_ip=$_SERVER['REMOTE_ADDR']??null;
$_SERVER['REMOTE_ADDR']='192.0.2.250';
$rate_key='dedenik_ai_rate_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR'],wp_salt('nonce'));
$old_rate=get_transient($rate_key);
delete_transient($rate_key);
foreach(['dedenik_ai_enabled','dedenik_ai_key','dedenik_ai_daily_limit','dedenik_ai_content_version','dedenik_ai_daily_'.gmdate('Ymd')] as $name){$saved[$name]=get_option($name,null);}
function integration_check($value,$message){$GLOBALS['checks']++;if(!$value)throw new RuntimeException($message);}
try {
    update_option('dedenik_ai_key','not-a-real-key-integration-only',false);
    update_option('dedenik_ai_enabled',true,false);
    update_option('dedenik_ai_daily_limit',50,false);
    delete_option('dedenik_ai_daily_'.gmdate('Ymd'));
    $word='aifind'.bin2hex(random_bytes(6));
    foreach(['publish','draft','private','password'] as $status){
        $created[]=wp_insert_post(['post_title'=>$word.' Kočky a psi','post_content'=>'Příběh '.$word.' soužití koček a psů.','post_status'=>$status==='password'?'publish':$status,'post_password'=>$status==='password'?'hidden':'']);
    }
    add_filter('pre_http_request',function($pre,$args,$url)use($word,&$calls,&$created){
        if($url!==dedenik_ai_endpoint())return $pre;
        integration_check($pre===false,'Staging exception did not open only the in-flight provider call');
        $calls++;
        $body=json_decode($args['body'],true);
        $data=json_decode($body['contents'][0]['parts'][0]['text'],true);
        if($calls===1){$out=['terms'=>[$word]];}
        else {
            integration_check(array_column($data['records'],'id')===[$created[0]],'Non-public content leaked into provider payload');
            $out=['ids'=>[$created[1],$created[2],$created[3],99999999,$created[0]]];
        }
        return ['response'=>['code'=>200],'body'=>wp_json_encode(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>wp_json_encode($out)]]]]]])];
    },PHP_INT_MAX,3);
    $query='Najdi '.$word;
    $result=dedenik_ai_search($query);
    integration_check($result===[$created[0]],'Only real public candidate may be returned');
    integration_check(dedenik_ai_search($query)===$result && $calls===2,'Repeated query must reuse persistent cache');
    global $wpdb;
    $used=(int)$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'dedenik_ai_daily_'.gmdate('Ymd')));
    integration_check($used===1,'Atomic DB daily counter must reserve once');
    update_option('dedenik_ai_daily_limit',1,false);
    integration_check(is_wp_error(dedenik_ai_search('jiný '.$word)) && $calls===2,'Real SQL quota must stop API calls');
    wp_update_post(['ID'=>$created[0],'post_status'=>'private']);
    integration_check(!dedenik_ai_public(get_post($created[0])),'Retracted result must no longer be visible');
    integration_check(is_wp_error(wp_remote_get('https://example.com')),'Other external hosts must remain blocked');
    ob_start();dedenik_ai_form();$form=ob_get_clean();
    integration_check(str_contains($form,'ai-search-form') && !str_contains($form,dedenik_ai_key()),'Public form must not expose API key');
    update_option('dedenik_ai_enabled',false,false);
    ob_start();dedenik_ai_form();$form=ob_get_clean();
    integration_check($form==='','Disabled form must stay hidden');
    echo "AI WordPress integration: $checks checks passed; $calls mocked calls, no Google traffic.\n";
} finally {
    foreach($created as $id)wp_delete_post($id,true);
    foreach($saved as $name=>$value){if($value===null)delete_option($name);else update_option($name,$value,false);}
    if($old_rate===false)delete_transient($rate_key);else set_transient($rate_key,$old_rate,MINUTE_IN_SECONDS);
    if($old_ip===null)unset($_SERVER['REMOTE_ADDR']);else $_SERVER['REMOTE_ADDR']=$old_ip;
    if(in_array('--keep-ui',$argv,true)){
        // Deliberately invalid local-only key permits browser testing of failure/fallback UI.
        update_option('dedenik_ai_key','local-ui-invalid-key-never-used',false);
        update_option('dedenik_ai_enabled',true,false);
        echo "Local-only UI preview enabled with a deliberately invalid key.\n";
    }
}
