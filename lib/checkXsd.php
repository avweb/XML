<?php
/**
 * Created by PhpStorm.
 * User: leav
 * Date: 17.01.2019
 * Time: 13:14
 */
/**
 * Класс для проверки XML по  XSD
 * на вход поступает массив описывающий структуру и данные XML
 * @author musa
 */
class checkXsd
{
    /**
     * new XMLWriter();
     * @var \XMLWriter
     */
    private $_xml;
    /**
     * new DOMDocument();
     * @var \DOMDocument
     */
    private $_domDoc;
    /**
     * валиден XML?
     * @var bool
     */
    private $_isValid;
    /**
     * текст ошибки
     * @var string
     */
    private $_errorMsg;

    /**
     * Конструктор
     * @param array $data       - массив описывающий структуру XML
     * @param string $xsd       - путь до XSD
     * @param string $version   - версия XML, &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; по умолчанию 1.0
     * @param string $encoding  - кодировка XML, по умолчанию windows-1251
     * @throws Exception
     */
    public function __construct(Array $data, $xsd, $version = '1.0', $encoding = 'windows-1251')
    {
        // файл существует?
        if( !file_exists($xsd) ){
            throw new Exception('open file error');
        }

        $this->_xml = new \XMLWriter();
        $this->_xml->openMemory(); // пищем xml  в память
        $this->_xml->setIndent(true);
        $this->_xml->startDocument($version, $encoding);
        $this->genXML($data);

        libxml_use_internal_errors(true); // Отключение ошибок libxml чтобы не валились варнинги
        $this->_domDoc = new \DOMDocument();
        $this->_domDoc->encoding = $encoding;
        $this->_domDoc->version = $version;
        $this->_domDoc->loadXML( $this->_xml->outputMemory(TRUE) ); // загружаем в $this->_domDoc XML
        $this->_isValid = $this->_domDoc->schemaValidate($xsd);
        /*
         * если xml невалиден, то пишем ошибки в $this->_errorMsg
         */
        if(!$this->_isValid){
            $errors = libxml_get_errors();
            foreach($errors as $v){
                $this->_errorMsg=trim($v->message);
            }
        }
    }

    /**
     * Деструктор
     */
    public function __destruct()
    {
        unset($this->_xml);
        unset($this->_domDoc);
    }

    /**
     * Запускает проверку по XSD
     * @return bool
     */
    public function check()
    {
        return $this->_isValid;
    }

    /**
     * Возвращает текст ошибки проверки по XSD
     * @return string
     */
    public function getError()
    {
        return $this->_errorMsg;
    }

    /**
     * Выполняет преобразование массив -> XML
     * @param array $data   - массив описывающий структуру XML
     * @return string       - XML
     */
    private function genXML(Array $data = NULL)
    {
        if( !empty($data) ){
            foreach ($data as $k => $v){
                if(!isset($v)) continue;
                if($k === '@attributes') continue;
                if($k === '@value') continue;

                if(is_array($v)){
                    // множественный тег
                    if(is_numeric(key($v))){
                        foreach ($v as $vv){
                            $this->genXml([$k => $vv]);
                        }
                    }else{
                        $this->_xml->startElement($k);
                        if(!empty($v['@attributes'])){
                            foreach ($v['@attributes'] as $attr => $val){
                                if(isset($val)){
                                    $this->_xml->writeAttribute($attr, $val);
                                }
                            }
                        }
                        if(array_key_exists('@value', $v)){
                            $this->_xml->text($v['@value']);
                        }else{
                            $this->genXml($v);
                        }

                        $this->_xml->endElement();
                    }
                }else{
                    $this->_xml->writeElement($k, $v);
                }
            }
        }
    }
}
