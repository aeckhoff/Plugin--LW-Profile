<?php

class lw_profile extends lw_advanced_plugin
{
    public function __construct() 
    {
        parent::__construct();
        $this->lwInAuth = lw_in_auth::getInstance();
    }
    
    public function init()
    {
        if (!$this->lwInAuth->isLoggedIn()) {
            $this->pageReload(lw_page::getInstance($this->params['loginid'])->getUrl());
        }
        $this->setDefaultCommand('showForm');
        $this->setCommandIdentifier('pcmd');
    }
    
    public function wf_showForm()
    {
        if ($this->isError()) {
            $name = trim($this->request->getRaw('name'));
        }
        else {
            $name = $this->lwInAuth->getUserdata("name");
        }
        $template = $this->loadFile(dirname(__FILE__).'/templates/form.tpl.html');
        $tpl = new lw_te($template);
        $tpl->reg("action", lw_page::getInstance()->getUrl(array($this->getCommandIdentifier()=>'saveProfile')));
        $tpl->reg("name", $name);
        if ($this->isError()) {
            $tpl->setIfVar("error");
            foreach($this->errorMessage as $key => $messages) {
                foreach($messages as $message) {
                    $tpl->setIfVar($key."_".$message);
                }
            }
        }
        $this->output = $tpl->parse();
    }

    public function wf_savedProfile()
    {
        $template = $this->loadFile(dirname(__FILE__).'/templates/saved.tpl.html');
        $tpl = new lw_te($template);
        $tpl->reg("backurl", lw_page::getInstance()->getUrl());
        $this->output = $tpl->parse();
    }
    
    public function wf_saveProfile()
    {
        $data = $this->prepareData();
        if (!$this->isError()) {
            $ok = $this->saveData($data);
            if ($ok) {
                $this->pageReload(lw_page::getInstance()->getUrl(array($this->getCommandIdentifier()=>'savedProfile')));
                exit();
            } 
            else {
                throw new Exception('Error while saving');
            }
        }
        return "showForm";
    }
    
    protected function saveData($data)
    {
        if ($data['password']) {
            $this->db->setStatement('UPDATE t:lw_in_user SET name = :name, password = :password WHERE id = :id ');
            $this->db->bindParameter('password', 's', sha1($data['password']));
        }
        else {
            $this->db->setStatement('UPDATE t:lw_in_user SET name = :name WHERE id = :id ');
        }
        $this->db->bindParameter('name', 's', $data['name']);
        $this->db->bindParameter('id', 'i', $this->lwInAuth->getUserdata("id"));
        return $this->db->pdbquery();
    }
    
    protected function prepareData()
    {
        $data['name'] = trim($this->request->getRaw('name'));
        if (!lw_validation::isEmail($data['name'])) {
            $this->setError('name', 'email');
        }
        if (strlen($data['name'])>255) {
            $this->setError('name', 'toolong');
        }
        if (strlen($data['name'])< 6) {
            $this->setError('name', 'tooshort');
        }
        if ($this->checkIfNameExists($this->lwInAuth->getUserdata("id"), $data['name'])) {
            $this->setError('name', 'exists');
        }
        
        $pw2 = trim($this->request->getRaw('pw2'));
        $data['password'] = trim($this->request->getRaw('pw1'));
        if ($data['password'] || $pw2) {
            if (strlen($data['password'])>255) {
                $this->setError('password', 'toolong');
            }
            if (strlen($data['password'])< 6) {
                $this->setError('password', 'tooshort');
            }
            if ($data['password'] != $pw2) {
                $this->setError('password', 'notequal');
            }
        }
        return $data;
    }
    
    protected function setError($field, $error) 
    {
        $this->error = true;
        $this->errorMessage[$field][] = $error;
    }
    
    protected function isError()
    {
        if ($this->error == true) {
            return true;
        }
        return false;
    }
    
    protected function checkIfNameExists($id, $name) 
    {
        $this->db->setStatement("SELECT * FROM t:lw_in_user WHERE name = :name AND id <> :id ");
        $this->db->bindParameter('name', 's', $name);
        $this->db->bindParameter('id', 'i', $id);
        $result = $this->db->pselect1();
        if ($result['id'] > 0) {
            return true;
        }
        return false;
    }
}