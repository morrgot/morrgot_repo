<?php
interface IAIUser{
    function getID();
    function getEmail();
    function getToken();
    function checkToken($ID,$token);

    function getFullName();
    function getName();
    function getLastName();
    function getSecondName();
    function getHref();
    function getAvatar($s = false);

    function isOnline();
    function itsMe($ID);

    function update($fields);
}


class AIUser{

    public $last_error = '';
    public $errors = array();

    protected function error($msg,&$success = null){
        $this->errors[] = $this->last_error = $msg;
        if(is_bool($success))
            $success = false;
        return $this->last_error;
    }

    const GROUP_APPROVED = 1;
    const GROUP_NOT_APPROVED = 0;
    const GROUP_REJECTED = -1;

    const COLLEGS_ID = 1;
    const FAVORITE_ID = 2;
    const SIGN_UP_ID = 3;

    const NO_AVATAR_S = '/uploads/avatar-empty.png';
    const NO_AVATAR_M = '/uploads/avatar-empty.png';

    const TABLE_USER_GIFTS = 'i_user_gifts';

    protected $_user_info = array();
    protected $_user_inf_keys = array( 'fname', 'lname', 'sname', 'email', 'gender', 'dateRegister', 'avatar', 'phones');

    protected $_user_id, $_user_type, $_token, $_notifier, $_messenger, $_account, $_its_me, $_uploadDir, $_uCollegs, $_uFavorites, $_hl, $_group;



    function __construct($ID){

        $this->_user_id = (int)$ID;
        if($this->_user_id > 0){
            $this->_user_type = 'simple_user';
            $this->getBXUser($this->_user_id);

            if(empty($this->_user_info)){
                $this->_user_type = 'bad_user';
            };
        }else{
            $this->_user_type = 'guest';
        }
    }

    public function __get($name){
        return (isset($this->_user_info[$name])) ? $this->_user_info[$name] : false;
    }

    public function __set($name, $value){
        if(in_array($name, $this->_user_inf_keys) || isset($this->_user_info[$name]))
            $this->_user_info[$name] = $value;
    }

    public function getBXUser($ID){
        if((int)$ID < 1) return false;

        $rs = CUser::GetByID($ID);

        if($ar = $rs->Fetch()){
            $this->_user_info = array_merge($this->_user_info,array(
                'lname' => $ar['LAST_NAME'],
                'fname' => $ar['NAME'],
                'sname' => $ar['SECOND_NAME'],
                'email' => $ar['EMAIL'],
                'gender' => ($ar['PERSONAL_GENDER']) ? $ar['PERSONAL_GENDER'] : 'M',
                'dateRegister' => $ar['DATE_REGISTER'],
                'lastLogin' => $ar['LAST_LOGIN']
            ));
        }

        return $this->_user_info;
    }
 

    public function getu(){
        return $this->_user_info;
    }

