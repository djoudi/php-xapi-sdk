<?php
/**
 * PHP Mosaico X API (XAPI) SDK
 * Innobit s.r.l.
 * web: http://www.innobit.it
 * mail: info@innobit.it
 */


namespace XAPISdk\Data\BusinessObjects;

class NaturaGiuridica extends ABaseBusinessObject {

    // region -- CONSTANTS --

    const CLASS_NAME = __CLASS__;

    // endregion

    // region -- MEMBERS --

    protected $_nome;
    protected $_descrizione;

    // endregion

    // region -- GETTERS/SETTERS --

    public function setNome($nome) {
        $this->_nome = $nome;
    }

    public function getNome() {
        return $this->_nome;
    }

    public function setDescrizione($descrizione) {
        $this->_descrizione = $descrizione;
    }

    public function getDescrizione() {
        return $this->_descrizione;
    }

    // endregion

    // region -- METHODS --

    public function toJson() {
        $toSerialize = array('nome' => $this->serializedField($this->_nome),
            'descrizione' => $this->serializedField($this->_descrizione));

        return json_encode($toSerialize);
    }

    protected function setFieldsFromJsonObj($jsonObj) {
        if (isset($jsonObj->nome))
            $this->setNome($jsonObj->nome);

        if (isset($jsonObj->descrizione))
            $this->setDescrizione($jsonObj->descrizione);
    }

    public static function getResourceName() {
        return 'natureGiuridiche';
    }

    // endregion

}