<?php
class AIAgent extends AIUser implements IAIUser{

    protected $_tableId, $_calendar;

    public $tableId, $show, $sort, $experience, $education, $transport, $agency, $agency_info, $specialities, $languages;

    function __construct($ID){
        parent::__construct($ID);

        $this->_user_type = 'agent';

        global $DB;

        $this->_hl = AI::getAI()->highloads['Agents'];

        if(!empty($_SESSION['ai']['user']) && $this->itsMe($ID)){
            $agent = $_SESSION['ai']['user'];
        }else{

            $rs = $DB->Query("
                SELECT
                    ID,
                    UF_SORT,
                    UF_MOB_PHONE,
                    UF_WORK_PHONE,
                    UF_NAME,
                    UF_BIRTHDATE,
                    UF_AVATAR,
                    UF_AVATAR_M,
                    UF_AVATAR_S
                FROM
                    `agents_table`
                WHERE
                    UF_USER_ID = $ID
            ;");

            if($ar = $rs->Fetch()){
                list($this->lname, $this->fname, $this->sname) = explode(' ',$ar['UF_NAME']);
                $agent = array(
                    'id' => $ID,
                    'full_name' => $ar['UF_NAME'],
                    //'email' => $ar['UF_EMAIL'],
                    //'gender' => $ar['UF_GENDER'],
                    'sort' => $ar['UF_SORT'],
                    'tableId' => $ar['ID'],
                    'birthdate' => $ar['UF_BIRTHDATE'],
                    'phones' => array(
                        'mobile' => explode('_',$ar['UF_MOB_PHONE']),
                        'work' => $ar['UF_WORK_PHONE']
                    ),
                    'avatar' => array(
                        'file_id' => $ar['UF_AVATAR'],
                        'b' => CFile::GetPath($ar['UF_AVATAR']),
                        'm' => ($ar['UF_AVATAR_M']) ? $ar['UF_AVATAR_M'] : self::NO_AVATAR_M,
                        's' => ($ar['UF_AVATAR_S']) ? $ar['UF_AVATAR_S'] : self::NO_AVATAR_S
                    )
                );


                if($this->itsMe($ID))
                    $_SESSION['ai']['user'] = $agent;
            }
        }

        $this->_user_info = array_merge($this->_user_info, $agent);
        $this->_user_inf_keys = array_merge($this->_user_inf_keys, array_keys($agent));

        list($this->lname, $this->fname, $this->sname) = explode(' ',$agent['full_name']);
        //$this->email = $agent['email'];
        //$this->gender = $agent['gender'];
        $this->sort = $agent['sort'];
        $this->_tableId = $this->tableId = $agent['tableId'];

        $this->_uploadDir = $_SERVER['DOCUMENT_ROOT']."/upload/agents/agent".$this->_user_id;
    }

    public function getWorkInfo(){
        $rs = $GLOBALS['DB']->Query('
            SELECT
                UF_AGENCY,
                UF_AGENCY_INFO,
                UF_EXPERIENCE,
                UF_TRANSPORT,
                UF_LOCATION,
                UF_EDUCATION
            FROM
                `agents_table`
            WHERE
                UF_USER_ID = '.$this->_user_id.'
        ;');
        if($ar = $rs->Fetch()){
            return array(
                'agency' => $ar['UF_AGENCY'],
                'agency_info' => $ar['UF_AGENCY_INFO'],
                'experience' => $ar['UF_EXPERIENCE'],
                'transport' => $ar['UF_TRANSPORT'],
                'education' => $ar['UF_EDUCATION'],
                'location' => $ar['UF_LOCATION'],
                'specialities' => $this->getSpecialities()
            );
        }
        return false;
    }


    public function getPersonalInfo(){
        $rs = $GLOBALS['DB']->Query('
            SELECT
                UF_T_ABOUT,
                UF_T_CREDO,
                UF_T_FILM,
                UF_T_HOBBY,
                UF_T_BOOK,
                UF_BIRTHDATE,
                UF_T_ANEKDOT
            FROM
                `agents_table`
            WHERE
                UF_USER_ID = '.$this->_user_id.'
        ;');
        if($ar = $rs->Fetch()){
            return array(
                'about' => $ar['UF_T_ABOUT'],
                'credo' => $ar['UF_T_CREDO'],
                'film' => $ar['UF_T_FILM'],
                'hobby' => $ar['UF_T_HOBBY'],
                'book' => $ar['UF_T_BOOK'],
                'birthdate' => $ar['UF_BIRTHDATE'],
                'anekdot' => $ar['UF_T_ANEKDOT']
            );
        }
        return false;
    }


    /*function __get($name){

    }*/

    public function removeAvatar(){

        if(is_array($this->avatar)){
            foreach($this->avatar as $img){
                if(is_file($_SERVER['DOCUMENT_ROOT'].$img)){
                    if(!unlink($_SERVER['DOCUMENT_ROOT'].$img)){
                        throw new Exception('error removing file '.$img);
                    }
                }
            }

            CFile::Delete($this->avatar['file_id']);

            return $this->update(array(
                'UF_AVATAR' => '',
                'UF_AVATAR_M' => '',
                'UF_AVATAR_S' => ''
            ));
        }

    }

    public static function makeHref($id){
        return '/agents/'.$id.'/';
    }

    public function getHref(){
        return self::makeHref($this->_user_id);
    }

    /**
     * Есть ли у агента коллега $pID
     * */
    public function isColleg($pID){
        return $this->inGroup($pID,self::COLLEGS_ID,true);
    }

    public function getCollegs(){
        if(empty($this->_uCollegs)){
            $this->_uCollegs = $this->getUsersInGroup(self::COLLEGS_ID);
        }
        return $this->_uCollegs;
    }

    /**
     * Список айдишников
     * */
    public function getCollegsList(){
        return $this->getUsersListInGroup(self::COLLEGS_ID,self::GROUP_APPROVED);
    }

    public function getSpecialities(){
        if($this->specialities == null){
            $this->specialities = $this->getUserN2NData(AI::getAI()->highloads['AgentsSpecialities']['TABLE_NAME'], AI::getAI()->highloads['Speciality']['TABLE_NAME'], 'UF_SPEC_ID');
        }
        return $this->specialities;
    }

    public function getLanguages(){
        if($this->languages == null){
            $this->languages = $this->getUserN2NData(AI::getAI()->highloads['AgentsLanguages']['TABLE_NAME'], AI::getAI()->highloads['Languages']['TABLE_NAME'], 'UF_LANG_ID');
        }
        return $this->languages;
    }

    public function getPresent($present_id){
        $arr = array();
        $present_id = (int)$present_id;

        $sql = '
            SELECT
                ug.ID,
                g.ID as GIFT,
                g.UF_NAME,
                g.UF_IMAGE,
                ug.UF_MESSAGE,
                ug.UF_FROM,
                ug.UF_TS
            FROM
                `table_presents` as ug
                INNER JOIN `table_gifts` as g ON ug.UF_GIFT = g.ID
            WHERE
                ug.UF_TO = '.$this->_user_id.'
                AND ug.ID = '.$present_id.'
            LIMIT 1
        ;';

        $rs = $GLOBALS['DB']->Query($sql);
        if($ar = $rs->Fetch()){
            $arr = array(
                'id' => $ar['ID'],
                'gift' => $ar['GIFT'],
                'name' => $ar['UF_NAME'],
                'image' => CFile::GetPath($ar['UF_IMAGE']),
                'from' => AI::getAI()->getAIUser($ar['UF_FROM'])->jsonArray(),
                'message' => $ar['UF_MESSAGE'],
                'date' => date('d.m.Y H:i',(int)$ar['UF_TS'])
            );
        }
        return $arr;
    }

    public function getPresentsPreview($limit = false){
        $arr = array();

        $sql = '
            SELECT
                ug.ID,
                g.ID as GIFT,
                g.UF_NAME,
                g.UF_IMAGE
            FROM
                `table_presents` as ug
                INNER JOIN `table_gifts` as g ON ug.UF_GIFT = g.ID
            WHERE
                ug.UF_TO = '.$this->_user_id.'
        ;';

        if((int)$limit > 0)
            $sql .= 'LIMIT '.$limit;

        $rs = $GLOBALS['DB']->Query($sql);
        while($ar = $rs->Fetch()){
            $arr[$ar['ID']] = array(
                'id' => $ar['ID'],
                'gift' => $ar['GIFT'],
                'name' => $ar['UF_NAME'],
                'image' => CFile::GetPath($ar['UF_IMAGE'])
            );
        }
        return $arr;
    }


    public function calendar(){
        if($this->_calendar == null)
            $this->_calendar = new AIEventsCalendar($this->_user_id);
        return $this->_calendar;
    }

}
