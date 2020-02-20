<?php
/**
 * Created by PhpStorm.
 * project: hit
 * User: leav
 * Date: 20.02.2020
 * Time: 8:53
 * fileName: StoreXMLReader.php
 */

Class StoreXMLReader
{
    /**
     * @var XMLReader
     */
    protected $reader;
    /**
     * @param $filename
     * @return array
     */
    public function parse($filename) {

        if (!$filename) return array();

        $this->reader = new XMLReader();
        $this->reader->open($filename);

        // begin read XML
        while ($this->reader->read()) {

            if ($this->reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($this->reader->name == 'bki_response') {

                $version = $this->reader->getAttribute('version');
                $partnerid = $this->reader->getAttribute('partnerid');
                $datetime = $this->reader->getAttribute('datetime');

                while (!($this->reader->name == 'bki_response' && $this->reader->nodeType == XMLReader::END_ELEMENT)) {

                    if ($this->reader->name == 'response') {

                        $response_num = $this->reader->getAttribute('num');
                        $xmlDoc     = new DOMDocument('1.0', 'windows-1251');
                        $bki_response = $xmlDoc->createElement('bki_response');
                        $bki_response->setAttribute("version", (string)$version);
                        $bki_response->setAttribute("partnerid",(string)$partnerid);
                        $bki_response->setAttribute("datetime", (string)$datetime);

                        $xmlRespons = $xmlDoc->createDocumentFragment();
                        $xmlRespons->appendXml($this->reader->readOuterXML());

                        $bki_response->appendChild($xmlRespons);
                        $xmlDoc->appendChild($bki_response);
                        // print_r($xmlDoc->saveXML());exit();
                        $xmlDoc->save('out/xml/response_'.$response_num.'.xml');

                        while (!($this->reader->name == 'response' && $this->reader->nodeType == XMLReader::END_ELEMENT)) {
                           /** пробигаем до конца блока  */
                            $this->reader->read();
                        }
                        /** перемещаем на другой блок **/
                        $this->reader->read();
                    }
                    $this->reader->read();
                }
            }

            $this->reader->read();
        } // while
    } // func
}

