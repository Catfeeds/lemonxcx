<?php
class IndexController extends ApiController
{
    public function actionConfig()
    {
        // 站点颜色 tab 文字和图案 站点名
        $data = [
            'color'=>Yii::app()->file->color,
            'sitename'=>Yii::app()->file->sitename,
            'phone'=>SiteExt::getAttr('qjpz','tel'),
            // 'sitename'=>Yii::app()->file->sitename,
        ];
        $this->frame['data'] = $data;
    }

    public function actionIndex()
    {
        $data = $data['imgs'] = $data['cates'] = $data['short_recoms'] = $data['long_recoms'] = [];
        // 轮播图
        $banner = SiteExt::getAttr('qjpz','indeximages');
        if($banner) {
            foreach ($banner as $key => $value) {
                $data['imgs'][] = Yii::app()->file->is_heng?ImageTools::fixImage($value,750,376):ImageTools::fixImage($value,750,826);
            }
        }
        // 分类图
        $tags = TagExt::model()->normal()->findAll(['condition'=>"cate='tab'",'limit'=>10]);
        if($tags) {
            $aat = ProductExt::$types;
            $aats = [];
            foreach ($aat as $key => $value) {
                $aats[$value['name']] = $key;
            }
            foreach ($tags as $key => $value) {

                $data['cates'][] = [
                    'id'=>$value->id,
                    'py'=>$value->name=='论坛'?'luntan':$aats[$value->name],
                    'name'=>$value->name,
                    'img'=>ImageTools::fixImage($value->icon,200,200),
                ];
            }
        }
        // 三个推荐
        $shs = RecomExt::model()->normal()->findAll(['condition'=>'cid=2','limit'=>2]);
        if($shs) {
            foreach ($shs as $key => $value) {
                $data['short_recoms'][] = [
                    'pid'=>$value->getObj()->id,
                    // 'name'=>$value->name,//750
                    'img'=>ImageTools::fixImage($value->image,370,260),
                ];
            }
        }
        // 三个推荐
        $shs = RecomExt::model()->normal()->findAll(['condition'=>'cid=1','limit'=>1]);
        if($shs) {
            foreach ($shs as $key => $value) {
                $data['long_recoms'][] = [
                    'pid'=>$value->getObj()->id,
                    // 'name'=>$value->name,//750
                    'img'=>ImageTools::fixImage($value->image,750,260),
                ];
            }
        }
        $this->frame['data'] = $data;
    }

    public function actionGetOpenId($code='')
    {
        $appid=SiteExt::getAttr('qjpz','appid');
        $apps=SiteExt::getAttr('qjpz','apps');
        if(!$appid||!$apps) {
            echo json_encode(['open_id'=>'','msg'=>'参数错误']);
            Yii::app()->end();
        }
        // $res = HttpHelper::get("https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$apps&js_code=$code&grant_type=authorization_code");
        $res = HttpHelper::getHttps("https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$apps&js_code=$code&grant_type=authorization_code");
        if($res){
            $cont = $res['content'];
            if($cont) {
                $cont = json_decode($cont,true);
                $openid = $cont['openid'];
                // $data = ['open_id'=>$cont['openid'],'session_key'=>$cont['session_key'],'uid'=>''];
                if($openid) {
                    $user = UserExt::model()->find("openid='$openid'");
                    if($user) {
                        $data = [
                            'id'=>$user->id,
                            'phone'=>$user->phone,
                            'name'=>$user->name,
                            'openid'=>$openid,
                        ];
                        echo json_encode($data);
                    } else {
                        echo json_encode(['open_id'=>$cont['openid'],'session_key'=>$cont['session_key']]);
                    }
                }
                Yii::app()->end();
            }
                
        }
    }

    public function actionSetUser()
    {
        $data['openid'] = Yii::app()->request->getPost('openid','');
        $data['name'] = Yii::app()->request->getPost('name','');
        $data['sex'] = Yii::app()->request->getPost('sex','');
        $data['pro'] = Yii::app()->request->getPost('pro','');
        $data['city'] = Yii::app()->request->getPost('city','');
        if(!$data['openid']) {
            $this->returnError('参数错误');
        }
        if($user = UserExt::getUserByOpenId($data['openid'])){
            $this->returnError('该用户已存在');
        } else {
            $obj = new UserExt;
            $obj->attributes = $data;
            if(!$obj->save()) {
                $this->returnError(current(current($obj->getErrors())));
            } else {
                $this->frame['data'] = $obj->id;
            }
        }

    }

    public function actionGetIntro()
    {
        $info = ArticleExt::model()->find(['condition'=>'type=3','order'=>'updated desc']);
        if($info) {
            $this->frame['data'] = $info->attributes;
        }
    }

    public function actionXcxLogin()
    {
        if(Yii::app()->request->getIsPostRequest()) {
            $phone = Yii::app()->request->getPost('phone','');
            $openid = Yii::app()->request->getPost('openid','');
            $name = Yii::app()->request->getPost('name','');
            if(!$phone||!$openid) {
                $this->returnError('参数错误');
                return false;
            }
            if($phone) {
                $user = UserExt::model()->find("phone='$phone'");
            } elseif($openid) {
                $user = UserExt::model()->find("openid='$openid'");
            }
        // $phone = '13861242596';
            if($user) {
                if($openid&&$user->openid!=$openid){
                    $user->openid=$openid;
                    $user->save();
                }
                
            } else {
                $user = new UserExt;
                $user->phone = $phone;
                $user->openid = $openid;
                $user->name = $name?$name:$this->get_rand_str();
                $user->status = 1;
                $user->pwd = md5('123456');
                $user->save();

                // $this->returnError('用户尚未登录');
            }
            $model = new ApiLoginForm();
            $model->isapp = true;
            $model->username = $user->phone;
            $model->password = $user->pwd;
            // $model->obj = $user->attributes
            $model->login();
            $this->staff = $user;
            $data = [
                'id'=>$this->staff->id,
                'phone'=>$this->staff->phone,
                'name'=>$this->staff->name,
                'openid'=>$this->staff->openid,
            ];
            $this->frame['data'] = $data;
        }
    }

    public function actionDecode()
    {
        include_once "wxBizDataCrypt.php";
        $appid = SiteExt::getAttr('qjpz','appid');
        $sessionKey = $_POST['accessKey'];
        $encryptedData = $_POST['encryptedData'];
        $iv = $_POST['iv'];
        $pc = new WXBizDataCrypt($appid, $sessionKey);
        $errCode = $pc->decryptData($encryptedData, $iv, $data );

        if ($errCode == 0) {
            $data = json_decode($data,true);
            $this->frame['data'] = $data['phoneNumber'];
            echo $data['phoneNumber'];
            Yii::app()->end();
            // print($data . "\n");
        } else {
            echo $errCode;
            Yii::app()->end();
        }
    }
}
