<?php

class ReportController extends BackEndController
{

    function __construct($id, $module) {
        parent::__construct($id, $module);
        $this->adminMenuItem = 'report';
    }


    /*
     * Просмотр отчёта
     */
	public function actionView($id) {

        // класс для <body>
        $this->bodyClass = 'reportBody';

        $report = $this->loadModel($id);
        $report->switchOnTables();

        $root = Query::model()->roots()->find();

        $_queries = $root->children()->findAll();
        $_queries = CHtml::listData($_queries, 'id', 'title');

        $repPart = $this->renderPartial(
            '/report/repPart',
            array(
                'queries' => $_queries, 'report' => $report
            ),
            true
        );

        $reportView = $this->renderPartial('sepReport', array('repPart' => $repPart, 'report' => $report), true);

        if (Yii::app()->request->isAjaxRequest) {

            echo $reportView;
            Yii::app()->end();

        } else {
            $this->render('view', array(
                'reportView' => $reportView,
                'report' => $report
            ));
        }

	}

    /*
    * Просмотр конкурентов
    */
    public function actionCompetitors($id) {
        // класс для <body>
        $this->bodyClass = 'reportBody';

        $report = $this->loadModel($id);
        $report->switchOnTables();

        $report = Report::model()->findByPk($id);

        $order = Yii::app()->request->getParam('order', 'rating');

        $orderResult = 'count_res desc';

        if ($order == 'avg-pos-k') {

            $orderResult = 'posk_sum_res desc';

        } elseif ($order == 'stat') {

            $orderResult = 'siteStat.stat desc';

        } elseif ($order == 'alexa')
            $orderResult = 'ISNULL(siteStat.alexa_rating) asc, alexa_rating asc';
        elseif ($order == 'metrika')
            $orderResult = 'ISNULL(siteStat.yandex_metrika) asc, yandex_metrika desc';
        elseif ($order == 'webomer')
            $orderResult = 'ISNULL(siteStat.webomer_place) asc, webomer_place asc';
        elseif ($order == 'liveinternet')
            $orderResult = 'ISNULL(siteStat.stat) asc, liveinternet desc';

        $report->switchOnTables();

        $query = Query::model()->find('level = 2'); // Все

        // получаем всех потомков (всю ветку) искомого запроса
        $children = $query->descendants()->findAll('visible=1');
        $childrenCount = 0;
        foreach($children as $c) {
            $childrenCount++;
            $ids[] = $c->id;
        }
        $ids[] = $query->id;

        $sitesCount = 0;
        $sites = array();
        if (!empty($ids)) {


            //
            $criteria = new CDbCriteria;
            $criteria->with = array('wordstat', 'siteStat');
            $criteria->together = true;
            $criteria->distinct = true;
            $criteria->select = 't.*, count(t.id) as count_res, sum(posk) as posk_sum_res';
            $criteria->addInCondition('wordstat.query_id', $ids); // учитываем сам запрос и всю ветку запросов ниже запроса по которому строится отчет
            $criteria->order = $orderResult;
            $criteria->group = 'site';
            $criteria->limit = 2000; // максимальное количество строк (сайтов) отображаемое на странице отчета

            $dependency = new CDbCacheDependency('select CONCAT_WS(",", recalculation_time, has_stat,finished) from report where id='.$report->id);
            $sites = YandexResult::model()
                ->cache(5000, $dependency, 3)
                ->findAll( $criteria );

            $urlCount = count($sites);


            foreach($sites as $site) {
                $sitesCount+=$site['count_res']; // общее количество уникальных пар запрос-сайт для анализируемой ветки
            }
        }
////////////////////
        // для каждого отдела из отчета собираем массив последователей + сама группа
        $departments = Query::model()->findAll( 'level = 3 order by id');
        $sites = array();
        //$arr_competitors = array(); // массив имя_сайта -> array(2,3) - столбцы, где сайт конкурент
        foreach($departments as $d) {
            $query = Query::model()->findByPk( $d->id );
            // получаем всех потомков (всю ветку) искомого запроса
            $quey_titles[] = trim($query->title);
            $children = $query->descendants()->findAll();
            $ids = array();
            foreach($children as $c) {
                $ids[] = $c->id;
            }
            $ids[] = $query->id;

            // определяем самые популярные сайты в отделе
            // !!! надо добавить проверку, что сайты не в стоп листе
            $criteria = new CDbCriteria;
            $criteria->with = array('wordstat');
            $criteria->together = true;
            $criteria->distinct = true;
            $criteria->select = 't.site, count(t.id) as count_res, sum(posk) as posk_sum_res';
            $criteria->addInCondition('wordstat.query_id', $ids); // учитываем сам запрос и всю ветку запросов ниже запроса по которому строится отчет
            $criteria->order = 'sum(posk) DESC, count(t.id) DESC';
            $criteria->group = 'site';
            $criteria->limit = 10; // оставляем самых сильных конкурентов в отделе

            $dep_sites = YandexResult::model()->findAll( $criteria );

            // 1. оставляем только те сайты, которые отсутсвуют в таблице Sites, имеют в ней свойство is_competitor = true или NULL
            // 2. собираем массив с информацие в каких по счету столбцах (отделах) сайт является конкурентом
            $arr_competitors = array(); // только конкуренты
            foreach($dep_sites as $dep_site) {
                $cur_site = Sites::model()->findByPk( $dep_site->site );
                // если сайт в отчете конкурент, но отсутсвует в таблице Sites, то добавляем и в следующем условии проверяем конкурентность
                if (empty($cur_site)) {
                    $newsite = new Sites;
                    $newsite->name=$dep_site->site;
                    $newsite->save();
                }
                // проверяем конкурентность для вновь добавленных или не проверенных
                if (empty($cur_site) || $cur_site->is_competitor==null) {
                    $is_competitor = 0;
                    $g = new Gateway('http://'.$dep_site->site, null);
                    if (g == false) {continue;}
                    $content = $g->getContent();
                    $words = array('магазин','корзин');
                    foreach ($words as $word) {
                        if(stristr($content, $word) || stristr(iconv("Windows-1251","UTF-8",$content), $word)) {$is_competitor = 1;}
                    }

                    $title_re = '#<title>(.*?)</title>#is';
                    preg_match($title_re, $content, $arr_title);
                    $title = $arr_title[1];

                    // определяем кодировку title и приводим к utf-8
                    $encoding_list = array('utf-8','windows-1251');
                    foreach ($encoding_list as $item) {
                        $sample = iconv($item, $item, $title);
                        if (md5($sample) == md5($title)) { $title = iconv($item, 'utf-8', $title); break; }
                    }

                    //echo $dep_site->site." ".$is_competitor."\n";
                    // записываем результат проверки конкурентности
                    $cur_site = Sites::model()->findByPk( $dep_site->site );
                    $cur_site->last_check=time();
                    $cur_site->is_competitor=$is_competitor;
                    $cur_site->title=substr($title,0,255);
                    $cur_site->save();
                }
                // проверяем конкурентность еще раз, т.к. сайт мог добавиться и/или проанализироваться
                $cur_site = Sites::model()->findByPk( $dep_site->site );
                if ($cur_site->is_competitor) {
                    // 1.
                    // собираем массив вида [Название отдела] => array(объект 'сайт1', объект 'сайт2'...)
                    $dep_site['title'] = $cur_site->title; // передаем в отчет Title конкурента
                    $arr_competitors[$query->title][] = $dep_site;
                    // 2.
                    //$arr_competitors[$dep_site->site][] = $td_count;
                }
            }
            if (!empty($arr_competitors)) {$sites = array_merge ($sites, $arr_competitors); }
        }
//        echo '<pre>';
//        print_r($sites);
//        echo '</pre>';
////////////////////


        $stopWordstat = array();
        foreach(StopWordstat::model()->findAll() as $sw) {
            $stopWordstat[$sw->title] = 1;
        }

        $_settings = Settings::model()->findAll();
        $regions = array();
        foreach($_settings as $s) {
            $regions[] = $s->id;
        }

        if(count($quey_titles)>0){
            foreach($quey_titles as $k=>$qTitle){
                if(strlen($qTitle) < 1)
                    unset($quey_titles[$k]);
            }

            $pr_cond = (count($quey_titles)>0) ? " AND priceCategory.name IN ('".implode("','",$quey_titles)."')" : '';
        }

        //$this->p($quey_titles);
        $cs_in_regions = CostSite::model()->with('CostSiteRegions','priceCategory')->findAll('CostSiteRegions.id IN ('.implode(',',$regions).')'.$pr_cond);

        $cs_reg = array();
        foreach($cs_in_regions as $cs){
            $cs_reg[] = $cs->name;
        }

        $_stopSites = StopSite::model()->findAll();
        $stopSites = array();
        foreach($_stopSites as $s) {
            $stopSites[$s->title] = 1;
        }

        // заголовки столбцов таблицы
        $title = array(
            'site' => 'сайт',
            'freq' => 'частота',
            'kpos' => 'к. поз',
            'stat' => 'посещ.'
        );

        // список столбцов, которые выводятся в таблице помимо основных
        $listFieldKeys = array(
            'return $site->siteStat ? number_format($site->siteStat->stat, 0, " ", " ") : \'\';',
        );

        // расширяем столбцы таблицы статистикой
        $statExt = $this->statSorting( $order );
        $title = array_merge($title, $statExt['title']);
        $listFieldKeys = array_merge($listFieldKeys, $statExt['fields']);


        if (!empty($_GET['excel']) && $_GET['excel'] == 1) {

            $dataPath = Yii::app()->params->dataPath;
            $dataUrl = Yii::app()->params->dataUrl;


            $objPHPExcel = Helper::getPHPExcel();
            $objPHPExcel->getProperties()->setCreator("AiloveSeoService")
                ->setTitle("AiloveSeo Report");
            $objPHPExcel->getActiveSheet()->setTitle('Запросы');
            $sheet = $objPHPExcel->setActiveSheetIndex(0);

            $row = 1;
            $column = -1;
            foreach($title as $k => $t) {
                $column++;
                $sheet->setCellValueByColumnAndRow($column, $row, strip_tags($t));
            }
            foreach($sites as $site) {
                $row++;
                $column = 0;
                $sheet->setCellValueByColumnAndRow($column++, $row, $site->site);
                $sheet->getCellByColumnAndRow($column-1, $row)->getHyperlink()->setUrl('http://' . $site->site);

                $sheet->setCellValueByColumnAndRow($column++, $row, round(((100/$sitesCount)*$site->count_res),2));
                $sheet->setCellValueByColumnAndRow($column++, $row, round($site->posk_sum_res, 2));

                foreach($listFieldKeys as $k) {
                    $v = eval( $k );
                    $sheet->setCellValueByColumnAndRow($column++, $row, $v);
                }
            }

            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            $xlsFileName = 'MyExcel.xls';
            $xlsFile = $dataPath . '/' . $xlsFileName;
            $objWriter->save($xlsFile);

            $this->jsonOut( array('status' => 'ok', 'file' => $dataUrl . '/' . $xlsFileName) );
            Yii::app()->end();

        } else {
            $this->render(
                'repCompetitors',
                array(
                    'stopWordstat' => $stopWordstat,
                    'stopSites' => $stopSites,
                    'report' => $report,
                    'title' => $title,
                    'listFieldKeys' => $listFieldKeys,
                    'sites' => $sites,
                    'sitesCount' => $sitesCount,
                    'query' => $query,
                    'childrensCount' => $childrenCount,
                    'order' => $order,
                    'costSites' => $cs_reg
                )//,
                //true
            );
        }
    }

