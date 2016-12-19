<?php 
namespace app\index\model;

use think\model;

class User extends model
{
	public function refresh_lottery($tablename,$array){
		foreach($array as $detail){
			if ($tablename=="lottery"){
				$sql=Db::execute("insert into ".$tablename." (id,mapId,cost,step,type,value,cnt) values (?,?,?,?,?,?)",$detail);
			}else{
				$sql=Db::execute("insert into ".$tablename." (id,boxid,minhq,maxhq,itemid,itemcnt,pro) values",$detail)
			}
		}
	}
}


 ?>