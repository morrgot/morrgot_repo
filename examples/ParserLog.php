<?php

class ParserLog {

    public $reportCode;
    public $report;
    public $file; // log file

    public function __construct( $report = null ) {

        if (!empty($report)) {
            $this->report = $report;
            $this->setLogFileName( 'report-' . $report->id );
            $this->reportCode = 'rep-' . $report->id . '-' . date('Y.m.d.H.i');
        } else {
            $this->reportCode = 'parser.log.'.date('Y.m.d.H.i') . '.' . uniqid();
        }
    }

    function setLogFileName( $name ) {
        $this->file = Yii::app()->params->dataPath . '/log-' . $name . '.txt';
        $this->initLogFile();
    }

    function initLogFile() {
        file_put_contents($this->file, "init " . "\n" . date('c') . "\n", FILE_APPEND);
        @chmod($this->file, 0777);
    }

    public function write( $message ) {
        $this->out($message);
    }

    public function end( $message ) {

        $this->out($message);

        if (!empty($this->report)) {
            $this->report->error = 1;
            $this->report->action = 'Завершен с ошибкой';
            $this->report->save();
        }

        if(get_class($this->report) == 'Report'){
            $next_report = $this->report->find('active=1 and started = 0 and error=0  order by id asc');
            $command_type = 'report';
        }
        if(!empty($next_report))
            BackEndController::runCCommand(array($command_type, 'id:'.$next_report->id));

        Yii::app()->end();
    }

    public function delimiter( $count = 1 ) {
        if ($count<1) $count = 1;
        for($i=1;$i<=$count;$i++)
            $this->out("----------------------------");
    }

    public function out( $mess ) {

        if (empty($this->file))
            $this->setLogFileName( uniqid() );

        if (!empty($this->report)) {
            $this->report->last_log_date = date('YmdHis');
            $this->report->save();
        }

        $mess = date('H:i:s') . ' - ' . $mess . "\n";
        //echo $mess;
        file_put_contents($this->file, $mess, FILE_APPEND);
    }

}