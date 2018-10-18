<?php
class CusController extends ApiController
{
	public function actionList()
	{
		$datas = $datas['list'] = [];
		$cid = (int)Yii::app()->request->getQuery('cid',0);
		$page = (int)Yii::app()->request->getQuery('page',1);
		$limit = (int)Yii::app()->request->getQuery('limit',20);
        $uid = (int)Yii::app()->request->getQuery('uid',0);
        $type = (int)Yii::app()->request->getQuery('type',0);
        $save = (int)Yii::app()->request->getQuery('save',0);
        $savetype = (int)Yii::app()->request->getQuery('savetype',0);
		$kw = $this->cleanXss(Yii::app()->request->getQuery('kw',''));
		$criteria = new CDbCriteria;
		$criteria->order = 't.sort desc,t.updated desc';
		$criteria->limit = $limit;
		$criteria->addCondition('t.status=1');
		if($kw) {
			$criteria->addSearchCondition('title',$kw);
		}
		if($cid) {
			$criteria->addCondition("cid=:cid");
			$criteria->params[':cid'] = $cid;
		}
        if($uid) {
            $criteria->addCondition("uid=:uid");
            $criteria->params[':uid'] = $uid;
        }
        if($type) {
            $criteria->addCondition("type=:type");
            $criteria->params[':type'] = $type;
        }
        if($savetype&&$save&&$uid) {
            $ids = [];
            $saeids = Yii::app()->db->createCommand("select pid from save where uid=$uid and type=$savetype")->queryAll();
            if($saeids) {
                foreach ($saeids as $key => $value) {
                    $ids[] = $value['pid'];
                }
            }
            $criteria->addInCondition('t.id',$ids);
        }
		$ress = ArticleExt::model()->with('cate')->getList($criteria);
		$infos = $ress->data;
		$pager = $ress->pagination;
		if($infos) {
			foreach ($infos as $key => $value) {
				$data['list'][] = [
					'id'=>$value->id,
					'name'=>Tools::u8_title_substr($value->title,20),
					'cate'=>$value->cate?$value->cate->name:'',
					'date'=>date('Y-m-d H:i',$value->updated),
					'author'=>$value->user?$value->user->name:'',
					'save_num'=>Yii::app()->db->createCommand("select count(id) from save where type=2 and pid=".$value->id)->queryScalar(),
					'praise_num'=>Yii::app()->db->createCommand("select count(id) from praise where cid=".$value->id)->queryScalar(),
                    'is_hot'=>$value->is_hot,
                    'hits'=>$value->hits,
					// 'price'=>$value->price,
					// 'old_price'=>$value->old_price,
					// 'ts'=>$value->shortdes,
					'image'=>ImageTools::fixImage($value->image,700,360),
				];
			}
		}
		$data['num'] = $pager->itemCount;
		$data['page_count'] = $pager->pageCount;
		$data['page'] = $page;

		$this->frame['data'] = $data;
	}

    public function actionNewsTags()
    {
        $data = [];
        $tags = TagExt::model()->findAll("cate='wzbq'");
        if($tags) {
            foreach ($tags as $key => $value) {
                $data[] = ['id'=>$value->id,'name'=>$value->name];
            }
        }
        $this->frame['data'] = $data;
    }

	public function actionInfo($id)
	{
        $data = [];
		$info = ArticleExt::model()->findByPk($id);
        $info->hits += 1;
        $info->save();
        $user = $info->user;
        $usernopic = SiteExt::getAttr('qjpz','usernopic');
        $data = [
            'id'=>$info->id,
            'title'=>$info->title,
            'author'=>$user?$user->name:'暂无',
            'image'=>$user&&$user->image?ImageTools::fixImage($data['image'],200,200):ImageTools::fixImage($usernopic,200,200),
            'time'=>date('Y-m-d H:i',$data['updated']),
            'content'=>$info->content,
        ];
        if($comments = $info->comments) {

        }
		// $data = $info->attributes;
        // if($info->user) {

        // }
		// $data['image'] = ImageTools::fixImage($data['image'],700,360);
		// $data['created'] = date('Y-m-d',$data['created']);
		// $data['updated'] = date('Y-m-d',$data['updated']);
		$this->frame['data'] = $data;
	}

	public function actionAddSave($pid='',$uid='',$type=1)
    {
        if($pid&&$uid) {
            $staff = UserExt::model()->findByPk($uid);
            if($save = SaveExt::model()->find('pid='.(int)$pid.' and type=2 and uid='.$staff->id)) {
                SaveExt::model()->deleteAllByAttributes(['pid'=>$pid,'uid'=>$staff->id,'type'=>2]);
                $this->frame['data'] = 0;
                $this->returnSuccess('取消收藏成功');
            } else {
                $save = new SaveExt;
                $save->uid = $staff->id;
                $save->pid = $pid;
                $save->type = $type;
                $save->save();
                $this->frame['data'] = 1;
                $this->returnSuccess('收藏成功');
            }
        }else {
            $this->returnError('请登录后操作');
        }
    }

    public function actionAddPraise($cid='',$uid='')
    {
        if($cid&&$uid) {
            $staff = UserExt::model()->findByPk($uid);
            if($save = PraiseExt::model()->find('cid='.(int)$cid.' and uid='.$staff->id)) {
                PraiseExt::model()->deleteAllByAttributes(['cid'=>$cid,'uid'=>$staff->id]);
                $this->returnSuccess('取消点赞成功');
                $this->frame['data'] = 0;
            } else {
                $save = new PraiseExt;
                $save->uid = $staff->id;
                $save->cid = $cid;
                // $save->type = 2;
                $save->save();
                $this->frame['data'] = 1;
                $this->returnSuccess('点赞成功');
            }
        }else {
            $this->returnError('请登录后操作');
        }
    }

    public function actionAddNews()
    {
    	if(Yii::app()->request->getIsPostRequest()) {
    		$uid = Yii::app()->request->getPost('uid','');
    		$title = Yii::app()->request->getPost('title','');
    		$content = Yii::app()->request->getPost('content','');
    		$fm = Yii::app()->request->getPost('fm','');
    		$imgs = Yii::app()->request->getPost('imgs','');
    		if(!$uid || !$title || !$content) {
    			return $this->returnError('参数错误');
    		}
    		$obj = new ArticleExt;
    		$obj->status = 0;
    		$obj->uid = $uid;
    		$obj->title = $title;
    		$obj->content = $content;
    		$obj->image = $fm;
    		if($obj->save()) {
    			if($imgs = explode(',', $imgs)) {
    				foreach ($imgs as $key => $value) {
    					$im = new AlbumExt;
    					$im->url = $value;
    					$im->pid = $obj->id;
    					$im->type = 2;
    					$im->save();
    				}
    			}
    		} else {
    			return $this->returnError(current(current($obj->getErrors())));
    		}


    	}
    }
}