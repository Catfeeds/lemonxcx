<?php 
/**
 * 评论类 type 1 资讯
 * @author steven.allen <[<email address>]>
 * @date(2017.2.12)
 */
class CommentExt extends Comment{
	/**
     * 定义关系
     */
    public function relations()
    {
        return array(
            'news'=>array(self::BELONGS_TO, 'ArticleExt', ['major_id'=>'id']),
            'user'=>array(self::BELONGS_TO, 'UserExt', 'uid'),
            'praises'=>array(self::STAT, 'PraiseExt', 'cid'),
        );
    }

    /**
     * @return array 验证规则
     */
    public function rules() {
        $rules = parent::rules();
        return array_merge($rules, array(
            // array('name', 'unique', 'message'=>'{attribute}已存在')
        ));
    }

    /**
     * 返回指定AR类的静态模型
     * @param string $className AR类的类名
     * @return CActiveRecord Admin静态模型
     */
    public static function model($className = __CLASS__) {
        return parent::model($className);
    }

    public function afterFind() {
        parent::afterFind();
        // if(!$this->image){
        //     $this->image = SiteExt::getAttr('qjpz','productNoPic');
        // }
    }

    public function beforeValidate() {
        if(!$this->username && $this->uid) {
            $this->username = UserExt::model()->findByPk($this->uid)->name;
        }
        if($this->getIsNewRecord())
            $this->created = $this->updated = time();
        else
            $this->updated = time();
        return parent::beforeValidate();
    }

    /**
     * 命名范围
     * @return array
     */
    public function scopes()
    {
        $alias = $this->getTableAlias();
        return array(
            'sorted' => array(
                'order' => "{$alias}.sort desc,{$alias}.updated desc",
            ),
            'normal' => array(
                'condition' => "{$alias}.status=1 and {$alias}.deleted=0",
                'order'=>"{$alias}.sort desc,{$alias}.updated desc",
            ),
            'undeleted' => array(
                'condition' => "{$alias}.deleted=0",
                // 'order'=>"{$alias}.sort desc,{$alias}.updated desc",
            ),
        );
    }

    /**
     * 绑定行为类
     */
    public function behaviors() {
        return array(
            'CacheBehavior' => array(
                'class' => 'application.behaviors.CacheBehavior',
                'cacheExp' => 0, //This is optional and the default is 0 (0 means never expire)
                'modelName' => __CLASS__, //This is optional as it will assume current model
            ),
            'BaseBehavior'=>'application.behaviors.BaseBehavior',
        );
    }

    public function getMajorInfo()
    {
        if($this->type==1) {
            return $this->news;
        }
    }

    public function getObj()
    {
        return self::model()->findByPk($this->comment_id);
    }

}