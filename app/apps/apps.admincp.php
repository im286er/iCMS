<?php
/**
* iCMS - i Content Management System
* Copyright (c) 2007-2017 iCMSdev.com. All rights reserved.
*
* @author icmsdev <master@icmsdev.com>
* @site https://www.icmsdev.com
* @licence https://www.icmsdev.com/LICENSE.html
*/
defined('iPHP') OR exit('What are you doing?');

class appsAdmincp{

    public function __construct() {
      $this->appid = iCMS_APP_APPS;
    	$this->id = (int)$_GET['id'];

      iHttp::$CURLOPT_TIMEOUT        = 60;
      iHttp::$CURLOPT_CONNECTTIMEOUT = 10;
    }
    public function do_iCMS(){
      $this->do_manage();
    }
    public function do_add(){
        $this->id && $rs = apps::get($this->id);
        if(empty($rs)){
          $rs['type']   = "2";
          $rs['status'] = "1";
          $rs['create'] = "1";
          if($rs['type']=="2"){
            $rs['apptype'] = "2";
            $rs['config']['iFormer'] = '1';
            $rs['config']['menu']    = 'main';
            $base_fields  = apps_mod::base_fields_array();
            $rs['fields'] = get_php_file(iPHP_APP_DIR.'/apps/json/fields.php');
            $rs['fields'] = json_decode($rs['fields'],true);
            $rs['menu']   = get_php_file(iPHP_APP_DIR.'/apps/json/menu.php');
            $rs['menu']   = json_decode($rs['menu'],true);
          }
        }else{
          if($rs['apptype']=="2"){
            $rs['config']['iFormer'] = '1';
          }
        }

        $rs['config']['template'] = apps_mod::template($rs);
        if(empty($rs['config']['iurl'])){
          $rs['config']['iurl'] = apps_mod::iurl($rs);
        }

        include admincp::view("apps.add");
    }

