<?php
/* @var modX $modx */
define('MODX_API_MODE', true);
require '../../index.php';
//ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
@ini_set("upload_max_filesize", "150M");
@ini_set("post_max_size", "150M");
@ini_set("max_execution_time", "1200"); //20 min.
@ini_set("max_input_time", "1200"); //20 min.
@ini_set('memory_limit', '2560M');
@ini_set('auto_detect_line_endings', 1);
@set_time_limit(0);
error_reporting(E_ALL);

$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_FATAL);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');


class oneS_import
{

    private  $categories;
    /**
     * @var mixed
     */
    private  $modx;

    public function __construct(&$modx, $config)
    {
        $params = [];
        $this->modx = $modx;
        $this->config = $config;
        $this->categories = [];
        $this->process();
    }

    private function process(){
        $file = $this->config['import_file'];

        if (file_exists($file)) {
            $timeDiff = time() - filemtime($file);
            $hoursDiff = $timeDiff / (60 * 60); // Количество прошедших часов

            if ($hoursDiff > 24) {
                echo '⚠️ Этот файл устарел и не нуждается в обновлении.';
                exit; // Останавливаем выполнение скрипта
            }
        } else {
            echo '❌ Файл не существует.';
            exit;
        }
    }


    public function getCategories()
    {
        //$data = $this->readLargeXMLFile($this->config['import_file'], 'Группа');
        $data = $this->xmlFileToArray($this->config['import_file']);
        $this->recursiveArrayCat($data["Классификатор"]['Группы']['Группа']);


        foreach ($this->categories as $item) {
            echo $this->uCategoryModx($item);
        }


    }

    private function uCategoryModx($item)
    {
        /* @var msCategory $res */
        /* @var modResource $res */
        $tv = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_1c_id'], 'value' => $item["Id"]]);
        $log = "";


        if ($tv) {
            $res = $this->modx->getObject('modResource', $tv->contentid);
            $alias = $res->cleanAlias($item['Naimenovanie']);
            $res->set('alias', $alias);

            $res->set('template', $this->config['catalog_tpl']);
            if ($item['parent']) {
                $tvParent = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_1c_id'], 'value' => $item['parent']]);
                $res->set('parent', $tvParent->contentid);
            }
            //$res->set('parent', $this->config['catalog_id']);

            $res->set('isfolder', 1);
            $res->set('class_key', 'msCategory');
            //$res->set('published', '1');
            $res->save();
            $log .= "Cat: UP {$res->id} {$res->pagetitle} - {$res->parent} - GroupID:{$tv->value}<br>";

        } else {
            $res = $this->modx->newObject('modResource');
            $res->set('pagetitle', $item['Naimenovanie']);
            $alias = $res->cleanAlias($item['Naimenovanie']);
            $res->set('alias', $alias);
            $res->set('parent', $this->config['catalog_id']);
            $res->set('template', $this->config['catalog_tpl']);
            $res->save();

            $res->setTVValue($this->config['tv_1c_id'], $item['Id']);
            $log .= "Cat: CR {$res->id} {$this->config['catalog_id']}<br>";
        }

        return "<pre>$log</pre>";


    }


    public function getProducts()
    {

        $data = $this->xmlFileToArray($this->config['import_file']);
        $products = $data["Классификатор"]['Товары']['товар'];

        $perPage = 350;
        $offset = $_GET['offset'] ?? 0;
        $countAll = count($products);
        echo "$offset is $countAll<br>";




        $products = array_slice($products, $offset, $perPage);
        if ($products) {
            foreach ($products as $item) {
                echo $this->uProductModx($item);
            }
            $offset += $perPage;

            if ($countAll >= $offset) {

                echo "<script>window.location.href = 'http://komfort.forestlj.beget.tech/assets/1c_files/cron.php?method=getProducts&offset=$offset';</script>";
            }


        } else {
            die("STOP!");
        }


    }

    public function getOffers()
    {

        $data = $this->xmlFileToArray($this->config['offers_file']);
        $products = $data["Классификатор"]["ПакетПредложений"]['Предложения']['Предложение'];

        $perPage = 300;
        $offset = $_GET['offset'] ? $_GET['offset'] : 0;
        $countAll = count($products);
        echo "$offset is $countAll<br>";


        $products = array_slice($products, $offset, $perPage);



        if ($products) {
            foreach ($products as $item) {

                echo $this->uProductOfferModx($item);
            }
            $offset += $perPage;



            if ($countAll >= $offset) {



                echo "<script>window.location.href = 'http://komfort.forestlj.beget.tech/assets/1c_files/cron.php?method=getOffers&offset=$offset';</script>";
            }


        } else {
            die("STOP!");
        }


    }

    private function uProductOfferModx($item)
    {
        /* @var msProduct $res */
        //1c_SHtrihkod
        //1c_Artikul
        $log = "";

        $tv = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_1c_id'], 'value' => $item["Ид"]]);


        if ($tv) {
            $res = $this->modx->getObject('msProduct', $tv->contentid);
            $price_desc = $item["Цены"]["Цены"]["Представление"];
            $price = $item["Цены"]["Цены"]["ЦенаЗаЕдиницу"];
            $ostatok = $item["Количество"];



            $art = is_array($item["Артикул"]) ? implode(",",$item["Артикул"]) : $item["Артикул"];
            $res->set('price', $price);
            $res->set('article', $art);
            $res->save();
            $res->setTVValue($this->config['tv_1c_Count'], $ostatok);
            $res->setTVValue($this->config['tv_1c_PriceDesk'], $price_desc);
            $res->setTVValue($this->config['tv_1c_Artikul'], $art);

            $log .= "OFFER: UP {$res->id} {$res->parent} pr: {$price_desc} | $art<br>\n";

        } else{
            $log .= "OFFER NO FIND ID: {$item["Ид"]}<br>\n";
        }
        return "<pre>$log</pre>";
    }

