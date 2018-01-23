<?php

namespace app\controllers;

use app\controllers\CommonController;
use Yii;
use app\models\Order;
use app\models\OrderDetail;
use app\models\Cart;
use app\modles\Cart;
use app\models\Product;
use app\models\User;
use app\models\Address;
use app\models\Pay;
use dzer\express\Express;

class OrderController extends CommonController
{
	protected $mustlogin = ['index', 'check', 'add', 'confirm', 'pay', 'getexpress', 'received'];
	protected $verbs = [
		'confirm' => ['post']
	];

	public function actionIndex()
	{
		$this->layout = "layout2";
		if (Yii::$app->user->isGuest) {
			return $this->redirect(['member/auth']);
		}
		if (Yii::$app->session['isLogin'] != 1) {
			return $this->redirect(['member/auth']);
		}
		$loginname = Yii::$app->session['loginname'];
		$userid = User::find()
		->where('username = :name or useremail = :email', [':name' => $loginname, ':email' => $loginname])
		->one()->userid;
		$userid = Yii::$app->user->id;
		$orders = Order::getProducts($userid);
		return $this->render("index", ['orders' => $orders]);
	}

	public function actionCheck()
	{
		if (Yii::$app->session['isLogin'] != 1) {
			return $this->redirect(['member/auth']);
		}
		$orderid = Yii::$app->request->get('orderid');
		$status = Order::find()->where('orderid = :oid', [':oid' => $orderid])->one()->status;
		if ($status != Order::CREATEORDER && $status != Order::CHECKORDER) {
			return $this->redirect(['order/index']);
		}
		// $loginname = Yii::$app->session['loginname'];
		// $userid = User::find()->where('username = :name or useremail = :email', [':name' => $loginname, ':email' => $loginname])->one()->userid;
		$userid = Yii::$app->user->id;
		$address = Address::find()
		->where('userid = :uid', [':uid' => $userid])
		->asArray()
		->all();

		$details = OrderDetail::find()
		->where('orderid = :oid', [':oid' => $orderid])
		->asArray()
		->all();

		$data = [];
		foreach ($details as $detail) {
			$model = Product::find()
			->where('productid = :pid' , [':pid' => $detail['productid']])
			->one();
			$detail['title'] = $model->title;
			$detail['cover'] = $model->cover;
			$data[] = $detail;
		}
		$express = Yii::$app->params['express'];
		$expressPrice = Yii::$app->params['expressPrice'];
		$this->layout = "layout1";
		return $this->render("check", 
			['express' => $express, 
			'expressPrice' => $expressPrice, 
			'addresses' => $addresses, 
			'products' => $data]);
	}

	public function actionAdd()
	{
		if (Yii::$app->session['isLogin'] != 1) {
			return $this->redirect(['member/auth']);
		}
		$transaction = Yii::$app->db->beginTransaction();
		try {
			if (Yii::$app->request->isPost) {
				$post = Yii::$app->request->post();
				$ordermodel = new Order;
				$ordermodel->scenario = 'add';
				$usermodel = User::find()
				->where('username = :name or usermail = :email', 
					[':name' => Yii::$app->session['loginname'], 
					':email'=> Yii::$app->session['loginname']])
				->one();
				// 绑定参数
				// 当使用带参数的 SQL 来创建数据库命令时，你几乎总是应该使用绑定参数的方法来防止 SQL 注入攻击
				// 在 SQL 语句中， 你可以嵌入一个或多个参数占位符(例如，上述例子中的 :id )。 一个参数占位符应该是以冒号开头的字符串。
				if (!$usermodel) {
					throw new \Exception();
				}
				$userid = $usermodel->userid;
				$userid = Yii::$app->user->id;
				$ordermodel->userid = $userid;
				$ordermodel->status = Order::CREATEORDER;
				$ordermodel->createtime = time();
				if (!$ordermodel->save()) {
					throw new \Exception();
				}
				$orderid = $ordermodel->getPrimaryKey();
				foreach ($post['OrderDetail'] as $product) {
					$model = new OrderDetail;
					$product['orderid'] = $orderid;
					$product['createtime'] = time();
					$data['OrderDetail'] = $product;
					if (!$model->add($data)) {
						throw new \Exception();
					}
					Cart::deleteAll('productid = :pid' , [':pid' => $product['productid']]);
					Product::updateAllCounters(['num' => -$product['productnum']], 'productid = :pid', [':pid' => $product['productid']]);
				}
			}
			$transaction->commit();
		}catch(\Exception $e) {
			$transaction->rollback();
			return $this->redirect(['cart/index']);
		}
		return $this->redirect(['order/check', 'orderid' => $orderid]);
	}

	public function actionConfirm()
	{
		//addressid, expressid, status, amount(orderid,userid)
		try {
			if (Yii::$app->session['isLogin'] != 1) {
				return $this->redirect(['member/auth']);
			}
			if (!Yii::$app->request->isPost) {
				throw new \Exception();
			}
			$post = Yii::$app->request->post();
			$loginname = Yii::$app->session['loginname'];
			$usermodel = User::find()
			->where('username = :name or usermail = :email', 
				[':name' => $loginname, ':email' => $loginname])
			->one();
			if (empty($usermodel)) {
				throw new \Exception();
			}
			$userid = $usermodel->userid;
			$userid = Yii::$app->user->id;
			$model = Order::find()
			->where('orderid = :oid and userid = :uid', 
				[':oid' => $post['orderid'], ':uid' => $userid])
			->one();
			if (empty($model)) {
				throw new \Exception();
			}
			$model->scenario = "update";
			$post['status'] = Order::CHECKORDER;
			$details = OrderDetail::find()->where('orderid = :oid', [':oid' => $post['orderid']])->all();
			$amount = 0;
			foreach ($details as $detail) {
				$amount += $detail->productnum*$detail->price;
			}
			if ($amount <= 0) {
				throw new \Exception();
			}
			$express = Yii::$app->params['expressPrice'][$post['expressid']];
			if ($express < 0) {
				throw new \Exception();
			}
			$amount += $express;
			$post['amount'] = $amount;
			$data['Order'] = $post;
			if (empty($post['addressid'])) {
				throw new \Exception();
				return $this->redirect(['order/pay', 
					'orderid' => $post['orderid'], 
					'payment' => $post['paymenthod']]);
			}
			if ($model->load($data) && $model->save()) {
				return $this->redirect(['order/pay', 'orderid' => $post['orderid'], 'paymenthod' => $post['paymenthod']]);
			}
		}catch(\Exception $e) {
			return $this->redirect(['index/index']);
		}
	}

	public function actionPay()
	{
		try{
			if (Yii::$app->session['isLogin'] != 1) {
				throw new \Exception();
			}
			$orderid = Yii::$app->request->get('orderid');
			$paymenthod = Yii::$app->request->get('paymenthod');
			if (empty($orderid) || empty($paymenthod)) {
				throw new \Exception();
			}
			if ($paymenthod == 'alipay') {
				return Pay::alipay($orderid);
			}
		}catch(\Exception $e) {}
		return $this->redirect(['order/index']);
	}

	public function actionGetexpress()
	{
		$expressno = Yii::$app->request->get('expressno');
		$res = Express::search($expressno);
		echo $res;
		exit;
	}

	public function actionReceived()
	{
		$orderid = Yii::$app->request->get('orderid');
		$order = Order::find()->where('orderid = :oid', [':oid' => $orderid])->one();
		if (!empty($order) && $order->status == Order::SENDED) {
			$order->status = Order::RECEIVED;
			$order->save();
		}
		return $this->redirect(['order/index']);
	}
}

