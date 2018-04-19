<?php
namespace app\index\controller;
use think\Controller;
class Api extends Controller {
    public function login(){       //登录
        $code = input('code');
        $user['nickName']  = filterEmoji(input('nickName'));
        $user['avatarUrl'] = input('avatarUrl');
        $type   = 'authorization_code';
        $auth   = "https://api.weixin.qq.com/sns/jscode2session?appid=".APPID."&secret=".SECRET."&js_code=".$code."&grant_type=".$type;
        $res    = httpGet($auth);
        $ident  = json_decode($res,TRUE);
        $openid = $ident['openid'];
        $user['openid']  = $openid;
        $user['regTime'] = time();
        $user['gold']    = 50;
        $find = db('user')->where('openid',$openid)->find();
        $data['openid'] = $openid;
        if($find){
            db('user')->where('openid',$openid)->update(['nickName'=>$user['nickName'],'avatarUrl'=>$user['avatarUrl']]);
            $data['flag'] = 1;
        }else{//首次登陆
            $data['flag'] = 2;
            db('user')->insert($user);
        }
        return json(['data'=>$data,'message'=>'获取成功']);      
    }

    public function getBanner(){    //获取首页banner
        $map['open'] = 1;
        $map['type'] = 0;
        $banList = db('app')->where($map)->field('appid,img,url')->select();
        if($banList)
            return json(['data'=>$banList,'msg'=>'获取成功','success'=>true]); 
        return json(['data'=>'','msg'=>'获取失败','success'=>false]); 
    }

    public function search(){       //首页搜索
        $map['title|name'] = ['like','%'.input('key').'%'];
        $map['open'] = 1;
        $list = db('hall')->field('id,openid,head_pic,title,name,creator,gongde,death,type')->order('gongde DESC')->where($map)->select();
        if($list){
            foreach ($list as $k => $v) {
               $list[$k]['death'] = date('Y-m-d',$v['death']);
                if($v['openid'] != input('openid') && $v['type'] == 0) unset($list[$k]);
            }
            return json(['data'=>$list,'msg'=>'获取成功','success'=>true]);  
        }else{
            return json(['data'=>[],'msg'=>'无数据','success'=>false]);
        }
    }

    public function getHall(){      //获取首页信息
        $where['open'] = 1;
        $where['type'] = 1;
        $where['openid'] = ['<>', input('openid')];
        $list = db('hall')->field('id,head_pic,title,name,creator,gongde,death,type')
        ->page(input('page'),input('limit'))->where($where)->order('gongde DESC')->select();
        foreach ($list as $k => $v) {
            $list[$k]['death'] = date('Y-m-d',$v['death']);
            $record = db('record')->where('hid',$v['id'])->order('time DESC')->limit(1)->find();
            if($record) $list[$k]['nickName'] = db('user')->where('openid',$record['openid'])->value('nickName');
            else $list[$k]['nickName'] = '';
        }
        return json(['data'=>$list,'msg'=>'获取成功','success'=>true]);
    } 
	public function getHalluser(){      //获取首页个人所建馆信息
        $where['open']   = 1;
        $where['openid'] = input('openid');
        $list  = db('hall')->field('id,head_pic,title,name,creator,gongde,death,type')->where($where)->order('gongde DESC')->select();
        foreach ($list as $k => $v) {
            $list[$k]['death'] = date('Y-m-d',$v['death']);
            $record = db('record')->where('hid',$v['id'])->order('time DESC')->limit(1)->find();
            if($record) $list[$k]['nickName'] = db('user')->where('openid',$record['openid'])->value('nickName');
            else $list[$k]['nickName'] = '';
        }
        return json(['data'=>$list,'msg'=>'获取成功','success'=>true]);
    } 

    public function getDetail(){    //获取纪念馆详情
        $data = db('hall')->where('id',input('id'))->field('id,title,head_pic,name,death,type,gongde')->find();
        $data['day'] = floor((time() - $data['death']) / 86400);     //死亡多少天
        $donate = db('record')->alias('r')                           //捐献记录
        ->join('clt_user u','u.openid = r.openid')
        ->join('clt_jipin j','j.id = r.jid')
        ->field('u.avatarUrl,u.nickName,j.name,j.jipinImg,r.time')
        ->where('hid',input('id'))
        ->select(); 
        $msgList = db('msg')->alias('m')                             //留言记录
        ->join('clt_user u','u.openid = m.openid')
        ->field('u.avatarUrl,u.nickName,m.content,m.time')
        ->where('hid',input('id'))
        ->select();
        $record = array_merge($donate,$msgList);
        if($record){
            foreach ($record as $k) $time[] = $k['time'];
            array_multisort($time, SORT_DESC, $record);  
            $data['record'] = $record;          
        }else{
            $data['record'] = [];
        }

        $pubList = db('hall')->order('gongde DESC')->field('id,head_pic,gongde,name,title')->where('open',1)->select();
        foreach ($pubList as $k => $v) {
            if($v['id'] == $data['id']) {
                $data['rank'] = $k + 1;
                break;
            }
        }
        $map['open'] = $map['type'] = 1;
        $hallList = db('hall')->order('gongde DESC')->field('id,head_pic,gongde,name,title')->where($map)->select();
        $data['rankList'] = array_slice($hallList,0,3);
        $data['music']    = 'https://jd.powerdadmom.com/public/music/bgMusic.mp3';
        return json(['data'=>$data,'msg'=>'获取成功','success'=>true]);
    }