    /*
     * Пересчёт отчёта по новым стоп-элементам
     */
    public function actionRecalculation( $id ) {

        // тащим отчёт
        $report = Report::model()->findByPk($id);

        // переключаем модели на таблицы отчёта
        $report->switchOnTables();

        // кол-во удалённых результатов
        $delResults = 0;

        // обрабатываем новые стоп-сайты
        $sites = StopSite::model()->findAll('is_used=0');
        foreach($sites as $site) {
            // ищем и удаляем результаты отчёта с новым стоп-сайтом
            $criteria = new CDbCriteria();
            $criteria->addInCondition('site', array($site->title, 'www.' . $site->title));
            $delResults+=YandexResult::model()->deleteAll( $criteria );

            $criteria = new CDbCriteria();
            $criteria->addInCondition('id', array($site->title, 'www.' . $site->title));

            // помечаем стоп-сайт как использованный
            $site->is_used = 1;
            $site->save();
        }

        // обрабатываем новые стоп-слова
        $wordstatQueries = StopWordstat::model()->findAll( 'is_used=0' );
        foreach($wordstatQueries as $wq) {
            $criteria = new CDbCriteria();
            $criteria->compare('title', $wq->title);
            $delResults+=WordstatQuery::model()->deleteAll( $criteria );
            // помечаем стоп-запрос как использованный
            $wq->is_used = 1;
            $wq->save();
        }

        $report->recalculation_time = date('c');
        $report->save();

        $this->jsonOut( array('status' => 'ok', 'delResults' => $delResults) );

    }

