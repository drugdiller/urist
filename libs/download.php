<?php
/**
* Скачивание группы файлов параллельно
* @param array $urls       Массив урлов
* @param array $opts       Массив дополнительных значений
* @param string $callback  Имя функции, вызываемой для каждого значения в момент получения.
* @return array/false      Вовзращает массив где ключ - урл, значение - скачанные данные
*/
function download_multi($urls,$opts=array(),$callback=''){
   $defaults = array(
       'useragent' => 'Android-x86-1.6-r2 - Mozilla/5.0 (Linux; U; Android 1.6; en-us; eeepc Build/Donut) AppleWebKit/528.5+ (KHTML, like Gecko) Version/3.1.2 Mobile Safari/525.20.1',
       'timeout' => 10,
       'referer' => '',
       'cookie' => '',
       'post' => '',
       'maxparallel' => 10,
       'followlocation'=>true,
       'cookiefile' => false,
       'header'=>false
   );
   foreach($defaults as $k=>$v){
       if(!array_key_exists($k,$opts)){
           $opts[$k]=$v;
       }
   }
   if(!is_array($urls) || sizeof($urls)==0)return false;
   $urls = array_values($urls);
   $mh = curl_multi_init();
   $ret = array();
   $iter=0;
   do{
       $max = $opts['maxparallel'];
       if($max>sizeof($urls))$max = sizeof($urls);
       $chs = array();
       for($i=0;$i<$max;$i++){
           if($urls[$i+$iter]=='')continue;
           $chs[$i] = curl_init();
           curl_setopt($chs[$i], CURLOPT_USERAGENT, $opts['useragent']);
           curl_setopt($chs[$i], CURLOPT_REFERER, $opts['referer']);
           if($opts['cookie']!=''){
               curl_setopt($chs[$i], CURLOPT_COOKIE, $opts['cookie']);
           }
           if($opts['cookiefile']!==false){
               curl_setopt($chs[$i], CURLOPT_COOKIEFILE, $opts['cookiefile']);
               curl_setopt($chs[$i], CURLOPT_COOKIEJAR, $opts['cookiefile']);
           }
           if((is_array($opts['post']) && sizeof($opts['post'])>0) || $opts['post']!=''){
               curl_setopt($chs[$i], CURLOPT_POST, true);
               curl_setopt($chs[$i], CURLOPT_POSTFIELDS, $opts['post']);
           }
           if($opts['header']!==false){
               curl_setopt($chs[$i], CURLOPT_HTTPHEADER, $opts['header']);
           }
           curl_setopt($chs[$i], CURLOPT_RETURNTRANSFER, true);
           curl_setopt($chs[$i], CURLOPT_TIMEOUT, $opts['timeout']);
           if(!ini_get('safe_mode')){
              if($opts['followlocation']===true){
                   curl_setopt($chs[$i], CURLOPT_FOLLOWLOCATION, true);
               }
               else{
                   curl_setopt($chs[$i], CURLOPT_FOLLOWLOCATION, false);
               }
           }
           curl_setopt($chs[$i], CURLOPT_URL, $urls[$i+$iter]);
           curl_multi_add_handle($mh,$chs[$i]);
       }
       $active = null;
       //запускаем дескрипторы
       do{
           $mrc = curl_multi_exec($mh, $active);
       }
       while($mrc == CURLM_CALL_MULTI_PERFORM);

       while($active && $mrc == CURLM_OK) {
           if(curl_multi_select($mh)!= -1) {
               do {
                   $mrc=curl_multi_exec($mh, $active);
               }
               while($mrc == CURLM_CALL_MULTI_PERFORM);
           }
       }
       for($i=0;$i<$max;$i++){
           if($callback!='' && is_callable($callback)){
               call_user_func($callback,$urls[$i+$iter],curl_multi_getcontent($chs[$i]));
           }
           else{
               $ret[$urls[$i+$iter]]=curl_multi_getcontent($chs[$i]);
           }
           unset($urls[$i+$iter]);
       }
       foreach($chs as $ch){
           curl_multi_remove_handle($mh, $ch);
       }
       $iter+=$max;
   }while(sizeof($urls)>0);
   curl_multi_close($mh);
   return$ret;
}
/**
* Скачка урла
* @param string $url
* @param array(useragent,timeout,referer,cookie,post)
* @param string $saveto    Куда сохранить
* @return mixed
*/
function download($url, $opts=array(), $saveto=null){
   if(function_exists('curl_init')){
       $defaults = array(
           'useragent' => 'Android-x86-1.6-r2 - Mozilla/5.0 (Linux; U; Android 1.6; en-us; eeepc Build/Donut) AppleWebKit/528.5+ (KHTML, like Gecko) Version/3.1.2 Mobile Safari/525.20.1',
           'timeout' => 10,
           'referer' => '',
           'cookie' => '',
           'post' => '',
           'session_start'=>false,
           'ssl_verify'=>false,
           'followlocation'=>true,
           'cookiefile'=>false,
           'header'=>false
       );
       foreach($defaults as $k=>$v){
           if(!array_key_exists($k,$opts)){
               $opts[$k]=$v;
           }
       }
       $ch = curl_init($url);
       curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $opts['ssl_verify']);
       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $opts['ssl_verify']);
       if($opts['header']!==false){
           curl_setopt($ch, CURLOPT_HTTPHEADER, $opts['header']);
       }
       if($opts['cookiefile']!==false){
           curl_setopt($ch, CURLOPT_COOKIEFILE, $opts['cookiefile']);
           curl_setopt($ch, CURLOPT_COOKIEJAR, $opts['cookiefile']);
       }
       if($opts['session_start']===true){
           curl_setopt($ch, CURLOPT_COOKIESESSION, true);
       }
       curl_setopt($ch, CURLOPT_USERAGENT, $opts['useragent']);
       curl_setopt($ch, CURLOPT_REFERER, $opts['referer']);
       if($opts['cookie']!=''){
           curl_setopt($ch, CURLOPT_COOKIE, $opts['cookie']);
       }
       if((is_array($opts['post']) && sizeof($opts['post'])>0) || $opts['post']!=''){
           curl_setopt($ch, CURLOPT_POST, true);
           curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['post']);
       }
       if(!ini_get('safe_mode')){
           if($opts['followlocation']===true){
               curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
           }
           else{
               curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
           }
       }
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($ch, CURLOPT_TIMEOUT, $opts['timeout']);
       $data = curl_exec($ch);
       if($saveto===null){
           curl_close($ch);
           return $data;
       }
       if(is_writable(dirname($saveto))){
           $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
           curl_close($ch);
           if($code!=404){
               $fp = fopen($saveto, 'w');
               fwrite($fp, $data);
               fclose($fp);
               return true;
           }
           return false;
       }
       return false;
   }
   throw new Exception('No curl installed');
}
