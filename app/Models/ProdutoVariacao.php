<?php

namespace MGLara\Models;

/**
 * Campos
 * @property  bigint                         $codprodutovariacao                 NOT NULL DEFAULT nextval('tblprodutovariacao_codprodutovariacao_seq'::regclass)
 * @property  varchar(100)                   $variacao                           
 * @property  bigint                         $codproduto                         NOT NULL
 * @property  bigint                         $codmarca                           
 * @property  timestamp                      $alteracao                          
 * @property  bigint                         $codusuarioalteracao                
 * @property  timestamp                      $criacao                            
 * @property  bigint                         $codusuariocriacao                  
 * @property  varchar(50)                    $referencia                         
 *
 * Chaves Estrangeiras
 * @property  Marca                          $Marca                         
 * @property  Usuario                        $UsuarioAlteracao
 * @property  Usuario                        $UsuarioCriacao
 * @property  Produto                        $Produto                       
 *
 * Tabelas Filhas
 * @property  ProdutoBarra[]                 $ProdutoBarraS
 */

class ProdutoVariacao extends MGModel
{
    protected $table = 'tblprodutovariacao';
    protected $primaryKey = 'codprodutovariacao';
    protected $fillable = [
        'variacao',
        'codproduto',
        'codmarca',
        'referencia',
    ];
    protected $dates = [
        'alteracao',
        'criacao',
    ];


    // Chaves Estrangeiras
    public function Marca()
    {
        return $this->belongsTo(Marca::class, 'codmarca', 'codmarca');
    }

    public function UsuarioAlteracao()
    {
        return $this->belongsTo(Usuario::class, 'codusuarioalteracao', 'codusuario');
    }

    public function UsuarioCriacao()
    {
        return $this->belongsTo(Usuario::class, 'codusuariocriacao', 'codusuario');
    }

    public function Produto()
    {
        return $this->belongsTo(Produto::class, 'codproduto', 'codproduto');
    }


    // Tabelas Filhas
    public function ProdutobarraS()
    {
        return $this->hasMany(ProdutoBarra::class, 'codprodutovariacao', 'codprodutovariacao');
    }


}