    public function actionDeleteStopElement( $id, $element, $mode ) {

        $report = Report::model()->findByPk($id);
        $report->switchOnTables();

        if ($mode == 'wordstat')
            $model = StopWordstat::model()->findByPk($element);
        else
            $model = StopSite::model()->findByPk($element);

        if (!$model->is_used)
            $model->delete();

    }

    public function actionAddStopElement( $id ) {

        $report = Report::model()->findByPk($id);
        $report->switchOnTables();

        $_item = Yii::app()->request->getParam('item');
        $type = Yii::app()->request->getParam('stopType');
        $status = (int) Yii::app()->request->getParam('status');

        if (!in_array($type, array('StopSite', 'StopResult', 'StopWordstat'))) die;

        if ($status == 1) {
            $itm = new $type;
            $itm->title = $_item;
            $itm->is_used = 0;
            $itm->save();
        } else {
            $type::model()->deleteAllByAttributes( array('title' => $_item ) );
        }

        $this->jsonOut( array('status' => 'ok') );


    }

    /*
     * Просмотр и добавление стоп-элементов у отчёта
     */
    public function actionStopElements( $id, $mode ) {

        $report = Report::model()->findByPk($id);

        $report->switchOnTables();

        if ($mode == 'site') {
            $modelName = 'StopSite';
        } elseif ($mode == 'wordstat') {
            $modelName = 'StopWordstat';
        }

        $modelList = new $modelName('search');
        $modelList->unsetAttributes();
        if(isset($_GET[$modelName]))
            $modelList->attributes=$_GET[$modelName];

        $createModel = new $modelName;



        if (isset($_POST[$modelName])) {
            $createModel->attributes = $_POST[$modelName];
            $createModel->is_used = 0;
            if ($createModel->save()) {
                $this->redirect( Yii::app()->createUrl('Report/StopElements', array('id' => $id, 'mode' => $mode)) );
            }
        }

        $this->render('stop',array(
            'report' => $report,
            'mode' => $mode,
            'model' => $modelList,
            'createModel' => $createModel,
        ));

    }

