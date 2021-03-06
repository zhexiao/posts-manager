<?php

namespace app\modules\social\controllers;

use yii\web\Controller;
use zhexiao;

class TwitterController extends CommonController implements SocialInterface{
    // define the codebird variable
    private $_codebird;

    // define the output data
    private $_output = ['error' => false];

    /**
     * initialization controller
     * @return [type] [description]
     */
    public function init(){
        // overload parent controller
        parent::init();

        \zhexiao\twitter\Codebird::setConsumerKey(\Yii::$app->params['TWITTER_CONSUMER_KEY'], \Yii::$app->params['TWITTER_CONSUMER_SECRET']);

        $this->_codebird = \zhexiao\twitter\Codebird::getInstance();
        // convert the return data to array
        $this->_codebird->setReturnFormat(CODEBIRD_RETURNFORMAT_ARRAY);
    }

    /**
     * link twitter account
     * @return [type] [description]
     */
    public function actionConnect(){
        $reply = $this->_codebird->oauth_requestToken([
            'oauth_callback' => \Yii::$app->params['TWITTER_CALLBACK_URL']
        ]);

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

        $res = $this->_codebird->oauth_accessToken([
            'oauth_verifier' => $oauth_verifier
        ]);

        // using user token to get twitter user data
        $this->_codebird->setToken($res['oauth_token'], $res['oauth_token_secret']);
        $twitterUser = $this->getTwitterUser($res['user_id']);

        if(isset($twitterUser['httpstatus']) && $twitterUser['httpstatus']==200){
            $insertData = [];

            // check this ip already exist or not
            $data = $this->getData();
            if($data){
                // remove and re-insert data
                $this->removeData($data['_id']);
                $insertData = $data['socialData'];
            }

            $insertData['twitter_'.$res['user_id']] = $twitterUser[0];
            $insertData['twitter_'.$res['user_id']]['type'] = 'twitter';
            $insertData['twitter_'.$res['user_id']]['oauth_token'] = $res['oauth_token'];
            $insertData['twitter_'.$res['user_id']]['oauth_token_secret'] = $res['oauth_token_secret'];

            // insert into mongodb
            $this->insertDb([
                'ip' => \Yii::$app->request->userIP, 
                'socialData' => $insertData
            ]);

            $this->redirect('/');
        }   
    }

    /**
     * get posts
     * @return [type] [description]
     */
    public function actionPosts($key){
        if( $this->setToken($key) ){
            $posts = $this->_codebird->statuses_userTimeline();       

            if(isset($posts['httpstatus']) && $posts['httpstatus'] == 200){
                unset($posts['httpstatus']);
                unset($posts['rate']);

                $this->outputJson(array(
                    'data' => $posts
                ));
            }   
        }
    }

    /**
     * delete post
     * @return [type] [description]
     */
    public function actionDel(){
        if($this->request->isPost){
            $key = $this->request->post('key');
            $ids = $this->request->post('id');

            if(count($ids) > 0 && $this->setToken($key)){
                foreach ($ids as  $statusId) {
                    $this->_codebird->statuses_destroy_ID('id='.$statusId);
                }

                $this->outputJson($this->_output);
            }
        }
    }


    /**
     * api get twitter user
     * @param  [type] $user_id [description]
     * @return [type]          [description]
     */
    private function getTwitterUser($user_id){
        $twitterUser = $this->_codebird->users_lookup([
            'user_id' => $user_id
        ]);

        return $twitterUser;
    }

    /**
     * set this user's twitter token
     */
    private function setToken($key){
        $data = $this->getData();
        if($data){
            $socialInfo = $data['socialData'][$key];
            $this->_codebird->setToken($socialInfo['oauth_token'], $socialInfo['oauth_token_secret']);

            return true;
        }

        return false;
    }
}
