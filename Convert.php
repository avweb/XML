<?php
/**
 * Created by PhpStorm.
 * User: leav
 * Date: 17.01.2019
 * Time: 11:15
 */

use CommonLib\ClifFunc;
use CommonDB\Settings;

require_once __DIR__ . '/../vendor/autoload.php';
Settings::createInstance('/etc/equ/config.ini');

include_once 'lib/writeXML.php';
include_once 'lib/checkXsd.php';
include_once 'lib/ZipHelper.php';

/**
 * Class Convert
 */
class Convert
{
    /**
     *
     */
    const PREF_FILE = 'FPC';
    /**
     * @var null $autoIncNameFile
     */
    private $autoIncNameFile = 0;

    /**
     * @var string
     */
    public $partnerCode = '';

    /**
     * @var int
     */
    public $xmlLine = 100000;
    /**
     * @var string
     */
    public $filename = 'in.txt';

    /**
     * @var array
     */
    protected $fields = ['lastname', 'firstname', 'birthday', 'docno', 'docreg', 'pan', 'date_issue', 'date_expire',
        'id_card_type', 'funding_source', 'id_payment_system'];

    /**
     * @return string
     */
    protected function getXsdFolderPath()
    {
        return __DIR__ . '/shema/';
    }

    /**
     * @return string
     */
    protected function getInFolderPath()
    {
        return __DIR__ . '/in/';
    }

    /**
     * @return string
     */
    protected function getOutFolderPath()
    {
        return __DIR__ . '/out/';
    }

    /**
     * Convert constructor.
     */
    function __construct()
    {
    }

    function add_leading_zero($value, $threshold = 2) {
        return sprintf('%0' . $threshold . 's', $value);
    }

    /**
     * Возвращаем хеш
     * @param $data
     * @return string
     */
    protected function sha256($data)
    {
        return mb_convert_case(hash('sha256', $data, false), MB_CASE_UPPER, "UTF-8");
    }

    /**
     * @return string
     */
    protected function CreateFileNameXml()
    {
        $D = date('Ymd',time());
        $nameFile = self::PREF_FILE.'_'.$this->partnerCode.'_'.$D.'_'.$this->add_leading_zero($this->autoIncNameFile,4);
        $this->autoIncNameFile++;
        return $nameFile;
    }

     protected function normalization($string){
         $string = trim($string);
         $string = preg_replace('/(Ё)/iu', 'Е', $string);
         return  $string;
     }

    /**
     * @param $data
     * @return array
     */
    protected function setDataXml($data,$index)
    {
        foreach ($data as $key=>$val) {
            $val  = preg_replace('/[^0-9A-Za-zА-Яа-я\-\ ]/i', '', $val);
            if (($key>=0)&&($key<=4))
            {
                $data[$key] = $this->sha256($this->normalization($val));
            }
            else
            $data[$key] =$val;

        }
        $data=[
            'info' => [
                "num" => $index,
                "title_part" => [
                    "lastname"          => $data[0]??'',
                    "firstname"         => $data[1]??'',
                    "birthday"          => $data[2]??'',
                    "docno"             => $data[3]??'',
                    "docreg"            => $data[4]??'',
                ],
                "card" => [
                    "pan"               => $data[6]??'',
                    "date_issue"        => $data[8]??'',
                    "date_expire"       => $data[9]??'',
                    "id_card_type"      => $data[10]??'',
                    "funding_source"    => $data[11]??'',
                    "id_payment_system" => 1
                ]
            ]
        ];

        IF (!empty($data[7])) {
            $data['info'][ "card"]["parent_pan"] = $data[7];
        }

        return $data;
    }


    /**
     * Запус задачи на чтение и формирования xml  файла +
     * проверка блоков по схеме и если проверка прошла пишем блок в файл
     */
    public function run()
    {
        // читаем файл построчно
        $inFile = new \SplFileObject($this->getInFolderPath() . $this->filename, 'r');

        $i = 0;
        $index = 0;
        $xm = null;
        while (!$inFile->eof()) {
            $row = $inFile->current();

            if (!$row){
                $inFile->next();
                continue;
            }
            $data = explode('	', $row);

            if (isset($data[1]) &&  $data[1]== 'FIRSTNAME')
            {
                $inFile->next();
                continue;
            }

            $dataBlock = $this->setDataXml($data,$index);
            $index++;
            if ($i == 0) {
                $xm = $this->CreateXmlFile($this->CreateFileNameXml());
            }

            if ($i < $this->xmlLine) {
                if ($xm instanceof writeXML) {
                    $this->WriteXml($xm, $dataBlock);
                }
                $i++;
            } else {
                $i = 0;
                if ($xm instanceof writeXML)
                {
                    if ($row) $this->WriteXml($xm, $dataBlock);
                }
            }
            $inFile->next();
        }

        if ($xm instanceof writeXML){
            $this->CloseFileXml($xm);
            $xm = null;
        }
        return $this;
    }

    /**
     * Создаем и фозвращаем указатель
     * @param $fileName
     * @return writeXML
     */
    private function CreateXmlFile($fileName)
    {
        $version = [
            'fpc' => [
                'version' => '1.0',
                '@@notCloseTag' => 1
            ]
        ];
        $xm = new writeXML($this->getOutFolderPath() . $fileName . '.xml','1.0','utf-8');
        $xm->header($version);
        return $xm;
    }

    /**
     * Получаем указатель на класс и пишем блок в файл
     * @param writeXML $xm
     * @param $info
     * @throws Exception
     */
    private function WriteXml(writeXML $xm, $info)
    {
        $xsd = new checkXsd($info, $this->getXsdFolderPath() . 'shemaInfo.xsd');
        if ($xsd->check()) {
            $xm->body($info);
        } else {
            $this->saveLog($xsd->getError());
        }
    }

    /**
     * Получаем указатель на класс writeXML и закрываем файл xml
     * @param writeXML $xm
     */
    private function CloseFileXml(writeXML $xm,$data=[])
    {
        return $xm->footer($data);
    }

    /**
     * Пишем в лог те наименование полей (блоков) которые по какой либо причине не прошли по блокам
     * @param $errorMsg
     */
    protected function saveLog($errorMsg){
        file_put_contents($this->getOutFolderPath() . 'info.log', $errorMsg."\n", FILE_APPEND);
    }

    public function outZip(){
        $path = $this->getOutFolderPath();
        $filename = $this->getOutFolderPath().'/../'.
            date('Y-m-d_H-i', time()).'_'.'out'.'_.zip';
        ZipHelper::zipDir($path, $filename);
    }
}

// создаем класс
$c = new Convert();
//код партера
$c->partnerCode = '02A';
// указываем имя файла для чтения
$c->filename = 'in.txt';
//  указываем какое количество строк будет в файле
$c->xmlLine = 100;
//  запускаем скрипт на обработку
$c->run()->outZip();


