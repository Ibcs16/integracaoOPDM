<?php
 ini_set('display_errors',1);
 ini_set('display_startup_erros',1);
 error_reporting(E_ALL);

require 'config.php';
require 'vendor/autoload.php';


require 'start.php';

use Controllers\ChamadoController;



if(!is_file(__DIR__.'/.env')){
    die();
}


$conf = json_decode(file_get_contents(__DIR__ . '/.env'), true);


$date = date("Y-m-d");//,strtotime('-1 days'));





function getHeader($token, $option){
    $headers = [];

    switch($option){
        case 0:
            $headers = array (
                "Authorization: {$token}",
                'Content-Type: application/json; charset=UTF-8',
                "cache-control: no-cache"
            );
            break;
        case 1:
            $headers = array(
                "Authorization: Basic ". base64_encode('apikey:'.$token),
                "Content-Type: application/json",
                "cache-control: no-cache"
            );
            break;
    }

    return $headers;
}




$listarChamadosEmAndamentoFilter = montaFiltroChamadoPorStatus('');//"Em andamento");

$listarChamadosAguardandoAtendimentoFilter = montaFiltroChamadoPorStatus('');//"Aguardando atendimento");


listarChamadosAguardandoAtendimentoEPrevenirInteracao($listarChamadosAguardandoAtendimentoFilter, $conf['base_url_DM'], $conf['lista_chamados'], $conf);
listarChamadosECriarNoOP($listarChamadosEmAndamentoFilter, $conf['base_url_DM'], $conf['lista_chamados'], $conf);
listarChamadosEAtualizarStatus($conf);


function autenticarDM($conf){
    
    $body = array("PublicKey"=> $conf['dkAmbiente']);
    $header = getHeader($conf['dkOperador'], 0);

    $token = doCurl($body, $conf['base_url_DM'], $conf['autenticarDM'], $header, true);

    return substr($token, 1, -1);
}

function retorna_chave($chamado){
    return $chamado->Chave;
}



function listarChamadosAguardandoAtendimentoEPrevenirInteracao($filtro, $base_url, $endpoint, $conf){
    $token = autenticarDM($conf);

    
    if($token!==null){

        $headers = getHeader($token, 0);
        $chamadosAguardandoAtendimento = doCurl($filtro, $base_url, $endpoint, $headers)->root;

        if(isset($chamadosAguardandoAtendimento->erro)){
            return;
        }

       
        $chavesAguardandoAtendimento = array_map('retorna_chave',$chamadosAguardandoAtendimento);

        ChamadoController::update_chamados_in_chaves_can_interact($chavesAguardandoAtendimento);
        
    }
    
     
}


//makes htto-request
function doCurl($data, $baseUrl, $endpoint, $headers, $raw=false, $hasbody = true, $method = -1)
{

    //var_dump($data, $baseUrl, $endpoint, $headers, $raw, $method);

    $ch = curl_init();

    switch($method){
        case 1:
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        break;
        case 2:
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        break;
        case 3:
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET');
        break;
    }
    

    curl_setopt( $ch, CURLOPT_URL, $baseUrl ."/". $endpoint );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    if($hasbody) curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data) );

    //var_dump(curl_getinfo($ch));
    
    $result = curl_exec( $ch );
   

    $err = curl_error( $ch );
    curl_close( $ch );

    
    if ($err) {
       
        return null;
    } else {
        if(!$raw){
            $dados = json_decode($result);
            return $dados;
        }else{
            return $result;
        }
    }
}

//updates status in deskmanager
function atualizarStatusDM($chamado, $conf) 
{
    interagirComChamado($chamado->chave, $chamado->status_name, $conf);
}


//get new request from deskManager and create workpackage in openproject
function listarChamadosECriarNoOP($filter, $base_url, $endpoint,  $conf)
{
    $token = autenticarDM($conf);
    
    if($token!=null){

        $headerWithToken = getHeader($token, 0);

        $chamadosEmAndamento = doCurl($filter, $base_url, $endpoint, $headerWithToken)->root;

        if(isset($chamadosEmAndamento->erro)){
            return;
        }

        $chamadosJaRegistrados = ChamadoController::get_chamados();

        $novosBugs = $chamadosJaRegistrados;//array_diff($chamadosJaRegistrados, $chamadosEmAndamento);


        if(count($novosBugs)){
            //foreach($novosBugs as $bug){
                //criarChamadoNoBD($bug);
                criarWorkPackage($novosBugs[0], $conf);//$bug, $conf);
            //}
        }
    }

}

//sends to database
function criarChamadoNoBD($bug)
{
    return $chamado = ChamadoController::create_chamado($bug->chave,$bug->cod, $bug->status_name);
    
}

//sends request for workpackage creation
function criarWorkPackage($chamado_origin, $conf)
{
    $data = [];

    $headersOP = getHeader($conf['opToken'], 1);

    $form = doCurl($data, $conf['base_url_OP'], $conf['get_form'], $headersOP);


    if($form->_type!='Error'){
        $workPackage = getWorkpackageSchemaFromChamado($chamado_origin, $form);


        $newWorkPackage = doCurl($workPackage, $conf['base_url_OP'], $conf['create_work_package'], $headersOP);

    
        if($workPackage!=null&&isset($workPackage->id)){
            syncChamadoProjectId($newWorkPackage->id, $chamado_origin->chave);
        }
    }
  
    
}

