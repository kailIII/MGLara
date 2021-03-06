<?php

namespace MGLara\Jobs;

use MGLara\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Log;

use MGLara\Models\EstoqueMes;
use MGLara\Models\EstoqueMovimentoTipo;
use Illuminate\Support\Facades\DB;

/**
 * @property $codestoquemes bigint
 * @property $ciclo bigint
 */

class EstoqueCalculaCustoMedio extends Job implements SelfHandling, ShouldQueue
{
    
    use InteractsWithQueue, SerializesModels, DispatchesJobs;
    
    protected $codestoquemes;
    protected $ciclo;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($codestoquemes, $ciclo = 0)
    {
        $this->codestoquemes = $codestoquemes;
        $this->ciclo = $ciclo;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        
        Log::info('EstoqueCalculaCustoMedio', ['codestoquemes' => $this->codestoquemes, 'ciclo' => $this->ciclo]);
        
        if ($this->ciclo > 10)
            return;
        
        $mes = EstoqueMes::findOrFail($this->codestoquemes);
        
        //busca totais de registros nao baseados no custo medio
        $sql = "
            select 
                sum(entradaquantidade) entradaquantidade
                , sum(entradavalor) entradavalor
            from tblestoquemovimento mov
            left join tblestoquemovimentotipo tipo on (tipo.codestoquemovimentotipo = mov.codestoquemovimentotipo)
            where mov.codestoquemes = {$mes->codestoquemes}
            and tipo.preco in (" . EstoqueMovimentoTipo::PRECO_INFORMADO . ", " . EstoqueMovimentoTipo::PRECO_ORIGEM . ")";

        $mov = DB::select($sql);
        $mov = $mov[0];

        //busca saldo inicial
        $inicialquantidade = 0;
        $inicialvalor = 0;
        $anterior = $mes->buscaAnteriores(1);
        if (isset($anterior[0]))
        {
            $inicialquantidade = $anterior[0]->saldoquantidade;
            $inicialvalor = $anterior[0]->saldovalor;
        }

        //calcula custo medio
        $valor = $mov->entradavalor;
        $quantidade = $mov->entradaquantidade;
        if ($inicialquantidade > 0 && $inicialvalor > 0)
        {
            $valor += $inicialvalor;
            $quantidade += $inicialquantidade;
        }
        $customedio = 0;
        if ($quantidade != 0)
            $customedio = abs($valor/$quantidade);
        
        //recalcula valor movimentacao com base custo medio
        $sql = "
            update tblestoquemovimento
            set saidavalor = saidaquantidade * $customedio
                , entradavalor = entradaquantidade * $customedio
            where tblestoquemovimento.codestoquemes = {$mes->codestoquemes} 
            and tblestoquemovimento.codestoquemovimentotipo in 
                (select t.codestoquemovimentotipo from tblestoquemovimentotipo t where t.preco = " . EstoqueMovimentoTipo::PRECO_MEDIO . ")
            ";
            
        $ret = DB::update($sql);
        
        //recalcula valor movimentacao para registros originados a partir deste mes
        $sql = "
            update tblestoquemovimento
            set entradavalor = orig.saidavalor
                , saidavalor = orig.entradavalor
            from tblestoquemovimento orig
            where tblestoquemovimento.codestoquemovimentoorigem = orig.codestoquemovimento
            and orig.codestoquemes = {$mes->codestoquemes}
            ";
            
        $ret = DB::update($sql);
        
        //busca totais movimentados do 
        $sql = "
            select 
                sum(entradaquantidade) entradaquantidade
                , sum(entradavalor) entradavalor
                , sum(saidaquantidade) saidaquantidade
                , sum(saidavalor) saidavalor
            from tblestoquemovimento mov
            left join tblestoquemovimentotipo tipo on (tipo.codestoquemovimentotipo = mov.codestoquemovimentotipo)
            where mov.codestoquemes = {$mes->codestoquemes}
            ";

        $mov = DB::select($sql);
        $mov = $mov[0];
        
        //calcula custo medio e totais novamente
        $mes->inicialquantidade = $inicialquantidade;
        $mes->inicialvalor = $mes->inicialquantidade * $customedio;
        $mes->entradaquantidade = $mov->entradaquantidade;
        $mes->entradavalor = $mov->entradavalor;
        $mes->saidaquantidade = $mov->saidaquantidade;
        $mes->saidavalor = $mov->saidavalor;
        $mes->saldoquantidade = $inicialquantidade + $mov->entradaquantidade - $mov->saidaquantidade;
        $mes->saldovalor = $mes->saldoquantidade * $customedio;
        $customedioanterior = $mes->customedio;
        $mes->customedio = $customedio;

        $mes->save();
        
        $customediodiferenca = abs($customedio - $customedioanterior);
        
        $mesesRecalcular = [];
        if ($customediodiferenca > 0.01)
        {
            $sql = "
                select distinct dest.codestoquemes
                from tblestoquemovimento orig
                inner join tblestoquemovimento dest on (dest.codestoquemovimentoorigem = orig.codestoquemovimento)
                where orig.codestoquemes = {$mes->codestoquemes}
                ";
            $ret = DB::select($sql);
            foreach ($ret as $row)
                $mesesRecalcular[] = $row->codestoquemes;
        }
        
        $proximo = $mes->buscaProximos(1);
        if (isset($proximo[0]))
            $mesesRecalcular[] = $proximo[0]->codestoquemes;
        else
        {
            $mes->EstoqueSaldo->saldoquantidade = $mes->saldoquantidade;
            $mes->EstoqueSaldo->saldovalor = $mes->saldovalor;
            $mes->EstoqueSaldo->customedio = $mes->customedio;
            $mes->EstoqueSaldo->save();
        }
        
        foreach ($mesesRecalcular as $mes)
            $this->dispatch((new EstoqueCalculaCustoMedio($mes, $this->ciclo +1))->onQueue('urgent'));
        
    }
}
