<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\Category;
use app\models\Cart;
use app\models\Product;
use app\models\User;

class CommonController extends Controller
{
	protected $actions   = ['*'];
	protected $except    =  [];
	protected $mustlogin = [];
	protected $verbs     = [];

	public function behaviors()
	{
		return [
			'access' => [
// AccessControl provides simple access control based on a set of rules.
// AccessControl is an action filter. It will check its $rules to find the first rule that matches the current context variables (such as user IP address, user role). The matching rule will dictate whether to allow or deny the access to the requested controller action. If no rule matches, the access will be denied.

// To use AccessControl, declare it in the behaviors() method of your controller class. For example, the following declarations will allow authenticated users to access the "create" and "update" actions and deny all other users from accessing these two actions.
				'class' => \yii\filters\AccessControl::className(),
				'only'  => $this->actions,
				'except' => $this->except,
				'rules' => [
					[
						'allow' => false,
						'actions' => empty($this->mustlogin) ? [] : $this->mustlogin,
						'roles' => ['?'] // guest
					],
					[
						'allow' => true,
						'actions' => empty($this->mustlogin) ? [] : $this->mustlogin,
						'roles' => ['@'],
					],
				],
			],
			'verbs' => [
				// VerbFilter is an action filter that filters by HTTP request methods.
// It allows to define allowed HTTP request methods for each action and will throw an HTTP 405 error when the method is not allowed.
// To use VerbFilter, declare it in the behaviors() method of your controller class. For example, the following declarations will define a typical set of allowed request methods for REST CRUD actions.
				'class' => \yii\filters\VerbFilter::className(),
				'actions' => $this->verbs,
			],
		];
	}

	public function init()
	{
		// 菜单缓存
		$cache = Yii::$app->cache;
		$key = 'menu';
		if (!$menu = $cache->get($key)) {
			// get()：通过一个指定的键（key）从缓存中取回一项数据。 如果该项数据不存在于缓存中或者已经过期/失效，则返回值 false。
			$menu = Category::getMenu();
			$cache->set($key, $menu, 3600*2);
			// set()：将一个由键指定的数据项存放到缓存中。
			// public boolean set ( $key, $value, $duration = null, $dependency = null )
		}
		$this->view->params['menu'] = $menu;

		//购物车缓存
		$key = "cart";
		if (!$data = $cache->get($key)) {
			$data = [];
			$data['products'] = [];
			$total = 0;
			$userid = Yii::$app->user->id;  //获取当前登录用户的ID值。
// Yii::app()->user->id 实际上是访问 Yii::app()->user->getId()，所以你的列名是userid那就重写getId函数，返回userid就可以了。
			$carts = Cart::find()->where('userid = :uid', [':uid' => $userid])->asArray()->all();
			foreach($carts as $k => $pro) {
				$product = Product::find()->where('productid = :pid', [':pid' => $pro['productid']])->one();
				$data['products'][$k]['cover']      = $product->cover;
				$data['products'][$k]['title']      = $product->title;
				$data['products'][$k]['productnum'] = $pro['productnum'];
				$data['products'][$k]['price']      = $pro['price'];
				$data['products'][$k]['productid']  = $pro['productid'];
				$data['products'][$k]['cartid']     = $pro['cartid'];
				$total += $data['products'][$k]['price'] * $data['products'][$k]['productnum'];
			}
			$data['total'] = $total;
			// DbDependency represents a dependency based on the query result of a SQL statement.
			$dep = new \yii\caching\DbDependency([
				'sql' => 'select max(updatetime) from {{%cart}} where userid = :uid',  //The SQL query whose result is used to determine if the dependency has been changed.
				'params' => [':uid' => Yii::$app->user->id], //The parameters (name => value) to be bound to the SQL statement specified by $sql.
			]);
			$cache->set($key, $data, 60, $dep);
		}
		$this->view->params['cart'] = $data;

		$dep = new \yii\caching\DbDependency([
			'sql' => 'select max(updatetime) from {{%product}} where ison = "1"',
		]);

		// 对商品做查询缓存
		$tui = Product::getDb()->cache(function (){
			return Product::find()
			->where('istui = "1" and ison = "1"')
			->orderby('createtime desc')
			->limit(3)
			->all();
		}, 60, $dep);

		$new = Product::getDb()->cache(function(){
			return Product::find()
			->where('ison = "1"')
			->orderby('createtime desc')
			->limit(3)
			->all();
		}, 60, $dep);

		$hot = Product::getDb()->cache(function(){
			return Product::find()
			->where('ison = "1" and ishot = "1"')
			->orderby('createtime desc')
			->limit(3)
			->all();

		$sale = Product::getDb()->cache(function(){
			return Product::find()->where('ison = "1" and issale = "1"')
			->orderby('createtime desc')
			->lit(3)
			->all();
		}, 60, $dep);
		$this->view->params['tui']  = (array)$tui;
		$this->view->params['new']  = (array)$new;
		$this->view->params['hot']  = (array)$hot;
		$this->view->params['sale'] = (array)$sale;
		}
	}
}