    public function getPhotos(){
        global $DB;

        $rs = $DB->Query('
            SELECT
                UF_IMAGE,
                UF_BIG,
                UF_MEDIUM,
                UF_SMALL,
                UF_DESCRIPTION
            FROM
                `'.AI::getAI()->highloads['UserPhotos']['TABLE_NAME'].'`
            WHERE
                UF_USER_ID = '.$this->_user_id.'
        ;');
        $arr = array();
        while($ar = $rs->Fetch()){
            if(empty($ar['UF_BIG'])) $ar['UF_BIG'] = CFile::GetPath($ar['UF_IMAGE']);
            $arr[] = $ar;
        }

        return $arr;
    }

    public function getAvatar($s = false){
        switch($s){
            case 'big': return $this->avatar['b']; break;
            case 'medium': return $this->avatar['m']; break;
            case 'small': return $this->avatar['s']; break;
            case 'id': return $this->avatar['file_id']; break;
            default: return $this->avatar;
        }
    }

    public function removeAvatar(){

        if(is_array($this->getAvatar())){
            if($this->update(array(
                'UF_AVATAR' => false,
                'UF_AVATAR_M' => false,
                'UF_AVATAR_S' => false
            ))){
                foreach($this->avatar as $img){
                    if(is_file($_SERVER['DOCUMENT_ROOT'].$img)){
                        if(!unlink($_SERVER['DOCUMENT_ROOT'].$img)){
                            throw new Exception('error removing file '.$img);
                        }
                    }
                }

                CFile::Delete($this->avatar['file_id']);
                $this->avatar = array();
                return true;
            }
        }

        return false;

    }

    public function getVideos(){}

    public function getType(){
        return $this->_user_type;
    }

    protected function createToken($ID){
        return md5(GetUserIp().$ID.$_SERVER['USER_AGENT'].bitrix_sessid());
    }

    public function getToken(){
        if($this->itsMe($this->_user_id)){
            if(empty($this->_token)){
                $this->_token = $this->createToken($this->_user_id);
            }
            return $this->_token;
        }

        return false;
    }

    public function checkToken($ID, $token){
        if($this->itsMe($this->_user_id)){
            return (!empty($_SESSION['ai']['token']) && $token == $this->createToken($ID) && $token == $_SESSION['ai']['token'] );
        }
        return false;
    }

    public function checkToken2($ID, $token){
        if($this->itsMe($this->_user_id)){
            if(!empty($_SESSION['ai']['token']) && $token == $this->createToken($ID) && $token == $_SESSION['ai']['token'] )
                return true;
        }
        //CHTTP::SetStatus('403 Permission Denied');
        throw new AIException('bad user auth');
        return false;
    }

    public function getID(){
        return $this->_user_id;
    }

    public function getEmail(){
        return trim($this->email);
    }

    public function getName(){
        return trim($this->fname);
    }

    public function getFullName(){
        return trim(str_replace('  ',' ',$this->lname.' '.$this->fname.' '.$this->sname));
    }

    public function getSocName(){
        return trim($this->fname.' '.$this->lname);
    }

    public function getLastName(){
        return trim($this->lname);
    }

    public function getSecondName(){
        return trim($this->sname);
    }

    public static function makeTOName($name){
        return strip_tags($name);
    }

    public function getTOName(){
        return trim(self::makeTOName($this->fname));
    }

    public static function makeIMHref($id){
    return '/im/?peer=p'.$id;
}

    public function getIMHref(){
        return self::makeIMHref($this->_user_id);
    }

    public static function makeVideoDetailHref($user_id, $video_id){
        return "/agents/$user_id/video/$video_id/";
    }

    public function getVideoDetailHref($video_id){
        return self::makeVideoDetailHref($this->_user_id, $video_id);
    }

    public function isAgent(){
        return ($this->_user_type == 'agent');
    }

    public function isClient(){
        return ($this->_user_type == 'client');
    }

    public function isAIUser(){
        return !(in_array($this->_user_type,array('guest','bad_user')));
    }

    public function isOnline(){
        return CUser::IsOnline($this->_user_id, AI::ONLINE_INTERVAL);
    }

    public function itsMe($ID = false){
        $ID = (int)$ID;

        //if($this->_its_me === null) $this->_its_me = /*($ID > 0) ?*/ !!($this->isAIUser() && $this->_user_id == $ID) ;//: !!($this->isAIUser() && $this->_user_id == $GLOBALS['USER']->GetID());

        return !!($this->isAIUser() && $ID === (int)$GLOBALS['USER']->GetID());
    }

    public function getUploadDir(){
        return $this->_uploadDir;
    }

    /**
     * Получить статус пользователя в списке. Если пользователя в списке или самого списка не существует, возвращает false
     * */
    public function getStatusInGroup($pID,$grID){
        $arr = $this->getUsersInGroup($grID);
        return (isset($arr[$pID])) ? (int)$arr[$pID] : false;
    }



    /**
     * Есть ли у агента в группе с $grID человек с $pID. Если обязательно подтверждение, то учитываем и его.
     * */
    public function inGroup($pID, $grID, $need_approve = false){
        return !!(($need_approve === true) ? ($this->getStatusInGroup($pID, $grID) === self::GROUP_APPROVED) : ($this->getStatusInGroup($pID, $grID) !== false));
    }

    /**
     * Есть ли у агента в избранном $pID
     * */
    public function isFavorite($pID){
        return $this->inGroup($pID,self::FAVORITE_ID);
    }

    public function getFavorites(){
        if(empty($this->_uFavorites)){
            $this->_uFavorites = $this->getUsersInGroup(self::FAVORITE_ID);
        }
        return $this->_uFavorites;
    }

    /**
     * Список айдишников
     * */
    public function getFavoritesList(){
        return $this->getUsersListInGroup(self::FAVORITE_ID);
    }

    /**
     * Подписан ли пользователь на $pID
     * */
    public function isSignedUp($pID){
        return $this->inGroup($pID,self::SIGN_UP_ID);
    }

    public function getSignUp(){
        if(empty($this->_uFavorites)){
            $this->_uFavorites = $this->getUsersInGroup(self::SIGN_UP_ID);
        }
        return $this->_uFavorites;
    }

    /**
     * Список айдишников
     * */
    public static function signUpSelectSql($user_id){
        return 'select UF_PERSON_ID FROM `i_users2groups` where UF_USER_ID = '.$user_id.' AND UF_GROUP_ID = '.self::SIGN_UP_ID;
    }

    public function getSignUpList(){
        return $this->getUsersListInGroup(self::SIGN_UP_ID);
    }

    public function setUserInGroupStatus($pID,$grID,$status){
        $pID = (int)$pID;

        if($pID < 1 || $pID == $this->_user_id) return false;

        if($status === self::GROUP_APPROVED || $status === self::GROUP_NOT_APPROVED || $status === self::GROUP_REJECTED){
            return !!$GLOBALS['DB']->Update('i_users2groups', array('UF_STATUS' => $status), 'WHERE UF_USER_ID = '.$this->_user_id.' AND UF_PERSON_ID = '.$pID.' AND UF_GROUP_ID = '.$grID);
        }
        return false;
    }

    /**
     * Получить список айди пользователей в группе $grID к статусам " 1234 => *статус* "
     **/
    public function getUsersInGroup($grID){
        if(empty($this->_group[$grID])){
            $rs = $GLOBALS['DB']->Query('select UF_PERSON_ID, UF_STATUS FROM `i_users2groups` where UF_USER_ID = '.$this->_user_id.' AND UF_GROUP_ID = '.(int)$grID.';');
            $this->_group[$grID] = array();
            while($ar = $rs->Fetch()){
                $this->_group[$grID][$ar['UF_PERSON_ID']] = (int)$ar['UF_STATUS'];
            }
        }
        return $this->_group[$grID];
    }

    /**
     * Список айдишников в группе
     * */
    public function getUsersListInGroup($grID, $status = 32114){
        $status = (int)$status;
        if($status === self::GROUP_APPROVED || $status === self::GROUP_NOT_APPROVED || $status === self::GROUP_REJECTED)
            return array_keys($this->getUsersInGroup($grID), $status);
        else
            return array_keys($this->getUsersInGroup($grID));
    }

    protected function getUserN2NData($m2m_table, $lib_table, $m2m_field){
        global $DB;

        $rs = $DB->Query('
                SELECT
                    t_l.*
                FROM
                    `'.$m2m_table.'` as t
                    INNER JOIN `'.$lib_table.'` as t_l ON t.'.$m2m_field.' = t_l.ID
                WHERE
                    t.UF_USER_ID = '.$this->_user_id.'
                ORDER BY
                    t_l.ID asc
            ;');

        $arr = array();
        while($ar = $rs->Fetch()){
            $arr[$ar['ID']] = $ar;
        }
        return $arr;
    }

    public function deleteUserN2NData($m2m_table){
        global $DB;

        $rs = $DB->Query('DELETE FROM `'.$m2m_table.'` WHERE `UF_USER_ID` = '.$this->_user_id.';');
        //die( 'DELETE FROM `'.$m2m_table.'` WHERE `UF_USER_ID` = '.$this->_user_id.';');

        return !!($rs && $rs->AffectedRowsCount() && $rs->result);
    }

    public function update($fields){
        $fields['UF_USER_ID'] = $this->_user_id;

        unset($_SESSION['ai']['user']);

        $rs = CPAOHighLoad::getHighLoad($this->_hl['ID'])->update(
            $this->_tableId,
            $fields
        );

        return $rs->isSuccess();
    }

    public function jsonArray(){
        return array(
            'id' => $this->_user_id,
            'name' => $this->getSocName(),
            'href' => $this->getHref(),
            'online' => (int)$this->isOnline(),
            'avatar' => $this->getAvatar('small')
        );
    }

    public function jsArray(){
        return array(
            'id' => $this->getID(),
            'name' => $this->getSocName(),
            'avatar_s' => $this->getAvatar('small'),
            'avatar_m' => $this->getAvatar('medium'),
            'to_name' => $this->getTOName(),
            'type' => $this->getType(),
            'href' => $this->getHref(),
            'online' => $this->isOnline()
        );
    }

    public function notifyArray(){
        return array(
            'id' => $this->getID(),
            'type' => $this->getType(),
            'name' => $this->getSocName(),
            'href' => $this->getHref()
        );
    }

    public function notifier(){
        if($this->_notifier == null)
            $this->_notifier = new AINotifier($this->_user_id);
        return $this->_notifier;
    }

    public function messenger(){
        if($this->_messenger == null)
            $this->_messenger = new AIMessenger($this->_user_id);
        return $this->_messenger;
    }

    public function account(){
        if($this->_account == null)
            $this->_account = new AIAccount($this->_user_id);
        return $this->_account;
    }

    public function sendGift($gift_id, $to_id, $message = ''){
        try{

            if((int)$gift_id < 1)
                throw new AIException('bad gift id');

            if((int)$to_id < 1)
                throw new AIException('bad reciever id');

            $present_id = $GLOBALS['DB']->Add(self::TABLE_USER_GIFTS, array(
                    'UF_FROM' => $this->_user_id,
                    'UF_TO' => $to_id,
                    'UF_GIFT' => $gift_id,
                    'UF_MESSAGE' => trim($message),
                    'UF_TS' => time()
                )
            );

            if($present_id < 1)
                throw new AIException('error db inserting');

            return $present_id;

        }catch (AIException $e){
            $this->error($e->getMessage());
            return false;
        }
    }

    public function add2vipAds($title, $info, $region_id = 0){
        try{

            if(trim($info) == '')
                throw new AIException('empty info');

            $region_id = (int)$region_id;
            if($region_id < 1)
                $region_id = AI::DEFAULT_REGION;


            $ad_id = $GLOBALS['DB']->Add(AI::TABLE_VIP_ADS, array(
                    'UF_USER_ID' => $this->_user_id,
                    'UF_INFO' => $info,
                    'UF_TS' => time(),
                    'UF_TITLE' => $title,
                    'UF_REGION' => $region_id
                )
            );

            if($ad_id < 1)
                throw new AIException('error db inserting');

            return $ad_id;

        }catch (AIException $e){
            $this->error($e->getMessage());
            return false;
        }
    }
}