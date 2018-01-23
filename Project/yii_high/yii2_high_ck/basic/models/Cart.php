<?php

namespace app\models;

use yii\db\ActiveRecord;
use Yii;
use yii\behaviors\TimestampBehavior;

class Cart extends ActiveRecord
{
	/**
	 * @Author   CK
	 * @DateTime 2018-01-19
	 * behaviors
	 * createtime,updattime
	 * @param    {string}
	 * @param    {string}
	 * @return   [attributes]
	 */
	public function behaviors()
	{
		return [
			[
				'class' => TimestampBehavior::className(),
				'createdAtAttribute' => 'createtime',
				'updatedAtAttribute' => 'updatetime',
				'attributes' => [
					ActiveRecord::EVENT_BEFORE_INSERT => ['createtime', 'updatetime'],
					ActiveRecord::EVENT_BEFORE_UPDATE => ['updatetime'],
				]
			]
		];
	}

	/**
	 * @DateTime 2018-01-19
	 * tableName
	 * 描述
	 * @param    {string}
	 * @param    {string}
	 * @return   [%cart]
	 */
	public static function tableName()
	{
		return "{{%cart}}";
	}

	/**
	 * @DateTime 2018-01-19
	 * rules
	 * 描述
	 * @return   [type]
	 */
	public function rules()
	{
		return [
			[['productid','productnum','userid','price'], 'required'],
			['createtime', 'safe']
		];
	}
}
