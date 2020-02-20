<?php
/**
 * Created by PhpStorm.
 * User: leav
 * Date: 17.01.2019
 * Time: 12:55
 */
class writeXML
{
    private $xml;                       // new XMLWriter();
    private $shapkaWritedFlg = FALSE; // выведена шапка?
    private $outputFile;                // файл для вывода
    const tabulator = " ";            // табулятор
    private $version = '1.0';         // версия xml
    private $encoding;                  // кодировка XML
    const indent = TRUE;              // включить/выключить отступы

    /**
     * Конструктор
     * @param string $outputFile    - файл куда пишем XML
     * @param string $version       - версия XML, по умолчанию = '1.0'
     * @param string $encoding      - кодировка, по умолчанию  = 'windows-1251'
     */
    public function __construct($outputFile, $version = '1.0', $encoding = 'windows-1251' )
    {
        $this->encoding = $encoding;
        $this->outputFile = $outputFile;
        $this->version = $version;
        $this->xml = new \XMLWriter();
    }

    /**
     * функция нужна для того, чтобы файл создавался
     * только при наличии данных для записи
     */
    private function openuri()
    {
        $this->xml->openUri($this->outputFile);
        $this->xml->startDocument($this->version, $this->encoding);
        $this->xml->setIndentString(self::tabulator);
        $this->xml->setIndent(self::indent);
    }

    /**
     * вывод "шапки" в XML
     * @param array $data - массив описывающий структуру XML с данными
     */
    public function header(Array $data = NULL)
    {
        if( !$this->shapkaWritedFlg && !empty($data) ){
            $this->openuri();
            $this->genXML($data);
            $this->shapkaWritedFlg = TRUE;
        }
    }

    /**
     * вывод центральной части в XML
     * как правило этот блок повторяется несколько раз, например <info>
     * @param array $data - массив описывающий структуру XML с данными
     */
    public function body(Array $data = NULL)
    {
        if( !empty($data) ){
            $this->genXML($data);
        }
    }

    /**
     * пишем "футер" в XML
     * @param array $data - массив описывающий структуру XML с данными
     */
    public function footer(Array $data = NULL)
    {
        if( !empty($data) ){
            $this->genXML($data);
        }
        $this->xml->endElement();
        $this->xml->endDocument();
        $this->output(); // сброс буфера файла на диск
    }

    /**
     * Функция для преобразования массива в XML
     * @param array $data - массив описывающий структуру XML с данными
     */
    private function genXML(Array $data = NULL)
    {
        if( !empty($data) ){
            foreach ($data as $k => &$v){
                if(!isset($v)) continue;
                if($k === '@attributes') continue;
                if($k === '@value') continue;
                if($k === '@@notCloseTag') continue;
                if($k === '@@closeTag'){
                    if($v>0){
                        for($i=0;$i<$v;$i++){
                            $this->xml->endElement();
                        }
                    }
                    continue;
                }

                if(is_array($v)){
                    // множественный тег
                    if(is_numeric(key($v))){
                        foreach ($v as &$vv){
                            $this->genXml([$k => $vv]);
                        }
                    }else{
                        $this->xml->startElement($k);
                        if(!empty($v['@attributes'])){
                            foreach ($v['@attributes'] as $attr => &$val){
                                if(isset($val)){
                                    $this->xml->writeAttribute($attr, $val);
                                }
                            }
                        }
                        if(array_key_exists('@value', $v)){
                            // на вход XMLWriter принимает только UTF-8
                            $this->xml->text($v['@value']);
                        }else{
                            $this->genXml($v);
                        }

                        if(!isset($v['@@notCloseTag'])){
                            $this->xml->endElement();
                        }
                    }
                }else{
                    $this->xml->writeElement($k, $v);
                }
            }
        }
    }

    /**
     * сброс буфера на диск
     */
    public function output()
    {
        $this->xml->flush();
        unset($this->xml);
    }
}
