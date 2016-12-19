<?php
namespace app\index\controller;
use think\Request;
use think\Controller;
use Org\Util\ExcelToArray;
use think\Db;
class Index extends Controller
{
    public function index()
    {
        return $this->fetch();
    }


    public function up(Request $request)
    {
        //获取表单上传文件

        $files = $request->file('file');
        //dump($request->param());
        // dump(empty($file));
        // if (empty($file)) {
        //     $this->error('请选择上传文件');
        // }
        // 移动到框架应用根目录/public/uploads/ 目录下
        foreach ($files as $file){
            $info = $file->rule('uniqid')->validate(['ext' => 'xls'])->move(ROOT_PATH . 'public' . DS . 'uploads','');
            if ($info) {
                $item[] = $info->getRealPath();
            } else {
                // 上传失败获取错误信息
                $this->error($file->getError());
                return $this->redirect('/public/index/index/update');
            }
        }
        $config_file= $this -> loadfile();
        $this->success('文件上传成功'.implode('<br/>',$item),'/public/index/index/index');
        
    }

    public function update(Request $request)
    {

        $file1 = ROOT_PATH."public/uploads/lottery.csv";
        $handle1=fopen($file1,"r");
        $fstat1 = fstat($handle1);
        $edittime1=date("Y-m-d h:i:s",$fstat1["mtime"]);
        $file2 = ROOT_PATH."public/uploads/lottery_gacha.csv";
        $handle2=fopen($file1,"r");
        $fstat2 = fstat($handle1);
        $edittime2=date("Y-m-d h:i:s",$fstat1["mtime"]);

        $this->assign('edittime1', $edittime1);
        $this->assign('edittime2', $edittime2);
        return $this->fetch();

    }

    public function loadfile()
    {   
        $filename_lottery=ROOT_PATH . 'public' . DS . 'uploads/lottery.xls';
        $filename_lottery_gacha=ROOT_PATH . 'public' . DS . 'uploads/lottery_gacha.xls';
        $result_lottery =import_excel($filename_lottery);
        $result_lottery_gacha =import_excel($filename_lottery_gacha);
        $this->refresh_lottery('lottery',$result_lottery);
        $this->refresh_lottery('lottery_gacha',$result_lottery_gacha);
    }

    public function test(Request $request){
        $config=$request->param();
        if (empty($config['configid']) || empty($config['test_type']) || empty($config['testnum'])){
            $this-> error('配置选择错误');
        }else{
            switch ($config['test_type']) {
                case '1':
                    $result=array();
                    foreach(range(1,$config['testnum']) as $i){
                        $monijieguo=$this->moni($config['configid']);
                        $result[]=$monijieguo;
                    }
                    $final_result=array(0,0,array());
                    foreach($result as $result_once){
                        $final_result[0]=$final_result[0]+$result_once[0];
                        $final_result[1]=$final_result[1]+$result_once[1];
                        foreach ($result_once[2] as $itemid => $itemcnt){
                            if (isset($final_result[2][$itemid])){
                                $final_result[2][$itemid]=$final_result[2][$itemid]+$itemcnt;
                            }else{
                                $final_result[2][$itemid]=$itemcnt;
                            }
                        }
                    }
                    break;
                
                default:
                    $this->error('未知错误');
                    break;
            }
        }
        $items='';
        foreach($final_result[2] as $key => $value){
            $items.=$key."=>".$value."<br \>";
        }
        $this->assign('test_num',$config['testnum']);
        $this->assign('total_cost',$final_result[0]);
        $this->assign('total_step',$final_result[1]);
        $this->assign('avg_cost',($final_result[0]/$config['testnum']));
        $this->assign('avg_step',($final_result[1]/$config['testnum']));
        $this->assign('items',$items);
        return $this->fetch();
    }

    public function moni($lottery_id=1){
        $lottery_map_num=Db::query("select distinct mapId from lottery where id= ".$lottery_id);
        $lottery_map_id=array_rand($lottery_map_num);
        $lottery_map_id=$lottery_map_num[$lottery_map_id]['mapId'];
        $step=0;
        $cost=0; 
        $item=array();
        $baoji=1;
        $count=0;
        while(true){
            $count=$count+1;
            $step_once=rand(1,6);
            if ($step+$step_once>=25){
                $step=25;
            }else{
                $step=$step+$step_once;
            }
            $reward = Db::query("select * from lottery where id=" . $lottery_id ." and mapid=".$lottery_map_id." and step=".$step);
            $type=$reward[0]['type'];
            $value=$reward[0]['value'];
            $cnt=$reward[0]['cnt'];
            switch ($type) {
                case '0':
                    if(isset($item[$value])){
                        $item[$value]=$item[$value]+$cnt*$baoji;
                    }else{
                        $item[$value]=$cnt*$baoji;
                    }
                    $baoji=1;
                    break;
                case '1':
                    if(isset($item[$value])){
                        $item[$value]=$item[$value]+$cnt*$baoji;
                    }else{
                        $item[$value]=$cnt*$baoji;
                    }
                    $baoji=1;
                    break;
                case '2':
                    $pro_sum=Db::query("select sum(pro) from lottery_gacha where id =".$cnt." and boxid=".$value);
                    $gacha_config=Db::query("select * from lottery_gacha where id =".$cnt." and boxid=".$value);
                    //dump($gacha_config);
                    $pro_rand=rand(1,$pro_sum[0]['sum(pro)']);
                    $pro_temp=0;
                    foreach ($gacha_config as $gacha_once){
                        if ($pro_rand <= $gacha_once['pro']+$pro_temp and $pro_rand >$pro_temp){
                            if(isset($item[$gacha_once['itemid']])){
                                $item[$gacha_once['itemid']]=$item[$gacha_once['itemid']]+$gacha_once['itemcnt']*$baoji;
                            }else{
                                $item[$gacha_once['itemid']]=$gacha_once['itemcnt']*$baoji;
                            }
                            $baoji=1;
                            break;
                        }else{
                            $pro_temp=$pro_temp+$gacha_once['pro'];
                        }
                    }
                    $baoji=1;
                    break;
                case '3':
                    $baoji=$baoji*$value;
                default:
                    break;
            }
            $cost=$cost+$reward[0]['cost'];
            if ($step==25){
                
                break;
            }
            
        }
        return array($cost,$count,$item);

        
    }

    public function refresh_lottery($tablename,$array){
        $sql=Db::execute("delete from ".$tablename);
        foreach($array as $detail){
            if ($tablename=="lottery"){
                $sql=Db::execute("insert into ".$tablename." (id,mapId,cost,step,type,value,cnt) values (?,?,?,?,?,?,?)",$detail);
            }else{
                $sql=Db::execute("insert into ".$tablename." (id,boxid,minhq,maxhq,itemid,itemcnt,pro) values (?,?,?,?,?,?,?)",$detail);
            }
        }
    }


}