    public function do_save(){
        @set_time_limit(0);

        $id      = (int)$_POST['_id'];
        $name    = iSecurity::escapeStr($_POST['_name']);
        $title   = iSecurity::escapeStr($_POST['_title']);
        $app     = iSecurity::escapeStr($_POST['_app']);
        $apptype = (int)$_POST['apptype'];
        $type    = (int)$_POST['type'];
        $status  = (int)$_POST['status'];
        // $create  = (int)$_POST['create']?true:false;
        $create  = true;

        $menu = json_decode(stripcslashes($_POST['menu']));
        $menu = addslashes(cnjson_encode($menu));

        $name OR iUI::alert('应用名称不能为空!');
        empty($app) && $app = iPinyin::get($name);
        empty($title) && $title = $name;

        $table_array  = $_POST['table'];
        if($table_array){
          $table_array  = array_filter($table_array);
          $table  = addslashes(cnjson_encode($table_array));
        }

        $config_array = $_POST['config'];
        if($config_array['template']){
          $config_array['template'] = explode("\n", $config_array['template']);
          $config_array['template'] = array_map('trim', $config_array['template']);
        }
        if($config_array['iurl']){
          $config_array['iurl'] = json_decode(stripcslashes($config_array['iurl']),true);
        }
        $config_array = array_filter($config_array);
        $config = addslashes(cnjson_encode($config_array));

        $fields = '';
        $fieldata = $_POST['fields'];
        if(is_array($fieldata)){
          $field_array = array();
          foreach ($fieldata as $key => $value) {
            $output = array();
            parse_str($value,$output);
            if(isset($output['UI:BR'])){
              $field_array[$key] = 'UI:BR';
            }else{
              $output['label'] OR iUI::alert('发现自定义字段中空字段名称!');
              $output['comment'] = $output['label'].($output['comment']?':'.$output['comment']:'');
              $fname = $output['name'];
              $fname OR iUI::alert('发现自定义字段中有空字段名!');
              $field_array[$fname] = $value;
              if($output['field']=="MEDIUMTEXT"){
                $addons_fieldata[$key] = $value;
                unset($fieldata[$key]);//从基本表移除
              }
            }
          }
          //字段数据存入数据库
          $fields = addslashes(cnjson_encode($field_array));
        }

        $addtime = time();
        $array   = compact(array('app','name','title','menu','table','config','fields','addtime','apptype','type','status'));
        // $array['menu'] = str_replace(array("\r","\n"),'',$array['menu']);

        if(empty($id)) {
            iDB::value("SELECT `id` FROM `#iCMS@__apps` where `app` ='$app'") && iUI::alert('该应用已经存在!');
            // iDB::$print_sql = true;
            if($type=='3'){
              $array['fields'] = '';
              $msg = "应用信息添加完成!";
            }else if($type=='2'){
              if($create){
                iDB::check_table($array['app']) && iUI::alert('['.$array['app'].']数据表已经存在!');
              }
              if($addons_fieldata){
                $addons_name = apps_mod::data_table_name($array['app']);
                if($create){
                  iDB::check_table($addons_name) && iUI::alert('['.$addons_name.']附加表已经存在!');
                }
              }

              //创建基本表
              $tb = apps_db::create_table(
                $array['app'],
                apps_mod::get_field_array($fieldata),//获取字段数组
                apps_mod::base_fields_index(),//索引
                $create
              );
              array_push ($tb,null,$array['name']);
              $table_array = array();
              $table_array[$array['app']]= $tb;//记录基本表名

              //有MEDIUMTEXT类型字段就创建xxx_data附加表
              if($addons_fieldata){
                $union_id = apps_mod::data_union_key($array['app']);//关联基本表id
                $addons_base_fields = apps_mod::data_base_fields($array['app']);//xxx_data附加表的基础字段
                $addons_fieldata = $addons_base_fields+$addons_fieldata;
                $table_array += apps_mod::data_create_table($addons_fieldata,$addons_name,$union_id,$create);
                // //添加到字段数据里
                // $field_array = array_merge($field_array,$addons_base_fields);
                // $array['fields'] = addslashes(cnjson_encode($field_array));
              }
              $table_array+=apps_meta::table_array($app,true);

              $array['table']  = $table_array;
              $array['config'] = $config_array;

              $config_array['template'] = apps_mod::template($array,'array');
              $config_array['iurl']   = apps_mod::iurl($array);

              $array['table'] = addslashes(cnjson_encode($table_array));
              $array['config'] = addslashes(cnjson_encode($config_array));
              $msg = "应用创建完成!";
            }

            $id = iDB::insert('apps',$array);
            // if(stripos($array['menu'], '{app}') !== false){
            //   $_name = $array['title']?$array['title']:$array['name'];
            //   $menu = str_replace(
            //       array('{appid}','{app}','{name}','{sort}'),
            //       array($id,$array['app'],$_name,$id*1000),
            //       $array['menu']
            //   );
            //   iDB::update('apps', array('menu'=>$menu), array('id'=>$id));
            // }
        }else {
            iDB::value("SELECT `id` FROM `#iCMS@__apps` where `app` ='$app' AND `id` !='$id'") && iUI::alert('该应用已经存在!');
            $_fields     = iDB::value("SELECT `fields` FROM `#iCMS@__apps` where `id` ='$id'");//json
            $_json_field = apps_mod::json_field($_fields);//旧数据
            $json_field  = apps_mod::json_field($fields); //新数据
            /**
             * 找出字段数据中的 MEDIUMTEXT类型字段
             * PS:函数内会unset(json_field[key]) 所以要在 基本表make_alter_sql前执行
             */
            $_addons_json_field = apps_mod::find_MEDIUMTEXT($_json_field);
            $addons_json_field = apps_mod::find_MEDIUMTEXT($json_field);

            // print_r($_addons_json_field);
            // print_r($addons_json_field);

            //基本表 新旧数据计算交差集 origin 为旧字段名
            $sql_array = apps_db::make_alter_sql($json_field,$_json_field,$_POST['origin']);
            $sql_array && apps_db::alter_table($array['app'],$sql_array);

            //MEDIUMTEXT类型字段 新旧数据计算交差集 origin 为旧字段名
            $addons_sql_array = apps_db::make_alter_sql($addons_json_field,$_addons_json_field,$_POST['origin']);

            $addons_name = apps_mod::data_table_name($array['app']);
            //存在附加表数据
            if($addons_fieldata){
              if($addons_sql_array){
                //附加表名
                //检测附加表是否存在
                if($table_array[$addons_name] && iDB::check_table($addons_name)){
                  //表存在执行 alter
                  apps_db::alter_table($addons_name,$addons_sql_array);
                }else{
                  // 不存在 创建
                  if($addons_fieldata){
                    iDB::check_table($addons_name) && iUI::alert('['.$addons_name.']附加表已经存在!');
                    //有MEDIUMTEXT类型字段创建xxx_data附加表
                    $union_id = apps_mod::data_union_key($array['app']);
                    $addons_base_fields = apps_mod::data_base_fields($array['app']);//xxx_data附加表的基础字段
                    $addons_fieldata = $addons_base_fields+$addons_fieldata;
                    $table_array += apps_mod::data_create_table($addons_fieldata,$addons_name,$union_id);
                    $array['table'] = addslashes(cnjson_encode($table_array));
                    // //添加到字段数据里
                    // $field_array = array_merge($field_array,$addons_base_fields);
                    // $array['fields'] = addslashes(cnjson_encode($field_array));
                  }
                }
              }
            }else{
              if($apptype=="2"){ //只删除自定义应用的表
                //不存在附加表数据 直接删除附加表 返回 table的json值 $table_array为引用参数
                apps_mod::drop_table($addons_fieldata,$table_array,$addons_name);
                $array['table'] = addslashes(cnjson_encode($table_array));
              }else{
                if($table_array){
                  $data_tables = next($table_array);
                  $union_id = apps_mod::data_union_key($array['app']);
                  //判断是否自动生成的表
                  if(is_array($data_tables) &&
                    in_array(apps_mod::DATA_PRIMARY_KEY ,$data_tables) &&
                    in_array($union_id ,$data_tables))
                  {
                    apps_mod::drop_table($addons_fieldata,$table_array,$addons_name);
                    $array['table'] = addslashes(cnjson_encode($table_array));
                  }else{
                    apps_db::alter_table($addons_name,$addons_sql_array);
                  }
                }
              }
            }

            iDB::update('apps', $array, array('id'=>$id));
            $msg = "应用编辑完成!";
        }
        apps::cache();
        menu::cache();
        iUI::success($msg,'url:'.APP_URI);
    }