    public function getJipin(){     //查询祭品
        $jipin = db('jipin')->select();
        return json(['data'=>$jipin,'msg'=>'获取成功','success'=>true]);
    }

    public function getStory(){     //获取生平事迹
        $data = db('hall')->where('id',input('id'))->field('head_pic,title,name,creator,type,gongde,describe')->find();
        return json(['data'=>$data,'msg'=>'获取成功','success'=>true]);
    }

    public function getGold(){      //获取天堂币余额
        $data = db('user')->where('openid',input('openid'))->field('gold,gongde')->find();
        return json(['data'=>$data,'msg'=>'获取成功','success'=>true]);
    }

    public function upImg(){        //用户上传图片
        $allowSize = 7145728;
        if($_FILES['image']['error'] === 0){
            $size = $_FILES['image']['size'];
            if($size < $allowSize){
                $filename = $_FILES['image']['name'];
                $patharr  = pathinfo($filename);
                $fileext  = $patharr['extension'];
                $newname  = time()."_".mt_rand().".".$fileext;
                $newpath  = 'public/userimg/'.$newname;
                $res      = move_uploaded_file($_FILES['image']['tmp_name'],$newpath);
                if($res){
                    return '/userimg/'.$newname;
                }else{
                    return json(['msg'=>'文件移动失败','success'=>false]);
                }
            }else{
                return json(['msg'=>'图片大小不符','success'=>false]);
            }
        }else{
            return json(['msg'=>'上传图片错误','success'=>false]);
        }
    }

    public function creatHall(){    //创建纪念馆
        $data = input('post.');
        $data = $data['data'];
		$find = db('hall')->where(array('openid'=>$data['openid'],'name'=>$data['name']))->find();
		if($find)	return json(['msg'=>'添加失败','success'=>false]);
		$data['death'] = $data['death'] ? strtotime($data['death']) : 0;
		$data['open']  = 0;
		if($data['id']){
			if(db('hall')->update($data))
				return json(['msg'=>'修改成功','success'=>true]);
			return json(['msg'=>'修改失败','success'=>false]);
		}
		$data['addtime'] = time();
		if(db('hall')->insert($data))
			return json(['msg'=>'添加成功','success'=>true]);
		return json(['msg'=>'添加失败','success'=>false]);
    }  

	public function creatHallinfo(){    //修改纪念馆
        $data = input('post.');
        $data = $data['data'];
		$data['death'] = $data['death'] ? strtotime($data['death']) : 0;
		if($data['id']){
			if(db('hall')->update($data))
				return json(['msg'=>'修改成功','success'=>true]);
			return json(['msg'=>'修改失败','success'=>false]);
		}
		$data['addtime'] = time();
		if(db('hall')->insert($data))
			return json(['msg'=>'添加成功','success'=>true]);
		return json(['msg'=>'添加失败','success'=>false]);
		
    } 
    public function getHallNum(){   //获取纪念馆数量
        $count = db('hall')->where('open',1)->count();
        return json(['data'=>$count,'msg'=>'获取成功','success'=>true]); 
    }

    public function editHall(){     //修改纪念馆
        $data = db('hall')->where('id',input('id'))->field('id,head_pic,name,gender,death,creator,describe,type')->find();
        $data['count'] = db('hall')->where('open',1)->count();
		if($data['death'] == 0){
			$data['death'] = '';
		}else{
			$data['death'] = date('Y-m-d',$data['death']);
			
		}
        return json(['data'=>$data,'msg'=>'查询成功','success'=>true]);
    }

    public function delHall(){      //删除我的纪念馆
        $map['openid'] = input('openid');
        $map['id']     = input('id');
        if(db('hall')->where($map)->delete())
            return json(['success'=>true,'msg'=>'删除成功']);
        return json(['success'=>false,'msg'=>'删除失败']);
    }

