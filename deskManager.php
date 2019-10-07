<?php
require 'vendor/autoload.php';
require 'config.php';

require ‘start.php’;
use Controllers\Chamados; 
 

if(!is_file(__DIR__.'/.env')){
    die();
}

$conf = json_decode(file_get_contents(__DIR__.'/.env'));



$date = date("Y-m-d");//,strtotime('-1 days'));

$headersDM = array (
    "Authorization: {$conf->tokenApiDK}",
    'Content-Type: application/json; charset=UTF-8',
    "cache-control: no-cache"
);

$headersOP = array(
    "Authorization: Basic ". base64_encode('apikey:'.$user->projectToken ),
    "Content-Type: application/json",
    "cache-control: no-cache"
);
  

$listarChamadosEmAndamentoFilter = montaFiltroChamadoPorStatus("Em andamento");
$listarChamadosAguardandoAtendimentoFilter = montaFiltroChamadoPorStatus("Aguardando atendimento");



listarChamadosAguardandoAtendimentoEPrevenirInteracao();
listarChamadosECriarNoOP();
listarChamadosEAtualizarStatus();


function retorna_chave($chamado){
    return $chamado->Chave;
}



function listarChamadosAguardandoAtendimentoEPrevenirInteracao(){
    
    $chamadosAguardandoAtendimento = doCurl($listarChamadosAguardandoAtendimentoFilter, $conf->base_url_DM, $lista_chamados, $headerDM)->root;

    $chavesAguardandoAtendimento = array_map('retorna_chave',$chamadosAguardandoAtendimento);

    Chamados::update_chamados_can_interact_in_chaves($chaves);
     
}



function doCurl($data, $baseUrl, $endpoint, $headers)
{
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $baseUrl ."". $endpoint );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode($data) );
    
    $result = curl_exec( $ch );

    $err = curl_error( $curl );
    curl_close( $ch );

    
    if ($err) {
        return null;
    } else {
        $dados = json_decode($response);
        return $dados;
    }
}


function atualizarStatusDM($chamado) 
{
    interagirComChamado($chamado->chave, $chamado->status_name);
}


function listarChamadosECriarNoOP()
{
    $chamadosEmAndamento = doCurl($listarChamadosEmAndamentoFilter, $conf->base_url_DM, $conf->lista_chamados, $headersDM)->root;

    $chamadosJaRegistrados = Chamados::get_chamados();

    $novosBugs = array_diff($chamadosJaRegistrados, $chamadosEmAndamento);

    if(count($novosBugs)){
        foreach($novosBugs as $bug){
            criarChamadoNoBD($bug);
            criarWorkPackage($bug);
        }
    }

}


function criarChamadoNoBD($bug)
{
    return $chamado = Chamados::create_chamado($bug->Chave,$bug->CodChamado, $bug->NomeStatus);
    
}

function criarWorkPackage($chamado_origin)
{
    $workPackage = doCurl($workPackage,$conf->base_url_OP.$conf->create_work_package,$headersOP);

    if($chamado!=null){
        syncChamadoProjectId($workPackage->id, $chamado->chave);
    }
    
}


function syncChamadoProjectId($workPackageId, $chamado_origin_chave)
{
    Chamados::update_project_id($chamado_origin_chave, $workPackageId);
}

function syncChamadoStatus($chave, $nomeStatus){
    Chamados::update_status_name($chave, $nomeStatus);
}


function interagirComChamado($chave, $nomeStatus)
{
    $interacaoChamado = criarInteracaoAPartirDeWorkPackage($chave, $nomeStatus);
    $interagir = doCurl($interacaoChamado, $conf->base_url_DM, $conf->muda_status_chamado, $headersDM);
    
    if($interagir!=null){
        syncChamadoStatus($chave,  $nomeStatus);
    }
}


function criarInteracaoAPartirDeWorkPackage($chave, $novoStatus)
{
    $interacaoChamado = array(
        "Chave" => $chave,
        "TChamado" => array(
            "Descricao" => "Atualização de status",
            "CodStatus" => $conf->STATUS_CODES_DM[$novoStatus],
            "DataInteracao" => date('d-m-Y'),
            "CodFormaAtendimento"=> "000001",
            "CodFPMsg" => $conf->FRASES_PRONTAS_CODES_DM[$novoStatus]
        )
    );
    return json_encode($interacaoChamado);
}


function montaFiltroChamadoPorStatus($status){
    return array(
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
}

function listarChamadosEAtualizarStatus(){
    $chamadosRegistrados = Chamados::get_chamados_can_interact();

    foreach($chamadosRegistrados as $chamado){
        $oldStatus = $chamado->status_name;
        $newStatus = getWorkPackageStatus($chamado->project_id);
        if($newStatus!=null&&$oldStatus!=$newStatus){
            atualizarStatusDM($chamado);
        }
    }

}

function getWorkPackageStatus($id){
    $workPackage = doCurl($id, $conf->base_url_OP, $conf->get_work_package, $headersOP);

    return $workPackage!=null?$workPackage->status:null;
}