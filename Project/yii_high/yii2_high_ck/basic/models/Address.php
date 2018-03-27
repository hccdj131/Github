<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

// 一个 AR 类关联一张数据表， 每个 AR 对象对应表中的一行，对象的属性（即 AR 的特性Attribute）映射到数据行的对应列。 即一条活动记录（AR 对象）对应数据表的一行，AR 对象的属性则映射该行的相应列。 您可以直接以面向对象的方式来操纵数据表中的数据，

// 例如，假定 Customer AR 类关联着 customer 表， 且该类的 name 属性代表 customer 表的 name 列。 你可以写以下代码来哉 customer 表里插入一行新的记录:

// $customer = new Customer();
// $customer->name = 'Qiang';
// $customer->save();

class Address extends ActiveRecord
{
	public static function tableName()
	{
		return "{{%address}}";
	}

	public function rules()
	{
		return [
			[['userid', 'firstname', 'lastname', 'address', 'email', 'telephone'], 'required'],
			[['createtime', 'postcode'], 'safe'],
			// 提供一个特别的别名为 safe 的验证器来申明 哪些属性是安全的不需要被验证
		];
	}
}