    public function statSorting( $innerOrder = null )
    {

        $title = array();
        $fields = array();

        if (!empty($_POST['ShowOption']['alexa_global']) || $innerOrder == 'alexa') {
            $fields['alexa_global'] = 'return $site->siteStat ? $site->siteStat->alexa_rating : "";';
            $title['alexa_global'] = '<span rel="tooltip" data-title="Позиция в рейтинге Alexa">Alexa</span>';
        }

        if (!empty($_POST['ShowOption']['yandex_metrika']) || $innerOrder == 'metrika') {
            $fields['yandex_metrika'] = 'return $site->siteStat ? number_format($site->siteStat->yandex_metrika,0," ", " ") : "";';
            $title['yandex_metrika'] = 'Я.Метрика';
        }

        if (!empty($_POST['ShowOption']['webomer_place']) || $innerOrder == 'webomer') {
            $fields['webomer_place'] = 'return $site->siteStat ? $site->siteStat->webomer_place : "";';
            $title['webomer_place'] = '<span rel="tooltip" data-title="Позиция в рейтинге Вебомера">Вебомер</div>';
        }

        if (!empty($_POST['ShowOption']['liveinternet']) || $innerOrder == 'liveinternet') {
            $title['liveinternet'] = 'Лайвинтернет';
            $fields['liveinternet'] = 'return $site->siteStat ? number_format($site->siteStat->liveinternet, 0, " ", " ") : "";';
        }

        if (!empty($_POST['ShowOption']['alexa_local'])) {
            $title['alexa_local'] = '<span rel="tooltip" data-title="Локальный рейтинг Alexa">Alexa.Local</span>';
            $fields['alexa_local'] = 'return $site->siteStat ? $site->siteStat->alexa_rating_local : "";';
        }

        if (!empty($_POST['ShowOption']['webomer_coverage'])) {
            $title['webomer_coverage'] = '<img src="/img/webomer.png" alt=""> Охват';
            $fields['webomer_coverage'] = 'return $site->siteStat ? $site->siteStat->webomer_coverage : "";';
        }

        if (!empty($_POST['ShowOption']['webomer_gender'])) {
            $title['webomer_gender'] = '<img src="/img/toilet.png" alt="" rel="tooltip" data-title="Демография"><span style="display:none;">Демография</span>';
            $fields['webomer_gender'] = 'return $site->siteStat ? $site->siteStat->webomer_traffic_male . " / " . (100-$site->siteStat->webomer_traffic_male) . "%" : "";';
        }

        if (!empty($_POST['ShowOption']['webomer_search'])) {
            $title['webomer_search'] = '<img src="/img/magnifier.png" alt="" rel="tooltip" data-title="Поисковый трафик"><span style="display:none;">Поисковый трафик</span>';
            $fields['webomer_search'] = 'return $site->siteStat ? $site->siteStat->webomer_traffic_search . "%" : "";';
        }

        return array(
            'title' => $title,
            'fields' => $fields,
        );

    }

