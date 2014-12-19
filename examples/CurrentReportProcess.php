<?php

class CurrentReportProcess extends CWidget {

    public $report;
    public $settings;

    public function init()
    {
//        parent::init();
        $this->settings = $this->controller->settings;
    }

    public function run()
    {

        $query = Yii::app()->db->createCommand('select count(*) as all_count, (select count(*) from query_'.$this->report->id.' where analyzed = 1 and level > 1) as checked_count from query_'.$this->report->id.' where level > 1')->queryRow();

        $wordstat = Yii::app()->db->createCommand('select count(*) as all_count, (select count(*) from wordstat_query_'.$this->report->id.' where analyzed = 1) as checked_count from wordstat_query_'.$this->report->id)->queryRow();

        $stat = Yii::app()->db->createCommand('select count(*) as all_count, (select count(*) from site_stat_'.$this->report->id.' where checked = 1) as checked_count from site_stat_'.$this->report->id)->queryRow();

        if ($query['all_count'] != $query['checked_count']) {
            $wordstatCount = $query['all_count']*$this->settings['wordstat_limit'];
        } else {
            $wordstatCount = $wordstat['all_count'];
        }

        if (($query['all_count'] == $query['checked_count']) && ($wordstat['all_count'] == $wordstat['checked_count'])) {
            $statCount = $stat['all_count'];
        } else {
            // примерное кол-во сайтов кот. найдётся - кол-во запросов*кол-во требуемых сайтов,
            // дополнительно делим примерно на 3, тк сайты будут одинаковые
            $statCount = ($query['all_count']*$this->settings['result_limit']) / 3;
        }

        $allCount = $query['all_count'] + $wordstatCount + $statCount;
        $checkedCount = $query['checked_count'] + $wordstat['checked_count'] + $stat['checked_count'];
        $readyPercent = round((100/$allCount) * $checkedCount, 2);

        $wordstatPageCount = ceil($this->settings['wordstat_limit'] / 50);
        $resultPageCount = ceil($this->settings['result_pagelimit'] / 50);

        $queryCount = ($query['all_count'] - $query['checked_count']) * $wordstatPageCount;
        $queryCount+=($wordstatCount - $wordstat['checked_count']) * $resultPageCount;

        $queryCount+=$statCount;

        $queryCount*=17; // на каждый запрос добавляем неск секунд
        $unixRemain = strtotime( '+' . $queryCount . ' second' );
        if (date('d.m.Y') == date('d.m.Y', $unixRemain)) {
            $timeRemain = 'сегодня, в ' . date('H:i', $unixRemain);
        } else {
            $timeRemain = date('d.m.Y H:i', $unixRemain);
        }

        $this->controller->renderPartial(
            '/blocks/reportProcess',
            array(
                'report' => $this->report,
                'query' => $query,
                'stat' => $stat,
                'wordstat' => $wordstat,
                'readyPercent' => $readyPercent,
                'timeRemain' => $timeRemain
            )
        );


    }

}