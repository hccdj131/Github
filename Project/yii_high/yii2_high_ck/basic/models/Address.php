<?php

namespace app\models;

use yii\db\ActiveRecord;
use Yii;

class Address extends ActiveRecord
{
	/**
	 * table name
	 * @param %address 
	 */
	public static function tableName()
	{
		return "{{%address}}";
	}

	/**
	 * return
	 * @param userid,firstname,lastname,address,email,telephone
	 * @param createtime, postcode
	 */
	public function rules()
	{
		return [
			[['userid', 'firstname', 'lastname', 'address', 'email', 'telephone'], 'required'],
			[['createtime', 'postcode'], 'safe'],
		];
	}
}