    public function actionLoadPart() {

        $reportID = (int) Yii::app()->request->getParam('report_id');
        $id = (int) Yii::app()->request->getParam('id');
        $report = Report::model()->findByPk($reportID);

        $order = Yii::app()->request->getParam('order', 'rating');

        $orderResult = 'count_res desc';

        if ($order == 'avg-pos-k') {

            $orderResult = 'posk_sum_res desc';

        } elseif ($order == 'stat') {

            $orderResult = 'siteStat.stat desc';

        } elseif ($order == 'alexa')
            $orderResult = 'ISNULL(siteStat.alexa_rating) asc, alexa_rating asc';
        elseif ($order == 'metrika')
            $orderResult = 'ISNULL(siteStat.yandex_metrika) asc, yandex_metrika desc';
        elseif ($order == 'webomer')
            $orderResult = 'ISNULL(siteStat.webomer_place) asc, webomer_place asc';
        elseif ($order == 'liveinternet')
            $orderResult = 'ISNULL(siteStat.stat) asc, liveinternet desc';

        $_global_stopSites = StopSite::model()->findAll();
        $globalStopSites = array();
        foreach($_global_stopSites as $g){
            $globalStopSites[$g->title] = 1;
        }

        $report->switchOnTables();

        $query = Query::model()->findByPk( $id );

        // получаем всех потомков (всю ветку) искомого запроса
        $children = $query->descendants()->findAll('visible=1');
        $childrenCount = 0;
        foreach($children as $c) {
            $childrenCount++;
            $ids[] = $c->id;
        }
        $ids[] = $query->id;



        $_settings = Settings::model()->findAll();
        $regions = array();
        foreach($_settings as $s) {
            $regions[] = $s->id;
        }

        $sitesCount = 0;
        $sites = array();
        if (!empty($ids)) {

            //
            $criteria = new CDbCriteria;
            $criteria->with = array('wordstat', 'siteStat');
            $criteria->together = true;
            $criteria->distinct = true;
            $criteria->select = 't.*, count(t.id) as count_res, sum(posk) as posk_sum_res';
            $criteria->addInCondition('wordstat.query_id', $ids); // учитываем сам запрос и всю ветку запросов ниже запроса по которому строится отчет
            $criteria->order = $orderResult;
            $criteria->group = 'site';
            $criteria->limit = 2000; // максимальное количество строк (сайтов) отображаемое на странице отчета

            $dependency = new CDbCacheDependency('select CONCAT_WS(",", recalculation_time, has_stat,finished) from report where id='.$report->id);
            $sites = YandexResult::model()
                ->cache(5000, $dependency, 3)
                ->findAll( $criteria );

            $urlCount = count($sites);


            foreach($sites as $site) {
                $sitesCount+=$site['count_res']; // общее количество уникальных пар запрос-сайт для анализируемой ветки
            }
        }

        $stopWordstat = array();
        foreach(StopWordstat::model()->findAll() as $sw) {
            $stopWordstat[$sw->title] = 1;
        }

        $pr_cond = (trim($query->title) != 'Все') ? " AND priceCategory.name = '".trim($query->title)."'" : '';

        $cs_in_regions = CostSite::model()->with('CostSiteRegions','priceCategory')->findAll('CostSiteRegions.id IN ('.implode(',',$regions).')'.$pr_cond);
        $cs_reg = array();
        /*if(trim($query->title) != 'Все')
            echo 'CostSiteRegions.id IN ('.implode(',',$regions).')'.$pr_cond.'--'.count($cs_in_regions).'!!';*/
        foreach($cs_in_regions as $cs){
            $cs_reg[] = $cs->name;
        }

        $_stopSites = StopSite::model()->findAll();
        $stopSites = array();
        foreach($_stopSites as $s) {
            $stopSites[$s->title] = 1;
        }

        // заголовки столбцов таблицы
        $title = array(
            'site' => 'сайт',
            'freq' => 'частота',
            'kpos' => 'к. поз',
            'stat' => 'посещ.'
        );
        // список столбцов, которые выводятся в таблице помимо основных
        $listFieldKeys = array(
            'return $site->siteStat ? number_format($site->siteStat->stat, 0, " ", " ") : \'\';',
        );

        // расширяем столбцы таблицы статистикой
        $statExt = $this->statSorting( $order );
        $title = array_merge($title, $statExt['title']);
        $listFieldKeys = array_merge($listFieldKeys, $statExt['fields']);


        if (!empty($_GET['excel']) && $_GET['excel'] == 1) {

            $dataPath = Yii::app()->params->dataPath;
            $dataUrl = Yii::app()->params->dataUrl;


            $objPHPExcel = Helper::getPHPExcel();
            $objPHPExcel->getProperties()->setCreator("AiloveSeoService")
                ->setTitle("AiloveSeo Report");
            $objPHPExcel->getActiveSheet()->setTitle('Запросы');
            $sheet = $objPHPExcel->setActiveSheetIndex(0);

            $row = 1;
            $column = -1;
            foreach($title as $k => $t) {
                $column++;
                $sheet->setCellValueByColumnAndRow($column, $row, strip_tags($t));
            }
            foreach($sites as $site) {
                $row++;
                $column = 0;
                $sheet->setCellValueByColumnAndRow($column++, $row, $site->site);
                $sheet->getCellByColumnAndRow($column-1, $row)->getHyperlink()->setUrl('http://' . $site->site);

                $sheet->setCellValueByColumnAndRow($column++, $row, round(((100/$sitesCount)*$site->count_res),2));
                $sheet->setCellValueByColumnAndRow($column++, $row, round($site->posk_sum_res, 2));

                foreach($listFieldKeys as $k) {
                    $v = eval( $k );
                    $sheet->setCellValueByColumnAndRow($column++, $row, $v);
                }
            }

            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            $xlsFileName = 'MyExcel.xls';
            $xlsFile = $dataPath . '/' . $xlsFileName;
            $objWriter->save($xlsFile);

            $this->jsonOut( array('status' => 'ok', 'file' => $dataUrl . '/' . $xlsFileName) );
            Yii::app()->end();

        } else {
            $content = $this->renderPartial(
                'repPartContent',
                array(
                    'stopWordstat' => $stopWordstat,
                    'stopSites' => $stopSites,
                    'report' => $report,
                    'title' => $title,
                    'listFieldKeys' => $listFieldKeys,
                    'sites' => $sites,
                    'sitesCount' => $sitesCount,
                    'query' => $query,
                    'childrensCount' => $childrenCount,
                    'order' => $order,
                    'globalStopSites'=>$globalStopSites,
                    'costSites' => $cs_reg
                ),
                true
            );
        }



        /*
         * если есть у запрашиваемого запроса дети - выводим селект
         */
        if ($queries = $query->children()->findAll()) {
            $queries = CHtml::listData($queries, 'id', 'title');
            $repPart = $this->renderPartial('repPart', array('queries' => $queries, 'report' => $report), true);
        } else {
            $repPart = '';
        }

        $wordstatPopover = null;

        $wordstats = WordstatQuery::model()->findAllBySql("select * from wordstat_query_{$report->id} where query_id = {$query->id} order by exposure desc");
        if ($wordstats) {
            $wordstatContent = '<div class="popOverWordstat"><table>';
            foreach($wordstats as $w) {
                $wordstatContent.='<tr><td class="word">'.$w['title'];
                //$stopTip = isset($stopWordstat[$w['title']]) ? 'Этот элемент добавлен в стоп фильтры. Чтобы удалить его - нажмите здесь' : 'Нажмите здесь, чтобы добавить этот элемент в стоп фильтры';
                //$wordstatContent.= '<a onclick="stopItem(\''.$w['title'].'\', this, \'StopWordstat\'); return false;" data-placement="left" target="_blank" rel="tooltip" style="margin-left: 7px; opacity: .3;cursor: pointer;" data-title="'.$stopTip.'" data-status="'.(isset($stopWordstat[$w['title']]) ? 1 : 0).'"><i class="'.((isset($stopWordstat[$w['title']])) ? 'icon-remove-sign' : 'icon-remove-circle').'"></i></a>';
                $wordstatContent.= '</td><td class="cnt">'.$w['exposure'].'</td></tr>';
            }
            $wordstatContent.='</table></div>';

            $wordstatPopover = $this->widget('bootstrap.widgets.TbButton', array(
                'label'=>'',
                'icon' => 'icon-list',
                'type'=>'',
                'size' => 'small',
                'htmlOptions'=>array(
                    'title' => 'Список Wordstat запросов для<br>«' . $query->title . '»',
                    'data-title'=>'Wordstat запросы',
                    'data-content' => $wordstatContent,
                    'rel'=>'popover',
                    'data-html' => 'true',
                    'data-placement' => 'left',
                ),
            ), true);
        }

        // подключаем скрипты
        $scripts = '';
        Yii::app()->clientScript->renderBodyBegin( $scripts );
        Yii::app()->clientScript->renderBodyEnd( $scripts );

        $this->jsonOut( array(
            'content' => $content,
            'scripts' => $scripts,
            'repPart' => $repPart,
            'wordstatPopover' => $wordstatPopover,
            'childrensCount' => $childrenCount,
            'sortOnly' => Yii::app()->request->getParam('sortOnly'),
            'query' => $query->title,
            'order' => $order,
        )
        );

    }