    public function do_update(){
        if($this->id){
            $args = iSQL::update_args($_GET['_args']);
            $args && iDB::update("apps",$args,array('id'=>$this->id));
            apps::cache();
            iUI::success('操作成功!','js:1');
        }
    }
    public function do_manage(){
      // if($_GET['keywords']) {
		    // $sql=" WHERE `keyword` REGEXP '{$_GET['keywords']}'";
      // }
      $orderby    =$_GET['orderby']?$_GET['orderby']:"id DESC";
      $maxperpage = $_GET['perpage']>0?(int)$_GET['perpage']:50;
      $total      = iCMS::page_total_cache("SELECT count(*) FROM `#iCMS@__apps` {$sql}","G");
      iUI::pagenav($total,$maxperpage,"个应用");
      $rs     = iDB::all("SELECT * FROM `#iCMS@__apps` {$sql} order by {$orderby} LIMIT ".iUI::$offset." , {$maxperpage}");
      $_count = count($rs);

      //分组
      foreach ($rs as $key => $value) {
        $apps_type_group[$value['type']][$key] = $value;
      }
    	include admincp::view("apps.manage");
    }

    public function do_batch(){
        $idArray = (array)$_POST['id'];
        $idArray OR iUI::alert("请选择要操作的应用");
        $ids     = implode(',',$idArray);
        $batch   = $_POST['batch'];
      	switch($batch){
  		  }

	  }
    public function do_cache(){
      apps::cache();
      iUI::success('更新完成');
    }
    /**
     * [卸载应用]
     * @return [type] [description]
     */
    public function do_uninstall($id = null,$dialog=true){
      $id===null && $id=$this->id;
      $app = apps::get($id);

      if($app['type'] && $app['apptype']){
        apps::uninstall($app);
        apps::cache();
        menu::cache();
        apps_store::del_config($id);
        $msg = '应用已经删除';
      }else{
        $msg = '应用已被禁止删除';
      }
      empty($app) && apps_store::del_config($id);

      $dialog && iUI::alert($msg,'js:1');
    }
    /**
     * [本地安装应用]
     * @return [type] [description]
     */
    public function do_local_app(){
      if(strpos($_POST['zipfile'], '..') !== false){
        iUI::alert('What the fuck!!');
      }
      apps_store::$zip_file = trim($_POST['zipfile'],"\0\n\r\t\x0B");
      apps_store::$msg_mode = 'alert';
      apps_store::install_app();
      iUI::success('应用安装完成','js:1');
    }
    /**
     * [打包下载应用]
     * @return [type] [description]
     */
    public function do_pack(){
      $rs = iDB::row("SELECT * FROM `#iCMS@__apps` where `id`='".$this->id."'",ARRAY_A);
      $appdir = iPHP_APP_DIR.'/'.$rs['app'];
      unset($rs['id']);
      $data     = base64_encode(serialize($rs));
      $config   = json_decode($rs['config'],true);
      $filename = 'iCMS.APP.'.$rs['app'].'-'.$config['version'];
      if(iFS::ex($appdir)) { //本地应用
        $remove_path = iPHP_APP_DIR;
      }else{//自定义应用
        $appdir = iPHP_APP_CACHE.'/pack.app/'.$rs['app'];
        $remove_path = iPHP_APP_CACHE.'/pack.app/';
        iFS::mkdir($appdir);
      }
      //应用数据
      $app_data_file = $appdir.'/iCMS.APP.DATA.php';
      put_php_file($app_data_file, $data);

      //数据库结构
      if($rs['table']){
        $app_table_file = $appdir.'/iCMS.APP.TABLE.php';

        put_php_file(
          $app_table_file,
          apps_db::create_table_sql($rs['table'])
        );
      }

      $zipfile = apps::get_zip($filename,$appdir,$remove_path);
      filesApp::attachment($zipfile);
      iFS::rm($zipfile);
      iFS::rm($app_data_file);
      $app_table_file && iFS::rm($app_table_file);

      if($remove_path != iPHP_APP_DIR){
        iFS::rmdir($remove_path);
      }
    }
    /**
     * [钩子管理]
     * @return [type] [description]
     */
    public function do_hooks(){
        configAdmincp::app($this->appid,'hooks');
    }
    /**
     * [保存钩子]
     * @return [type] [description]
     */
    public function do_hooks_save(){
        $hooks = array();
        foreach ((array)$_POST['hooks']['method'] as $key => $method) {
          $h_app   = $_POST['hooks']['app'][$key];
          $h_field = $_POST['hooks']['field'][$key];
          if($method && $h_app && $h_field){
            $hooks[$h_app][$h_field][]= explode("::", $method);
          }
        }
        $_POST['config'] = $hooks;
        configAdmincp::save($this->appid,'hooks');
    }
    /**
     * [模板市场]
     * @return [type] [description]
     */
    public function do_template(){
      $title      = '模板';
      $storeArray = configAdmincp::get('999999','store');
      $dataArray  = apps_store::get_data('template');
      include admincp::view("apps.store");
    }
    public function do_template_update(){
      $this->do_store_update('template','模板');
    }
    public function do_template_uninstall(){
      $sid   = (int)$_GET['sid'];
      $store = apps_store::get_config($sid);
      $dir   = iView::check_dir($store['app']);
      if($dir){
        foreach (iDevice::$config as $key => $value) {
          if($value['tpl']==$store['app']){
            iUI::alert('当前模板【'.$key.'】设备正在使用中,如果删除将出现错误','js:1',10);
          }
        }
        foreach ((array)iDevice::$config['device'] as $key => $value) {
          if($value['tpl']==$store['app']){
            iUI::alert('当前模板【'.$value['name'].'】设备正在使用中,如果删除将出现错误','js:1',10);
          }
        }
        iFS::rmdir($dir);
      }
      $sid && apps_store::config('delete',$sid);
      iUI::success('模板已删除','js:1');
    }
    /**
     * [从模板市场安装模板]
     * @return [type] [description]
     */
    public function do_template_install(){
      $this->do_store_install('template','模板');
    }
    /**
     * [付费安装模板]
     * @return [type] [description]
     */
    public function do_template_premium_install(){
      $this->do_store_premium_install('template');
    }
    /**
     * [应用市场]
     * @return [type] [description]
     */
    public function do_store($name=null){
      $title      = '应用';
      $storeArray = configAdmincp::get('999999','store');
      $dataArray  = apps_store::get_data();
      include admincp::view("apps.store");
    }
    public function do_store_uninstall(){
      $this->do_uninstall();
    }
    public function do_store_update($type='app',$title='应用'){
      $sid   = (int)$_GET['sid'];
      $conf  = apps_store::get_config($sid);

      $conf['authkey'] && $_GET['authkey'] = $conf['authkey'];
      $conf['transaction_id'] && $_GET['transaction_id'] = $conf['transaction_id'];

      $store = apps_store::git('app_update_zip',$sid);

      if(empty($store)){
        iUI::alert('请求出错','js:1',10);
      }
      if(empty($store['code'])){
        iUI::alert($store['msg'],'js:1',10);
      }

      iCache::set('store/'.$sid,$store,3600);

      apps_store::$is_update = true;
      apps_store::$sid       = $sid;
      apps_store::$app_id    = $this->id;
      apps_store::$uptime    = $conf['git_time'];

      apps_store::setup($store['zip_url'],$store['app'],$store['name']);
    }
    /**
     * [从应用市场安装应用]
     * @return [type] [description]
     */
    public function do_store_install($type='app',$title='应用',$update=false){
      $sid   = (int)$_GET['sid'];
      $store = apps_store::get($sid);
      empty($store) && iUI::alert('请求出错','js:1',1000000);

      if($store['iCMS_VERSION'] && $store['iCMS_RELEASE']){
        if(version_compare($store['iCMS_VERSION'],iCMS_VERSION,'>') && $store['iCMS_RELEASE']>iCMS_RELEASE){
          iUI::alert('该应用要求iCMS V'.$store['iCMS_VERSION'].'['.$store['iCMS_RELEASE'].']以上版本','js:1',1000000);
        }
      }

      if($store['iCMS_GIT_TIME'] && $store['iCMS_GIT_TIME']>GIT_TIME){
        iUI::alert('该应用要求iCMS版本更新到<br />[git:'.get_date($store['iCMS_GIT_TIME'],'Y-m-d H:i').']以上版本','js:1',1000000);
      }

      if($type=='app'){
          $appid = iDB::value("
            SELECT `id` FROM `#iCMS@__apps`
            WHERE `app` ='".$store['app']."'
          ");
          if($appid){
            apps_store::config(array(
                'appid'    => $appid,
                'app'      => $store['app'],
                'git_sha'  => $store['git_sha'],
                'git_time' => $store['git_time'],
                'version'  => $store['version'],
                'authkey'  => $store['authkey'],
            ),$sid);
            iUI::alert($store['name'].'['.$store['app'].'] 应用已存在','js:1',1000000);
          }

          if($store['data']['tables']){
            foreach ($store['data']['tables'] as $table) {
                iDB::check_table($table) && iUI::alert('['.$table.']数据表已经存在!','js:1',1000000);
            }
          }

          $path = iPHP_APP_DIR.'/'.$store['app'];
          if(iFS::checkDir($path)){
            $ptext = str_replace(iPATH,'iPHP://',$path);
            iUI::alert(
              $store['name'].'['.$store['app'].'] <br />应用['.$ptext.']目录已存在,<br />程序无法继续安装',
              'js:1',1000000
            );
          }
      }

      if($type=='template'){
        $path = iPHP_TPL_DIR.'/'.$store['app'];
        if(iFS::checkDir($path)){
          apps_store::config(array(
              'appid'    => null,
              'app'      => $store['app'],
              'git_sha'  => $store['git_sha'],
              'git_time' => $store['git_time'],
              'version'  => $store['version'],
              'authkey'  => $store['authkey'],
          ),$sid);
          $ptext = str_replace(iPATH,'iPHP://',$path);
          iUI::alert(
            $store['name'].'['.$store['app'].'] <br /> 模板['.$ptext.']目录已存在,<br />程序无法继续安装',
            'js:1',1000000
          );
        }
      }

      iCache::set('store/'.$sid,$store,3600);
      apps_store::$sid = $sid;

      if($store){
        if($store['premium']){
          apps_store::premium_dialog($sid,$store,$title);
        }else{
          apps_store::setup($store['url'],$store['app'],$store['name'],null,$type);
        }
      }
    }
    /**
     * [付费安装]
     * @return [type] [description]
     */
    public function do_store_premium_install($type='app'){
      $sapp    = $_GET['sapp'];
      $name    = $_GET['name'];
      $version = $_GET['version'];

      $url     = $_GET['url'];
      $key     = $_GET['key'];
      $sid     = $_GET['sid'];
      $tid     = $_GET['transaction_id'];
      $query   = compact(array('sid','key','tid'));
      $zipurl  = $url.'?'.http_build_query($query);

      apps_store::$sid = $sid;
      apps_store::setup($zipurl,$sapp,$name,$key.'.zip',$type);
    }
    public function do_pay_notify(){
      echo apps_store::pay_notify($_GET);
    }
    public static function _count(){
      return iDB::value("SELECT count(*) FROM `#iCMS@__apps`");
    }
}
