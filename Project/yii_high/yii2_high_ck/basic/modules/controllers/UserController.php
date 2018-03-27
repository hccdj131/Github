<?php

namespace app\modules\controllers;

use Yii;
use app\models\User;
use app\models\Profile;
use app\modules\controllers\CommonController;
use yii\web\Controller;
use yii\data\Pagination;

class UserController extends CommonController
{
	protected $mustlogin = ['users', 'reg', 'del'];

	public function actionUsers()
	{
		$model = User::find()->joinWith('profile');
		// yii\db\ActiveQuery::joinWith() 和 yii\db\ActiveQuery::with() 的区别是 前者连接主模型类和关联模型类的数据表来检索主模型， 而后者只查询和检索主模型类。 检索主模型
		// \yii\db\with(): list of relations that this query should be performed with.
		// joinWith(): reuse a relation query definition to add a join to a query.
		$count = $model->count();
		$pageSize = Yii::$app->params['pageSize']['user'];
		$pager = new Pagination(['totalCount' => $count, 'pageSize' => $pageSize]);
		$users = $model->offet($pager->offset)->limit($pager->limit)->all();
		$this->layout = "layout1";
		return $this->render('users', ['users' => $users, 'pager' => $pager]);
	}

	public function actionReg()
	{
		$this->layout = "layout1";
		$model = new User;
		if (Yii::$app->request->isPost) {
			$post = Yii::$app->request->post();
			if ($model->reg($post)) {
				Yii::$app->session->setFlash('info', '添加成功');
			}
		}
		$model->userpass = '';
		$model->repass = '';
		return $this->render("reg", ['model' => $model]);
	}

	public function actionDel()
	{
		try{
			$userid = (int)Yii::$app->request->get('userid');
			if (empty($userid)) {
				throw new \Exception();
			}
			$trans = Yii::$app->db->beginTransaction();
			if ($obj = Profile::find()->where('userid = :id', [':id' => $userid])->one()) {
				$res = Profile::delete('userid = :id', [':id' => $userid]);
				if (empty($res)) {
					throw new \Exception();
				}
			}
			if (!User::delete('userid = :id', [':id' => $userid])) {
				throw new \Exception();
			}
			$trans->commit();
		} catch(\Exception $e) {
			if (Yii::$app->db->getTransaction()) {
				$trans->rollback();
			}
		}
		$this->redirect(['user/users']);
	}
}
