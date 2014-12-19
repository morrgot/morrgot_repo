<?php

/**
 * This is the model class for table "report".
 *
 * The followings are the available columns in table 'report':
 * @property string $id
 * @property string $name
 * @property string $action
 * @property string $created
 */
class Report extends CActiveRecord
{
    static $tableName = 'report';
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Report the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}


	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return self::$tableName;
	}

    public function beforeDelete() {

        $sql = array(
            "drop table if exists settings_{$this->id}",
            "drop table if exists stop_result_{$this->id}",
            "drop table if exists stop_wordstat_{$this->id}",
            "drop table if exists stop_site_{$this->id}",
            "drop table if exists site_stat_{$this->id}",
            "drop table if exists yandex_result_{$this->id}",
            "drop table if exists wordstat_query_{$this->id}",
            "drop table if exists query_{$this->id}",
        );
        foreach($sql as $s) {
            Yii::app()->db->createCommand( $s )->execute();
        }

        return true;

    }

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('name, action', 'length', 'max'=>255),

            array('active', 'numerical'),

            // Готовность отчёта по части wordstat+yandex_result
            array('ya_result_ready', 'numerical'),
            // готовность отчёта по посещаемости
            array('has_stat', 'numerical'),

            array('recalculation_time', 'safe'),

			array('created, finished, get_only_stat, get_stat, stoped, last_log_date', 'length', 'max'=>14),
            array('stop_result,stop_sites,started,stop_wordstat,region_res,region_ws,error', 'safe'),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, name, action, created', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'action' => 'Action',
			'created' => 'Created',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('id',$this->id,true);
		$criteria->compare('name',$this->name,true);
		$criteria->compare('action',$this->action,true);
		$criteria->compare('created',$this->created,true);
//		$criteria->compare('stoped',$this->stoped);
        //$criteria->pagination = false;

        $criteria->order = 'id desc';
		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
            'pagination' => array(
                'pageSize' => 100,
            ),
		));
	}

    /*
     * Переключаем модели в режим текущего отчёта
     */
    public function switchOnTables() {
        CActiveRecord::clearModelsCache();
        Settings::$tableName = 'settings_' . $this->id;
        Query::$tableName = 'query_' . $this->id;
        WordstatQuery::$tableName = 'wordstat_query_' . $this->id;
        YandexResult::$tableName = 'yandex_result_' . $this->id;
        StopSite::$tableName = 'stop_site_' . $this->id;
        StopWordstat::$tableName = 'stop_wordstat_' . $this->id;
        StopResult::$tableName = 'stop_result_' . $this->id;
        SiteStat::$tableName = $this->getSiteStatTable();
    }
    /*
     * Переключаем модели на системные таблицы (см метод switchOnTables)
     */
    public function switchOffTables() {
        CActiveRecord::clearModelsCache();
        Settings::$tableName = 'settings';
        Query::$tableName = 'query';
        WordstatQuery::$tableName = 'wordstat_query';
        YandexResult::$tableName = 'yandex_result';
        StopSite::$tableName = 'stop_site';
        StopWordstat::$tableName = 'stop_site';
        StopResult::$tableName = 'stop_site';
        SiteStat::$tableName = 'site_stat';
    }

    public function getYandexResultTable() {
        return 'yandex_result_' . $this->id;
    }

    public function getSiteStatTable() {
        return 'site_stat_' . $this->id;
    }

    public function createSiteStatTable() {

        $sql = "CREATE TABLE IF NOT EXISTS `site_stat_{$this->id}` (
          `id` varchar(255) NOT NULL DEFAULT '',
          `stat` int(11) DEFAULT NULL,
          `stat_source` varchar(50) DEFAULT NULL,
          `alexa_rating` int(11) DEFAULT NULL,
          `alexa_rating_local` int(11) DEFAULT NULL,
          `alexa_local_code` varchar(10) DEFAULT NULL,
          `yandex_metrika` int(11) DEFAULT NULL,
          `webomer_place` int(11) DEFAULT NULL,
          `webomer_coverage` double DEFAULT NULL,
          `webomer_traffic_male` double DEFAULT NULL,
          `webomer_traffic_search` double DEFAULT NULL,
          `liveinternet` int(11) DEFAULT NULL,
          `checked` tinyint(1) NOT NULL DEFAULT '0',
          `check_time` varchar(14) DEFAULT '',
          `neighbor_info` text,
          PRIMARY KEY (`id`),
          KEY `checked` (`checked`),
          KEY `stat` (`stat`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        Yii::app()->db->createCommand($sql)->execute();

    }

    /*
     * Создаём СТОП таблицы
     */
    public function createStopTables() {

        // стопы для поисковой выдачи:
        $sql = "CREATE TABLE IF NOT EXISTS `stop_result_{$this->id}` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `title` varchar(255) DEFAULT NULL,
          `is_used` tinyint(1) NOT NULL DEFAULT '1',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        Yii::app()->db->createCommand($sql)->execute();

        // стоп-сайты:ы
        $sql = "CREATE TABLE IF NOT EXISTS `stop_site_{$this->id}` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `title` varchar(255) DEFAULT NULL,
          `is_used` tinyint(1) NOT NULL DEFAULT '1',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        Yii::app()->db->createCommand($sql)->execute();

        $sql = "CREATE TABLE IF NOT EXISTS `stop_wordstat_{$this->id}` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `title` varchar(255) DEFAULT NULL,
          `is_used` tinyint(1) NOT NULL DEFAULT '1',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        Yii::app()->db->createCommand($sql)->execute();
    }


    function isParserNotUpdatedLongTime() {

        if (!$this->active) return false;
        if($this->started && time() > strtotime('+20 minutes', strtotime($this->last_log_date)))
            return true;

        return false;
    }

}