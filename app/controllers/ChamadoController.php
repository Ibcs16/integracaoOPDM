<?php
namespace Controllers;
use Models\Chamado;

class ChamadoController{

    public static function create_chamado($chave, $cod, $status_name, $project_id=null){
        $chamado = Chamado::updateOrCreate(['chave'=>$chave,'cod'=>$cod,'project_id'=>$project_id,'status_name'=>$status_name]);
        return $chamado;
    }

    
    public static function update_can_interact($chave, $can_interact){
        $chamado = Chamado::where(array('chave'=> $chave))->update(array('can_interact'=> $can_interact ? 1 : 0));
    
        return $chamado;
    }

    public static function update_status_name($chave, $new_status){
        $chamado = Chamado::where(array('chave'=> $chave))->update(array('status_name'=> $new_status));
        
        return $chamado;
    }

    public static function update_project_id($chave, $project_id){

        var_dump($chave, $project_id);

        $chamado = Chamado::where(array('chave'=> $chave))->update(array('project_id'=> $project_id));
        
      
        return $chamado;
    }

 
    public static function get_chamados_with_status($status_name){
    
        $chamados = Chamado::where('status_name',$status_name);
        return $chamados;
    }

    public static function update_chamados_in_chaves_can_interact( $chaves ){

        $rows = Chamado::whereIn('chave', $chaves)->update(array('can_interact' => 0));
       
        return $rows;
    }


    public static function get_chamados_can_interact(){
    
        $chamados = Chamado::where('can_interact', 0)->get();//teste

        return $chamados;
    }

    public static function get_chamados(){
    
        $chamados = Chamado::all();

        return $chamados;
    }

}