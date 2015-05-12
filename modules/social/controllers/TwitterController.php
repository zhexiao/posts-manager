<?php

namespace app\modules\social\controllers;

use yii\web\Controller;
use zhexiao;

class TwitterController extends CommonController{
    // define the codebird variable
    private $_codebird;

    /**
     * initialization controller
     * @return [type] [description]
     */
    public function init(){
        // overload parent controller
        parent::init();

        \zhexiao\twitter\Codebird::setConsumerKey(\Yii::$app->params['TWITTER_CONSUMER_KEY'], \Yii::$app->params['TWITTER_CONSUMER_SECRET']);

        $this->_codebird = \zhexiao\twitter\Codebird::getInstance();
        $this->_codebird->setReturnFormat(CODEBIRD_RETURNFORMAT_ARRAY);
    }

    /**
     * link twitter account
     * @return [type] [description]
     */
    public function actionConnect(){
        $reply = $this->_codebird->oauth_requestToken(array(
            'oauth_callback' => \Yii::$app->params['TWITTER_CALLBACK_URL']
        ));

        // store the token
        $this->_codebird->setToken($reply['oauth_token'], $reply['oauth_token_secret']);

        $this->session->set('oauth_token_secret', $reply['oauth_token_secret']);

        $auth_url = $this->_codebird->oauth_authorize();

        $this->redirect($auth_url);
    }

    /**
     * auth twitter account and save token
     * @return [type] [description]
     */
    public function actionAuth(){
        $oauth_token = $this->request->get('oauth_token');
        $oauth_verifier = $this->request->get('oauth_verifier');

        $oauth_secret = $this->session->get('oauth_token_secret');
        $this->_codebird->setToken($oauth_token, $oauth_secret);

        $res = $this->_codebird->oauth_accessToken(array(
            'oauth_verifier' => $oauth_verifier
        ));

        $this->_codebird->setToken($res['oauth_token'], $res['oauth_token_secret']);
        $twitterUser = $this->_codebird->users_lookup(array(
            'user_id' => $res['user_id']
        ));


        if(isset($twitterUser['httpstatus']) && $twitterUser['httpstatus']==200){
            $insetData['twitter_'.$res['user_id']] = $twitterUser[0];
            $insetData['twitter_'.$res['user_id']]['oauth_token'] = $res['oauth_token'];
            $insetData['twitter_'.$res['user_id']]['oauth_token_secret'] = $res['oauth_token_secret'];

            // insert into mongodb
            $collection = \Yii::$app->db->getCollection('social');
            $collection->insert(['ip' => \Yii::$app->request->userIP, 'socialData' => $insetData]);
        }   
    }
}
