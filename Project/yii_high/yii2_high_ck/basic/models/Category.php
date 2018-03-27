<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\behaviors\BlameableBehavior;

class Category extends ActiveRecord
{
	public function behaviors()
	{
		return [
			[
				// BlameableBehavior automatically fills the specified attributes with the current user ID.
				// By default, BlameableBehavior will fill the created_by and updated_by attributes with the current user ID when the associated AR object is being inserted;
				// it will fill the adminid with current admin ID when the associated AR object is being inserted;
				'class' => BlameableBehavior::className(),
				'createdByAttribute' => 'adminid',
				'updatedByAttribute' => null,
				'value' => Yii::$app->admin->id,
			],
		];
	}

	public static function tableName()
	{
		return "{{%category}}";
	}

	public function attributeLabels()
	{
		return [
			'parentid' => '上级分类',
			'title' => '分类名称'
		];
	}

	public function rules()
	{
		return [
			['parentid', 'required', 'message' => '上级分类不能为空', 'except' => 'rename'],
			['title', 'required', 'message' => '标题名称不能为空'],
			['createtime', 'safe']
		];
	}

	public function add($data)
	{
		$data['Category']['createtime'] = time();
		$data['Category']['adminid'] = Yii::$app->admin->id;
		if ($this->load($data) && $this->save()) {
			return true;
		}
		return false;
	}

	public function getData()
	{
		$cates = self::find()->all();
		$cates = ArrayHelper::toArray($cates);
		return $cates;
	}

	public function getTree($cates, $pid = 0)
	{
		$tree = [];
		foreach($cates as $cate) {
			if ($cate['parentid'] == $pid) {
				$tree[] = $cate;
				$tree = array_merge($tree, $this->getTree($cates, $cate['cateid']));
				// 把两个数组合并为一个数组
			}
		}
		return $tree;
	}

	public function setPrefix($data, $p = "|-----")
	{
		$tree = [];
		$num = 1;
		$prefix = [0 => 1];
		while($val = current($data)) {
			// current() 函数返回数组中的当前元素的值。
			$key = key($data);
			if ($key > 0) {
				if ($data[$key - 1]['parentid'] != $val['parentid']) {
					$num ++;
				}
			}
			if (array_key_exists($val['parentid'], $prefix)) {
				$num = $prefix[$val['parentid']];
			}
			$val['title'] = str_repeat($p, $num).$val['title'];
			// str_repeat() 函数把字符串重复指定的次数。
			$prefix[$val['parentid']] = $num;
			$tree[] = $val;
			next($data);
			// next() - 将内部指针指向数组中的下一个元素，并输出
		}
		return $tree;
	}

	public function getOptions()
	{
		$data = $this->getData();
		$tree = $this->getTree($data);
		$tree = $this->setPrefix($tree);
		$options = ['添加顶级分类'];
		foreach ($tree as $cate) {
			$options[$cate['cateid']] = $cate['title'];
		}
		return $options;
	}

	public static function getMenu()
	{
		$top = self::find()
		->where('parentid = :pid', [":pid" => 0])
		->limit(11)
		->orderby('createtime asc')
		->asArray()
		->all();
		$data = [];
		foreach ((array)$top as $k => $cate) {
			$cate['children'] = self::find()
			->where("parentid = :pid", [":pid" => $cate['cateid']])
			->limit(10)
			->asArray()
			->all();
		}
		return $data;
	}

	/**
     * getChild 递归查询所有子类数据
	 *
	 */
	public function getChild($pid)
	{
		$data = self::find()->where('parentid = :pid', [":pid" => $pid])->all();
		if (empty($data)) {
			return [];
		}
		$children = [];
		foreach ($data as $child) {
			$children[] = [
				"id" => $child->cateid,
				"text" => $child->title,
				"children" => $this->getChild($child->cateid)
			];
		}
		return $children;
	} 

	/**
     * 查询所有的顶级分类
     *
     */
    public function getPrimaryCate()
    {
    	$data = self::find()->where("parentid = :pid", [":pid" => 0]);
    	if (empty($data)) {
    		return [];
    	}
    	$pages = new \yii\data\Pagination(['totalCount' => $data->count(), 'pageSize' => '10']);
    	$data = $data->orderBy('createtime desc')
    	->offset($pages->offset)
    	->limit($pages->limit)
    	->all();

    	if (empty($data)) {
    		return [];
    	}
    	$primary = [];
    	foreach ($data as $cate) {
    		$primary[] = [
    			'id' => $cate->cateid,
    			'text' => $cate->title,
    			'children' => $this->getChild($cate->cateid)
    		];
    	}
    	return ['data' => $primary, 'pages' => $pages];
    }
}