    public function send(){         //送祭品
        $openid  = input('openid');
        $hid     = input('hid');
        $jid     = input('jid');    
        $balance = db('user')->where('openid',$openid)->value('gold');  //天堂币余额
        $gold    = db('jipin')->where('id',$jid)->value('gold');        //所需天堂币
        $gongde  = db('jipin')->where('id',$jid)->value('gongde');      //祭品功德值
        $newBal  = $balance - $gold;
        if($newBal < 0) return json(['msg'=>'天堂币余额不足','success'=>false]);
        $data['openid'] = $openid;
        $data['hid']    = $hid;
        $data['jid']    = $jid; 
        $data['time']   = time();
        if(!db('record')->insert($data)) return json(['msg'=>'生成记录失败','success'=>false]);
        db('user')->where('openid',$openid)->setField('gold',$newBal);          //更新天堂币余额
        db('user')->where('openid',$openid)->setInc('gongde',$gongde);          //更新个人功德值 
        db('hall')->where('id',$hid)->setInc('gongde',$gongde);                 //更新纪念馆功德
        return json(['msg'=>'赠送成功','success'=>true]);
    } 

    public function getMsg(){       //查询留言
        $donate = db('record')->alias('r')                           //捐献记录
        ->join('clt_user u','u.openid = r.openid')
        ->join('clt_jipin j','j.id = r.jid')
        ->field('u.nickName,u.avatarUrl,j.name,j.jipinImg,r.time')
        ->where('hid',input('id'))
        ->select(); 
        $msgList = db('msg')->alias('m')                             //留言记录
        ->join('clt_user u','u.openid = m.openid')
        ->field('m.id,m.content,m.time,u.nickName,u.avatarUrl,m.openid')
        ->where('hid',input('id'))
        ->select();
        $record = array_merge($donate,$msgList);
        if($record){
            foreach ($record as $k) $time[] = $k['time'];
            array_multisort($time, SORT_DESC, $record);  
            $data['record'] = $record;          
        }else{
            $data['record'] = [];
        }
        foreach ($record as $k => $v) {
            $record[$k]['flag'] = 0;
            if($v['openid'] == input('openid')) $record[$k]['flag'] = 1;
            $record[$k]['time'] = date("Y-m-d",$v['time']);
        }
        return json(['data'=>$record,'msg'=>'获取成功','success'=>true]);
    }

    public function message(){      //留言
        $data = input('post.');
		$data = $data['data'];
		$data['time'] = time();
		if(db('msg')->insert($data)){
			return json(['msg'=>'添加成功','success'=>true]);
		}else{
			return json(['msg'=>'添加失败','success'=>false]);
		}
    }

    public function delMsg(){       //删除留言 
        if(db('msg')->delete(input('id')))
            return json(['msg'=>'删除成功','success'=>true]); 
        return json(['msg'=>'删除失败','success'=>false]);
    }

    public function rank(){         //纪念馆排行 
        $map['open'] = $map['type'] = 1;
        $list = db('hall')->order('gongde DESC')->field('id,head_pic,title,creator,gongde,type')->where($map)->select();
        return  json(['data'=>$list,'msg'=>'获取成功','success'=>true]);
    }   

    public function userRank(){     //用户排行
        $user = db('user')->field('openid,nickName,avatarUrl,gongde')->order('gongde DESC')->select();
        foreach ($user as $k => $v) {
            if($v['openid'] == input('openid')){
                $data['rank']   = $k + 1;
                $data['gongde'] = $v['gongde'];
                break;
            }
        }
        if($data) return json(['data'=>$data,'msg'=>'获取成功','success'=>true]);
    }

    public function getMyhall(){    //获取我的纪念馆
        $map['openid'] = input('openid'); 
        $list = db('hall')->where($map)->select();
        foreach ($list as $k => $v) {
            $list[$k]['death']   = date('Y-m-d',$v['death']);
            $list[$k]['addtime'] = date('Y-m-d H:i:s',$v['addtime']);
        }
        return  json(['data'=>$list,'msg'=>'获取成功','success'=>true]);
    }

    public function share(){	   //分享得天堂币
        $data['reward'] = 20; 
    	if(db('user')->where('openid',input('openid'))->setInc('gold',$data['reward']))
            $data['gold'] = db('user')->where('openid',input('openid'))->value('gold');
    		return json(['data'=>$data,'msg'=>'分享成功','success'=>true]);
    	return json(['msg'=>'分享失败','success'=>true]);
    }

    public function getAd(){        //获取广告
        $map['type'] = 1;
        $map['open'] = 1;
        $data = db('app')->where($map)->find();
        return json(['data'=>$data,'msg'=>'查询成功','success'=>true]);
    }
}