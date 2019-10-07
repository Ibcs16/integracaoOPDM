<?php
 
namespace Models;
 
use \Illuminate\Database\Eloquent\Model;
 
class Chamado extends Model {
     
    protected $table = 'chamados';
    protected $fillable = ['status_name','chave','project_id','updated_at'];

     
}
 