//updates workpackage id - DB
function syncChamadoProjectId($workPackageId, $chamado_origin_chave)
{
    ChamadoController::update_project_id($chamado_origin_chave, $workPackageId);
}

//updates new status - DB
function syncChamadoStatus($chave, $nomeStatus){
    ChamadoController::update_status_name($chave, $nomeStatus);
}

//sends new status request
function interagirComChamado($chave, $nomeStatus, $conf)
{
    $token = autenticarDM($conf);
    
    if(!$token){
        return;
    }

    $headersDM = getHeader($token, 0);

    $interacaoChamado = criarInteracaoAPartirDeWorkPackage($chave, $nomeStatus, $conf);
    $interagir = doCurl($interacaoChamado, $conf['base_url_DM'], $conf['muda_status_chamado'], $headersDM, false, true, 1);
    
    if($interagir!=null&&!isset($interagir->erro)){
        syncChamadoStatus($chave,  $nomeStatus);
    }
}

//creates object to send in status change requeest
function criarInteracaoAPartirDeWorkPackage($chave, $novoStatus, $conf)
{
    $codStatus = $conf['STATUS_CODES_DM'][$novoStatus];
    $codMensagem = $conf['FRASES_PRONTAS_CODES_DM'][$novoStatus];

    $interacaoChamado = array(
        "Chave" => $chave,
        "TChamado" => array(
            "Descricao" => "Atualização de status",
            "CodStatus" => $codStatus,
            "DataInteracao" => date('d-m-Y'),
            "CodFormaAtendimento"=> "000001",
            "CodFPMsg" => $codMensagem
        )
    );
    return $interacaoChamado;
}


function montaFiltroChamadoPorStatus($status){
    
    
    $filtro = array(
        "Pesquisa" => $status,
        "Colunas" =>  array(
            "Chave" => "on",		
            "CodChamado" => "on",
            "NomePrioridade" => "on",
            "DataCriacao" => "on",	
            "NomeStatus" => "on",
            "Descricao" => "on",
            "ChaveUsuario" => "on",
            "NomeOperador" => "on",	
            "SobrenomeOperador" => "on"
    ));
    
    return $filtro;
}


//gets workpackages from OpenProject and sends new statuses to deskManager
function listarChamadosEAtualizarStatus($conf){
    $chamadosRegistrados = ChamadoController::get_chamados_can_interact();
    var_dump($chamadosRegistrados);
    foreach($chamadosRegistrados as $chamado){
        $oldStatus = $chamado->status_name;
        $newStatus = getWorkPackageStatus($chamado->project_id, $conf);
        if($newStatus!=null&&$oldStatus!=$newStatus){
            $chamado->status_name = $newStatus;
            atualizarStatusDM($chamado, $conf);
        }
    }

}


function getWorkPackageStatus($id, $conf){
    $headersOP = getHeader($conf['opToken'],1);

    $data = '';

    $workPackage = doCurl($data, $conf['base_url_OP'], $conf['get_work_package'] . '/' . $id, $headersOP, false, false, 2);
    var_dump($workPackage);

    if($workPackage->_type=='Error'){
        return null;
    }

    $newStatus = getStatusNameFromWorkPackage($workPackage->_embedded->status->name, $conf);
    return $workPackage!=null?$newStatus:null;
}

//map status name from OP to deskmanager set language
function getStatusNameFromWorkPackage($origin_name, $conf){
    $status_name = $origin_name;

    foreach($conf['STATUS_CODES_OP_PT_BR'] as $status=>$value){
        var_dump($status);
        if($status_name==$status){
            $status_name = $value;
            break;
        }
    }

    return $status_name;
}

//map new workpackage from deskManager object
function getWorkpackageSchemaFromChamado($chamado, $form){
    $customerId =  getCustomerId($chamado, $form);
    $workPackage = array (
        'subject' => "Teste OS digital chamado",
        'description' => 
            array (
                'format' => 'markdown',
                'raw' => 'test',
                'html' => 'test',
            ),
        '_links' => 
            array (
            'type' => 
                array (
                    'href' => '/api/v3/types/7',
                ),
            'priority' => 
                array (
                    'href' => '/api/v3/priorities/1',
                ),
            'project' => 
                array (
                    'href' => '/api/v3/projects/43',
                ),
            'status' => 
                array (
                    'href' => '/api/v3/statuses/1',
                ),
            'author' => 
                array (
                    'href' => '/api/v3/users/36',
                    'title' => 'api integracao',
                ),
            'customField11' => 
                array (
                    'href' => "/api/v3/custom_options/$customerId",
                ),
            )
        );


        return $workPackage;
}


function getCustomerId($chamado_origin, $form){
    $customerName = 'TrackerUp';//$chamado->customerName; teste
    $customerID = 22;

    $valuesCustomer = $form->_embedded->schema->customField11->_embedded->allowedValues;
    foreach($valuesCustomer as $customer){
        if($customer->value&&$customer->value==$customerName){
            $customerID = $customer->id;
            break;
        }
    }

    return $customerID;
}