	/*
	 * Запуск нового отчёта
	 */
	public function actionCreate()
	{
        $queries = Query::model()->findAll('visible=1 and level > 1');

        if(empty($queries)){
            $this->jsonOut( array('status' => 'query') );
        }


        $reportName = Yii::app()->request->getParam('reportName');
        $par = Yii::app()->request->getParam('par');

        $i = 0;
        foreach($par as $res_reg=>$param){
            if(empty($param['queue'])) continue;

            $sqlCreate = array();
            $report = new Report;

            $region_inf =  Settings::model()->find('result_region = :res_reg',array('res_reg'=>$res_reg));
            $region_name = $region_inf->wordstat_region;
            $report->name = (!empty($reportName)) ? $reportName.' '.$region_name : $region_name;

            $report->action = 'Инициализация';

            $report->get_stat = isset($param['get_stat']) ? (int) $param['get_stat'] : 0;
            if($report->get_stat == 1) $report->name .= ' с посещаемостью';
            $report->active = 1;
            $report->created = date('YmdHis');
            $report->save();

            if($i < 1){
                $cur_rep_id = $report->id;
                $first_report = clone $report;
            }
            $i++;


            $reportId = $report->id;


            $sqlCreate[] = "CREATE TABLE IF NOT EXISTS `query_{$report->id}` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `root` int(10) unsigned DEFAULT NULL,
                  `lft` int(10) unsigned NOT NULL,
                  `rgt` int(10) unsigned NOT NULL,
                  `level` smallint(5) unsigned NOT NULL,
                  `title` varchar(255) DEFAULT NULL,
                  `id_parent` varchar(255) DEFAULT NULL,
                  `position` varchar(255) DEFAULT NULL,
                  `visible` varchar(255) NOT NULL DEFAULT '1',
                  `analyzed` tinyint(1) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `root` (`root`),
                  KEY `lft` (`lft`),
                  KEY `rgt` (`rgt`),
                  KEY `level` (`level`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

            $sqlCreate[] = "insert into query_{$report->id} select * from query where visible=1";
            $sqlCreate[] = "update query_{$report->id} set analyzed = 0;";

            $sqlCreate[] = "CREATE TABLE `settings_{$report->id}` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `wordstat_limit` int(11) DEFAULT NULL,
              `result_limit` int(11) DEFAULT NULL,
              `wordstat_pagelimit` int(11) DEFAULT NULL,
              `result_pagelimit` int(11) DEFAULT NULL,
              `wordstat_region` varchar(255) DEFAULT NULL,
              `result_region` varchar(255) DEFAULT NULL,
              `posk` double DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

            $sqlCreate[] = "insert into settings_{$report->id} select id, wordstat_limit, result_limit, wordstat_pagelimit, result_pagelimit, wordstat_region, result_region, posk from settings where result_region = $res_reg";


            $sqlCreate[] = "CREATE TABLE IF NOT EXISTS `wordstat_query_{$report->id}` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `query_id` int(11) unsigned DEFAULT NULL,
                  `title` varchar(255) DEFAULT NULL,
                  `exposure` bigint(20) DEFAULT NULL,
                  `added` varchar(14) DEFAULT NULL,
                  `analyzed` tinyint(1) NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  KEY `query_id` (`query_id`),
                  CONSTRAINT `wordstat_query_{$report->id}_ibfk_1` FOREIGN KEY (`query_id`) REFERENCES `query_{$report->id}` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

            $sqlCreate[] = "CREATE TABLE IF NOT EXISTS `yandex_result_{$report->id}` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `title` varchar(255) DEFAULT NULL,
                  `description` varchar(255) DEFAULT NULL,
                  `site` varchar(255) DEFAULT NULL,
                  `url` varchar(255) DEFAULT NULL,
                  `wordstat_id` int(11) unsigned DEFAULT NULL,
                  `pos` int(11) DEFAULT NULL,
                  `posk` double DEFAULT NULL,
                  `added` varchar(14) DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `wordstat_id` (`wordstat_id`),
                  KEY `site` (`site`),
                  CONSTRAINT `yandex_result_{$report->id}_ibfk_1` FOREIGN KEY (`wordstat_id`) REFERENCES `wordstat_query_{$report->id}` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";


            // создаём таблицы статистики
            $report->createSiteStatTable();
            // таблицы для СТОП слов
            $report->createStopTables();

            $sqlCreate[] = "insert into stop_wordstat_{$reportId} (id, title, is_used) (select id, title, 1 from stop_wordstat)";
            $sqlCreate[] = "insert into stop_site_{$reportId} (id, title, is_used) (select id, title, 1 from stop_site)";
            $sqlCreate[] = "insert into stop_result_{$reportId} (id, title, is_used) (select id, title, 1 from stop_result)";

            foreach($sqlCreate as $sql)
                Yii::app()->db->createCommand($sql)->execute();

        }

        $html = $this->widget('application.widgets.CurrentReport', array('report' => $first_report), true);

        $this->jsonOut( array('status' => 'ok', 'html' => $html, 'report_id' => $cur_rep_id) );
	}

    public function actionStartReport( $id ) {

        ignore_user_abort(true);
        set_time_limit(0);

        $this->runCCommand( array('report', 'id:'.$id) );
    }

    public function actionStopReport($id) {

        Yii::app()->cache->set( 'report_stop_' . $id, 1, 180 );

    }



    public function actionLog()
    {
        /*
        $reportID = (int) Yii::app()->request->getParam('report_id');
        $report = Report::model()->findByPk($reportID);

        $logFile = Yii::app()->params->dataPath . '/log-' . $report->id . '.txt';
        ob_start();
        passthru('tail -n 500 '.$logFile);
        $log = ob_get_clean();
        $log = explode("\n", trim($log));
        $log = implode("<br>", array_reverse($log));
        echo $log;
        */
    }

	/**
	 * Updates a particular model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 * @param integer $id the ID of the model to be updated
	 */
	public function actionUpdate($id)
	{
		$model=$this->loadModel($id);

		// Uncomment the following line if AJAX validation is needed
		// $this->performAjaxValidation($model);

		if(isset($_POST['Report']))
		{
			$model->attributes=$_POST['Report'];
			if($model->save())
				$this->redirect(array('view','id'=>$model->id));
		}

		$this->render('update',array(
			'model'=>$model,
		));
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id)
	{
		$this->loadModel($id)->delete();

		// if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
		if(!isset($_GET['ajax']))
			$this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
	}

	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		$dataProvider=new CActiveDataProvider('Report');
		$this->render('index',array(
			'dataProvider'=>$dataProvider,
		));
	}

