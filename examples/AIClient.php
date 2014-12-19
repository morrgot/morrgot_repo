<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 21.08.14
 * Time: 17:18
 */
class AIClient extends AIUser implements IAIUser{

    protected $_tableId;

    protected $_additional_keys = array('phones');

    function __construct($ID){
        parent::__construct($ID);

        //$this->_user_inf_keys = array_merge($this->_user_inf_keys,$this->_additional_keys);

        global $DB;

        $this->_hl = AI::getAI()->highloads['Clients'];

        /*if(!empty($_SESSION['ai']['user']) && $this->itsMe($ID)){
            $client = $_SESSION['ai']['user'];
        }else{*/

            $rs = $DB->Query("
                SELECT
                    ID,
                    UF_MOB_PHONE,
                    UF_AVATAR,
                    UF_AVATAR_M,
                    UF_AVATAR_S,
                    UF_LOCATION,
                    UF_BIRTHDATE,
                    UF_ABOUT,
                    UF_CREDO,
                    UF_ANEKDOT,
                    UF_BOOK,
                    UF_FILM
                FROM
                    `table_clients`
                WHERE
                    UF_USER_ID = $ID
            ;");

            if($ar = $rs->Fetch()){
                $client = array(
                    'id' => $ID,
                    'location' => $ar['UF_LOCATION'],
                    'birthdate' => $ar['UF_BIRTHDATE'],
                    'about' => $ar['UF_ABOUT'],
                    'credo' => $ar['UF_CREDO'],
                    'book' => $ar['UF_BOOK'],
                    'film' => $ar['UF_FILM'],
                    'anekdot' => $ar['UF_ANEKDOT'],
                    'tableId' => $ar['ID'],
                    'phones' => array(
                        'mobile' => explode('_',$ar['UF_MOB_PHONE']),
                        'work' => $ar['UF_WORK_PHONE']
                    ),
                    'avatar' => array(
                        'file_id' => $ar['UF_AVATAR'],
                        'b' => CFile::GetPath($ar['UF_AVATAR']),
                        'm' => ($ar['UF_AVATAR_M']) ? $ar['UF_AVATAR_M'] : self::NO_AVATAR_M,
                        's' => ($ar['UF_AVATAR_S']) ? $ar['UF_AVATAR_S'] : self::NO_AVATAR_S
                    ),
                    'location' => $ar['UF_LOCATION']
                );



                /*if($this->itsMe($ID))
                    $_SESSION['ai']['user'] = $client;*/
            }
       // }
        $this->_user_info = array_merge($this->_user_info, $client);
        $this->_user_inf_keys = array_merge($this->_user_inf_keys, array_keys($client));

        $this->_tableId = $this->tableId = $client['tableId'];


        $this->_user_type = 'client';
        $this->_uploadDir = $_SERVER['DOCUMENT_ROOT']."/upload/clients/client".$this->_user_id;
    }

    public static function makeHref($id){
        return '/client/'.$id.'/';
    }

    public function getHref(){
        return self::makeHref($this->_user_id);
    }

}
