<?php

namespace MGLara\Models;

class EstoqueMes extends MGModel
{
    protected $table = 'tblestoquemes';
    protected $primaryKey = 'codestoquemes';
    protected $fillable = [
      'codestoquesaldo',
      'mes',
      'inicialquantidade',
      'inicialvalor',
      'entradaquantidade',
      'entradavalor',
      'saidaquantidade',
      'saidavalor',
      'saidaquantidade',
      'saidavalor',
      'saldoquantidade',
      'saldovalor',
      'saldovalorunitario',
    ];
    
    public function EstoqueMovimento()
    {
        return $this->hasMany(EstoqueMovimento::class, 'codestoquemes', 'codestoquemes');
    }    
    
    public function EstoqueSaldo()
    {
        return $this->hasMany(EstoqueSaldo::class, 'codestoquesaldo', 'codestoquesaldo');
    }
     

    public function validate() {
        
        $this->_regrasValidacao = [
            //'field' => 'required|min:2', 
        ];
    
        $this->_mensagensErro = [
            //'field.required' => 'Preencha o campo',
        ];
        
        return parent::validate();
    }
    
    # Buscas #
    public static function filterAndPaginate($codestoquemes)
    {
        return EstoqueMes::codestoquemes($codestoquemes)
            ->orderBy('criacao', 'DESC')
            ->paginate(20);
    }
    
    public function scopeCodestoquemes($query, $codestoquemes)
    {
        if ($codestoquemes)
        {
            $query->where('codestoquemes', "$codestoquemes");
        }
    }   
}