<?php

class QueryController extends BackEndController
{

    function __construct($id, $module) {
        parent::__construct($id, $module);
        $this->adminMenuItem = 'query';
    }

	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionView($id)
	{
		$this->render('view',array(
			'model'=>$this->loadModel($id),
		));
	}

	/**
	 * Creates a new model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 */
	public function actionCreate()
	{
		$model=new Query;

		// Uncomment the following line if AJAX validation is needed
		// $this->performAjaxValidation($model);

		if(isset($_POST['Query']))
		{
			$model->attributes=$_POST['Query'];
			if($model->save())
				$this->redirect(array('view','id'=>$model->id));
		}

		$this->render('create',array(
			'model'=>$model,
		));
	}

	/**
	 * Updates a particular model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 * @param integer $id the ID of the model to be updated
	 */
	public function actionUpdate()
	{
        $id = Yii::app()->request->getParam('query_id');
		$model=$this->loadModel($id);
        $query = trim( Yii::app()->request->getParam('query'));
        if (empty($query)) {
            $this->jsonOut(array('status' => 'error', 'error' => 'empty'));
        }

        $model->title = $query;
        $model->saveNode();

        $this->jsonOut(array('status' => 'ok', 'query' => $query));
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
    public function actionDeleteQuery()
    {
        $id = Yii::app()->request->getParam('id');
        $this->loadModel($id)->deleteNode();

    }

    public function actionActive()
    {
        $id = Yii::app()->request->getParam('queryID');
        $status = (int) Yii::app()->request->getParam('status');

        $model = $this->loadModel($id);
        if (!$model) return;

        $model->visible = $status;
        $model->saveNode();

        // отключаем всех потомков
        if ($status == 0)
        {
            $descendants = $model->descendants()->findAll();
            foreach($descendants as $d)
            {
                $d->visible = 0;
                $d->saveNode();
            }
        }

        /*
         * Включаем родителей
         */
        $activeParents = array();
        if ($status == 1)
        {
            $parents = $model->parent()->findAll();
            foreach($parents as $p)
            {
                $p->visible = 1;
                $p->saveNode();
                $activeParents[] = $p->id;
            }

            $descendants = $model->descendants()->findAll();
            foreach($descendants as $d)
            {
                $d->visible = 1;
                $d->saveNode();
            }
        }
        $this->jsonOut( array('activeParents' => $activeParents) );
    }

	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		$dataProvider=new CActiveDataProvider('Query');
		$this->render('index',array(
			'dataProvider'=>$dataProvider,
		));
	}

    public function importCSV( $data ) {

        set_time_limit(600);

        $transaction = Yii::app()->db->beginTransaction();

        try {
            Yii::app()->db->createCommand("delete from query")->execute();

    //        Query::model()->dele

            $root = new Query;
            $root->title = 'Запросы';
            $root->saveNode();

            $allItems = 0;
            foreach($data as $_items) {

    //            if ($allItems > 15) break;
                $allItems++;

                $items = explode(';', $_items);
                $idx = 0;
                $roots = array(0=>$root);

                foreach($items as $item) {
                    $idx++;

                    $item = trim($item);

                    if ($idx == 1) {
                        $_itm = $root->children()->find("title = :t", array('t' => $item));
                    } else {
                        $_itm = $roots[$idx-1]->children()->find("title = :t", array('t' => $item));
                    }

                    if (!$_itm) {

                        $_itm = new Query;
                        $_itm->title = trim( $item );

                        // заменяем комбинации на которые ругается yandex direct пробелами
                        $arr_replace = array("- ", " -", "/");
                        $_itm->title = str_replace($arr_replace,' ',$_itm->title);

                        $_itm->appendTo( $roots[$idx-1] );

                    }
                    $roots[$idx] = $_itm;
                }

            }
        } catch(Exception $e) {
            /**
             * Прям совсем все плохо пошло
             */
            $transaction->rollback();
        }

        $transaction->commit();

    }

	/**
	 * Manages all models.
	 */
	public function actionAdmin()
	{
        $uploadError = '';
        $mode = Yii::app()->request->getParam('mode');
        if ($mode == 'uploadCSV') {
            if (!($file = CUploadedFile::getInstanceByName('csvfile'))) {
                $uploadError = 'Укажите файл';
            } else {

                if (mb_strtolower($file->extensionName, 'UTF-8') != 'csv') {
                    $uploadError = 'Файл должен быть в формате CSV';
                } else {

                    $csvFile = Yii::app()->params->dataPath . '/q'.uniqid().'.csv';
                    if (!$file->saveAs($csvFile)) {

                        $uploadError = 'Не удалось загрузить файл';

                    } else {

                        $fileContent = file_get_contents($csvFile);
                        if (!mb_detect_encoding($fileContent, 'UTF-8', true)) {
                            $fileContent = iconv('windows-1251', 'UTF-8//IGNORE', $fileContent);
                        }

                        $this->importCSV( explode("\r", $fileContent)  );

                        unlink( $csvFile );
                    }
                }

            }
        }

        $type = Yii::app()->request->getParam('type', 'query');


        $criteria=new CDbCriteria;
        $criteria->order='t.lft'; // or 't.root, t.lft' for multiple trees
        $queries = Query::model()->findAll($criteria);
        $level=0;


		$model=new Query('search');
		$model->unsetAttributes();  // clear any default values
		if(isset($_GET['Query']))
			$model->attributes=$_GET['Query'];

        $renderMethod = 'render';
        if (Yii::app()->request->isAjaxRequest) $renderMethod = 'renderPartial';

        $css = array('query' => '', 'wordstat' => '', 'full' => '');
        $css[$type] = 'active';

		$this->$renderMethod('admin',array(
			'queries'=>$queries,
            'model' => $model,
            'type' => $type,
            'css' => $css,
            'uploadError' => $uploadError,
		));
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer the ID of the model to be loaded
	 */
	public function loadModel($id)
	{
		$model=Query::model()->findByPk($id);
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
		if(isset($_POST['ajax']) && $_POST['ajax']==='query-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
}
