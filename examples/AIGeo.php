<?php

class AIGeo{
    protected static $_i = null;

    const COUNTRY_TABLE_NAME = 'sxgeo_country';
    const REGION_TABLE_NAME = 'sxgeo_regions';
    const CITY_TABLE_NAME = 'sxgeo_cities';

    public function detectLocation($ip = ''){
        $ip = trim($ip.'');
        if(empty($ip)) $ip = GetUserIp();
        $c = json_decode(file_get_contents('http://api.sypexgeo.net/json/'.$ip));
        $arr = array();
        if($c && (int)$c->region->id > 0){
            $arr['region'] = (array)$c->region;
            $arr['country'] = (array)$c->country;
            $arr['city'] = (array)$c->city;
        }
        return (!empty($arr)) ? $arr : false;
    }

    public function getTimezone(){
        if(!empty($_SESSION['geo']['true_location']['timezone'])){
            $timezone =  $_SESSION['geo']['true_location']['timezone'];
        }else{
            $timezone = $this->getTimezoneFromCookies();
            if($timezone) $_SESSION['geo']['true_location']['timezone'] = $timezone;
        }
        return $timezone;
    }

    public function getTimezoneFromCookies(){
        return (isset($_COOKIE['time_zone_offset'])) ? timezone_name_from_abbr('', -$_COOKIE['time_zone_offset'] * 60, $_COOKIE['time_zone_dst']) : false;
    }

    public function getDefaultCountry(){
        return $this->getCountry(AI::DEFAULT_COUNTRY);
    }

    public function getDefaultRegion(){
        return $this->getRegion(AI::DEFAULT_REGION);
    }

    public function getDefaultCity(){
        return $this->getCity(AI::DEFAULT_CITY);
    }

    public function getCity($ID){
        global $DB;

        $rs = $DB->Query('SELECT * FROM `'.self::CITY_TABLE_NAME.'` WHERE id = '.$ID.' LIMIT 1 ;');
        return $rs->Fetch();
    }

    public function getRegion($ID){
        global $DB;

        $rs = $DB->Query('SELECT * FROM `'.self::REGION_TABLE_NAME.'` WHERE id = '.$ID.' LIMIT 1 ;');
        return $rs->Fetch();
    }

    public function getRegionByCity($ID){
        global $DB;
        if((int)$ID < 1) return false;

        //$rs = $DB->Query('SELECT * FROM `'.self::REGION_TABLE_NAME.'` WHERE id = '.$city_ID.' LIMIT 1 ;');
        $rs = $DB->Query('SELECT * FROM `'.self::REGION_TABLE_NAME.'` as r INNER JOIN `'.self::CITY_TABLE_NAME.'` as c ON r.id=c.region_id where c.id = '.$ID.'  LIMIT 1 ;');
        return $rs->Fetch();
    }

    public function getCountry($ID){
        global $DB;
        if((int)$ID < 1) return false;

        $rs = $DB->Query('SELECT * FROM `'.self::COUNTRY_TABLE_NAME.'` WHERE id = '.$ID.' LIMIT 1 ;');
        return $rs->Fetch();
    }

    public function getCountryByISO($iso){
        global $DB;
        if(strlen($iso) < 2) return false;

        $rs = $DB->Query('SELECT * FROM `'.self::COUNTRY_TABLE_NAME.'` WHERE iso = \''.$DB->ForSql($iso).'\' LIMIT 1 ;');
        return $rs->Fetch();
    }

    public function getCountriesList(){
        global $DB;

        $rs = $DB->Query('SELECT id, iso, name_ru FROM `'.self::COUNTRY_TABLE_NAME.'` ORDER BY name_ru ;');
        $arr = array();
        while($ar = $rs->Fetch()){
            $arr[] = $ar;
        }
        return $arr;
    }

    public function getRegionsList($c_iso){
        global $DB;

        $rs = $DB->Query('SELECT id, iso, name_ru FROM `'.self::REGION_TABLE_NAME.'` where `country` = \''.$DB->ForSql($c_iso).'\'  ORDER BY name_ru ;');
        $arr = array();
        while($ar = $rs->Fetch()){
            $arr[] = $ar;
        }
        return $arr;
    }

    public function getCityList($r_id){
        global $DB;

        $rs = $DB->Query('SELECT `id`, `name_ru`, `region_id`, CONCAT(`lat`,\'x\',`lon`) as coordinates  FROM `'.self::CITY_TABLE_NAME.'` where `region_id` = '.(int)$r_id.'  ORDER BY name_ru ;');
        $arr = array();
        while($ar = $rs->Fetch()){
            $arr[] = $ar;
        }
        return $arr;
    }
	
	public function getCityListByCountry($c_iso){
        global $DB;

        $rs = $DB->Query('SELECT c.`id`, c.`name_ru` 
			FROM `'.self::CITY_TABLE_NAME.'` as c
			INNER JOIN `'.self::REGION_TABLE_NAME.'` as r ON c.region_id = r.id
			INNER JOIN `'.self::COUNTRY_TABLE_NAME.'` as cn ON r.country = cn.iso
			where cn.`iso` = \''.$DB->ForSql($c_iso).'\' 
		;');
        $arr = array();
        while($ar = $rs->Fetch()){
            $arr[] = $ar;
        }
        return $arr;
    }

    public static function getIt(){
        if(self::$_i == null){
            self::$_i = new self();
        }
        return self::$_i;
    }

    private function __construct(){

    }

}