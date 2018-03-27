<?php

namespace app\controllers;

use Yii;
use app\controllers\CommonController;
use app\models\User;
use app\models\Address;

class AddressController extends CommonController
{
	protected $mustlogin = ['del', 'add'];

	public function actionAdd()
	{
		if (Yii::$app->session['isLogin'] !=1) {
			return $this->redirect(['member/auth']);
		}
		//设置loginname的session
		$loginname = Yii::$app->session['loginname'];

		$userid = User::find()->where('username = :name or useremail = :email', [':name' => $loginname, ':email' => $loginname])->one()->userid;

		//从客户端取地址数据，然后变成数组，保存到数据库
		$userid = Yii::$app->user->id;
		if (Yii::$app->request->isPost) {
			$post = Yii::$app->request->post();
			$post['userid']  = $userid;
			$post['address'] = $post['address1'].$post['address2'];
			$data['Address'] = $post;
			$model = new Address;
			$model->load($data);
			$model->save();
		}
		return $this->redirect($_SERVER['HTTP_REFERER']);
	}

	public function actionDel()
	{
		if (Yii::$app->session['isLogin'] != 1) {
			return $this->redirect(['member/auth']);
		}
		$loginname = Yii::$app->session['loginname'];
		$userid = User::find()
		->where('username = :name or useremail = :email', [':name' => $loginname, ':email' => $loginname])
		->one()->userid;
		$userid = Yii::$app->user->id;
		$addressid = Yii::$app->request->get('addressid');
		if (!Address::find()
			->where('userid = :uid and addressid = :aid', [':uid' => $userid, ':aid' =>$addressid])
			->one()) {
			return $this->redirect($_SERVER['HTTP_REFERER']);
		}
		Address::delete('addressid = :aid', [':aid' => $addressid]);

// 要删除单行数据，首先获取与该行对应的 AR 实例，然后调用 yii\db\ActiveRecord::delete() 方法。
// $customer = Customer::findOne(123);
// $customer->delete();
		// 你可以调用 yii\db\ActiveRecord::deleteAll() 方法删除多行甚至全部的数据。例如，

// Customer::deleteAll(['status' => Customer::STATUS_INACTIVE]);
// 提示：不要随意使用 deleteAll() 它真的会 清空你表里的数据，因为你指不定啥时候犯二。

		return $this->redirect($_SERVER['HTTP_REFERER']);
	}
}



