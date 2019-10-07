<?php
namespace Controllers;
use Models\Chamado;

class Chamados{

    public static function create_chamado($chave, $cod, $status_name, $project_id=null){
        $chamado = Chamado::create(['chave'=>$chave,'cod'=>$cod,'project_id'=>$project_id,'status_name'=>$status_name]);
        return $chamado;
    }

    
    public static function update_can_interact($chave, $can_interact){
        $chamado = Chamado::find($chave);
        
        
        $chamado->can_interact = $can_interact ? 1 : 0;

        $updated = $chamado->save();
        return $updated;
    }

    public static function update_status_name($chave, $new_status){
        $chamado = Chamado::find($chave);
        
       
        $chamado->status_name = $new_status;

        $updated = $chamado->save();
        return $updated;
    }

    public static function update_project_id($chave, $project_id){
        $chamado = Chamado::find($chave);
        
        $chamado->project_id = $project_id;

        $updated = $chamado->save();
        return $updated;
    }

 
    public static function get_chamados_with_status($status_name){
    
        $chamados = Chamado::where('status_name',$status_name);
        return $chamados;
    }

    public static function update_chamados_can_interact_in_chaves($chaves){
    
        $chamados = Chamado::whereIn('chave',$chaves)->update(array('can_interact'=>0));

        return $chamados;
    }


    public static function get_chamados_can_interact(){
    
        $chamados = Chamado::where('can_interact', 1);

        return $chamados;
    }

    public static function get_chamados(){
    
        $chamados = Chamado::all();

        return $chamados;
    }


}