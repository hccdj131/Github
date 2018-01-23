<?php

namespace app\controllers;

use app\controllers\CommonController;
use app\models\User;
use Yii;

class MemberController extends CommonController
{
	protected $except = ['auth', 'logout', 'reg', 'qqreg', 'qqlogin', 'qqcalback'];

	public function actionAuth()
	{
		$this->layout = 'layout2';
		if (Yii::$app->request->isGet) {
			$url = Yii::$app->request->referrer;
			if (empty($url)) {
				$url = "/";
			}
			Yii::$app->session->setFlash('referrer', $url); 
			//setflash是创建一个渐隐形式的消息框，所谓的储存只是在当前session里面储存，不会重复上次的，重新调用runDialog就会重新构建
		}
		$model = new User;
		if (Yii::$app->request->isPost) {
			$post = Yii::$app->request->post();
			if ($model->login($post)) {
				$url = Yii::$app->session->getFlash('referrer');
				return $this->redirect($url);
			}
		}
		return $this->render("auth", ['model' => $model]);
	}

	public function actionLogout()
	{
		Yii::$app->session->remove('loginname');
		Yii::$app->session->remove('isLogin');
		if (!isset(Yii::$app->session['isLogin'])) {
			return $this->goBack(Yii::$app->request->referrer);
		}
		Yii::$app->user->logout(false);
		return $this->goBack(Yii::$app->request->referrer);
	}

	public function actionReg()
	{
		$model = new User;
		if (Yii::$app->request->isPost) {
			$post = Yii::$app->request->post();
			if ($model->regByMail($post)) {
				Yii::$app->session->setFlash('info', '电子邮件发送成功');
			}
		}
		$this->layout = 'layout2';
		return $this->render('auth', ['model' => $model]);
	}

	public function actionQqlogin()
	{
		require_once("../vendor/qqlogin/qqConnectAPI.php");
		$qc = new \QC();
		$qc->qq_login();
	}

	public function actionQqcallback()
	{
		require_once("../vendor/qqlogin/qqConnectAPI.php");
		$auth = new \OAuth();
		$accessToken = $auth->qq_callback();
		$openid = $auth->get_openid();
		$qc = new \QC($accessToken, $openid);
		$userinfo = $qc->get_user_info();
		$session = Yii::$app->session;
		$session['userinfo'] = $userinfo;
		$session['openid'] = $openid;
		if ($model = User::find()->where('openid = :openid', [':openid' => $openid])->one()) {
			$session['loginname'] = $model->username;
			$session['isLogin'] = 1;
			return $this->redirect(['index/index']);
		}
		return $this->redirect(['member/qqreg']);
	}

	public function actionQqreg()
	{
		$this->layout = "layout2";
		$model = new User;
		if (Yii::$app->request->isPost) {
			$post = Yii::$app->request->post();
			$session = Yii::$app->session;
			$post['User']['openid'] = $session['openid'];
			if ($model->reg($post, 'qqreg')) {
				$sseion['loginname'] = $post['User']['username'];
				$sseion['isLogin'] = 1;
				return $this->redirect(['index/index']);
			}
		}
		return $this->render('qqreg', ['model' => $model]);
	}
}