    private function uProductModx($item)
    {
        /* @var msProduct $res */
        /* @var modResource $res */
        //1c_SHtrihkod
        //1c_Artikul
        $log = "";


        $tv = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_1c_id'], 'value' => $item["Ид"]]);

        if ($tv) {


            $res = $this->modx->getObject('msProduct', $tv->contentid);
            $res->set('pagetitle', $item['Наименование']);
            $alias = $res->cleanAlias($item['Наименование'] . "-" . $res->getTVValue($this->config['tv_1c_Artikul']));

            $res->set('alias', $alias);
            $res->set('show_in_tree', 0);
            $res->set('parent', $this->config['catalog_id_other']);
            $res->set('content', implode("<br>", $item['Описание']));

            if ($item['Группы']["Ид"]) {
                $tvParent = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_1c_id'], 'value' => $item['Группы']["Ид"]]);

                if ($tvParent) {
                    $res->set('parent', $tvParent->contentid);
                    $res->set('published', 1);
                }
            }


            $res->save();
            $res->setTVValue($this->config['tv_1c_SHtrihkod'], $item["Штрихкод"]);
            $art = is_array($item["Артикул"]) ? implode(",",$item["Артикул"]) : $item["Артикул"];
            $res->setTVValue($this->config['tv_1c_Artikul'], $art);

            //Добавление изображения


            $imgMessage = "";
            if($item["Картинка"]){
                $img = MODX_BASE_PATH."assets/1c/webdata/{$item["Картинка"]}";

                $gallery = array(
                    'id' => $res->id,
                    'name' => htmlspecialchars( "1C_image - {$res->pagetitle}"),
                    'description' => htmlspecialchars( "1C_image - {$res->pagetitle}"),
                    'rank' => 0,
                    'file' => $img
                );

                $upload = $this->modx->runProcessor('gallery/upload', $gallery, array(
                    'processors_path' => MODX_CORE_PATH.'components/minishop2/processors/mgr/'
                ));
                $imgMessage = $upload->getResponse()['message']??'Картинка привязанная к продукту';

            } else{
                $imgMessage  = "Не нашел тег Картинка!";
            }



            $log .= "1CID: {$item["Ид"]} Product: UP {$res->id} {$res->parent} \t {$imgMessage}<br>\n";



        } else {
            $res = $this->modx->newObject('modResource');
            $alias = $res->cleanAlias($item['Наименование'] . "-" . $res->getTVValue($this->config['tv_1c_Artikul']));
            $res->set('template', $this->config['product_tpl']);
            $res->set('pagetitle', $item['Наименование']);
            $res->set('alias', $alias);
            $res->set('parent', $this->config['catalog_id_other']);
            if ($item['Группы']["Ид"]) {
                $tvParent = $this->modx->getObject('modTemplateVarResource', ['tmplvarid' => $this->config['tv_1c_id'], 'value' => $item['Группы']["Ид"]]);

                if ($tvParent) {
                    $res->set('parent', $tvParent->contentid);
                    $res->set('published', 1);
                }
            }


            $res->set('class_key', 'msProduct');
            $res->save();
            $res->setTVValue($this->config['tv_1c_id'], $item["Ид"]);
            $log .= "1CID: {$item["Ид"]} Product: CR {$res->id} {$res->parent}<br>\n";
        }

        return "<pre>$log</pre>";

    }


    /**
     * Рекурсивно обрабатывает массив для формирования иерархии категорий.
     *
     * @param array $array Входной массив для обработки.
     * @param string $parent Значение родительского элемента, используется для построения иерархии.
     *
     * @return void
     */
    private function recursiveArrayCat($array, $parent = '')
    {

        /*if ($parent){
            var_dump($array[0]);
        }*/

        foreach ($array as $item) {
            if (!isset($item['Наименование'])) continue;
            //if ($item['Наименование']!="Обувь") continue;
            $toArr = ['Id' => $item['Ид'], 'parent' => $parent, "Naimenovanie" => $item['Наименование']];
            $this->categories[] = $toArr;

            if (isset($item['Группы']) && is_array($item['Группы']['Группа'])) {
                $dRecurs = @($item['Группы']['Группа'][0]) ? $item['Группы']['Группа'] : [$item['Группы']['Группа']];
                $this->recursiveArrayCat($dRecurs, $item['Ид']);
            }

        }

    }


    private function xmlFileToArray($xmlPath)
    {
        $xml = file_get_contents($xmlPath);

        $array = json_decode(json_encode(simplexml_load_string($xml)), true);
        return $array;
    }

}

$config = [
    'import_file' => MODX_ASSETS_PATH . '1c_files/import0_1.xml',
    'offers_file' => MODX_ASSETS_PATH . '1c_files/offers0_1.xml',
    'catalog_id' => 8,
    'catalog_id_other' => 67,
    'catalog_tpl' => 2,
    'product_tpl' => 3,
    'tv_1c_id' => 20,
    'tv_1c_SHtrihkod' => 21,
    'tv_1c_Artikul' => 22,
    'tv_1c_Count' => 19, //sklad
    'tv_1c_PriceDesk' => 23,
];

$import = new oneS_import($modx, $config);
$method = $_GET['method'] ?? false;
switch ($method) {
    case "getCategories":
        $import->getCategories();
        break;
    case "getProducts":
        $import->getProducts();
        break;
    case "getOffers":
        $import->getOffers();
        break;
}

//$import->getProducts();