    public function actionProcess()
    {
        $reportID = (int) Yii::app()->request->getParam('report_id');
        $report = Report::model()->findByPk($reportID);

        if ($report->stoped) {
            echo '<script>document.location.reload(true);</script>';
            Yii::app()->end();
        }

        $this->widget('application.widgets.CurrentReportProcess', array('report' => $report));
        Yii::app()->end();
    }

	/**
	 * Manages all models.
	 */
	public function actionAdmin()
	{
		$model=new Report('search');
		$model->unsetAttributes();  // clear any default values

        $model->stoped = 0;

		if(isset($_GET['Report']))
			$model->attributes=$_GET['Report'];

		$this->render('admin',array(
			'model'=>$model,
		));
	}

    public function actionReportRestart( $id ) {

        // todo: переписать на $this->runCCommand()

        ignore_user_abort(true);
        set_time_limit(0);

        $report = Report::model()->findByPk($id);
        if (empty($report)) return;

        $report->error = 0;
        $report->save();

        $commandPath = Yii::app()->getBasePath() . DIRECTORY_SEPARATOR . 'commands';
        $runner = new CConsoleCommandRunner();
        $runner->addCommands($commandPath);
        $args = array('init.php', 'report', 'id:'.$report->id);
        $runner->run($args);
    }

    public function actionStartStat( $id ) {

        $report = Report::model()->findByPk($id);
        if (empty($report)) return;

        $report->error = 0;
        $report->get_only_stat = 1;
        $report->active = 1;
        $report->has_stat = 0;
        $report->finished = null;
        $report->save();

        $isForceStart = isset($_GET['force']) ? $_GET['force'] : 0;
        $this->runCCommand( array('report', 'id:'.$id, 'force:' . $isForceStart) );

    }

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer the ID of the model to be loaded
	 */
	public function loadModel($id)
	{
		$model=Report::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param CModel the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='report-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}

}
