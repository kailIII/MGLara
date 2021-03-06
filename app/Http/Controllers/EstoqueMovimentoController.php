<?php

namespace MGLara\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use MGLara\Http\Controllers\Controller;
use MGLara\Models\EstoqueMovimento;
use MGLara\Models\EstoqueMovimentoTipo;
use MGLara\Models\EstoqueMes;
use MGLara\Models\EstoqueLocal;

use Carbon\Carbon;
use Illuminate\Support\Facades\Input;

class EstoqueMovimentoController extends Controller
{
    
    public function __construct()
    {
        $this->datas = [];
        $this->numericos = [];
    }    
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $model = new EstoqueMovimento();
        $model->codestoquemes = $request->codestoquemes;
        $model->data = $model->EstoqueMes->mes;
        $model->data = $model->data->modify('last day of this month');
        
        return view('estoque-movimento.create', compact('model', 'tipos', 'request', 'options', 'el'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->converteDatas(['data' => $request->input('data')]);
        $this->converteNumericos([
            'entradaquantidade' => $request->input('entradaquantidade'),
            'saidaquantidade' => $request->input('saidaquantidade'),
            'entradavalor' => $request->input('entradavalor'),
            'saidavalor' => $request->input('saidavalor')
        ]);

        $model = new EstoqueMovimento($request->all());
        
        $em = EstoqueMes::buscaOuCria(
                $model->EstoqueMes->EstoqueSaldo->codprodutovariacao, 
                $model->EstoqueMes->EstoqueSaldo->codestoquelocal, 
                $model->EstoqueMes->EstoqueSaldo->fiscal, 
                $model->data);
        
        $model->codestoquemes = $em->codestoquemes;
        
        if (!$model->validate()) {
            $this->throwValidationException($request, $model->_validator);
        }

        //Cria registro de Origem
        if (!empty($model->EstoqueMovimentoTipo->codestoquemovimentotipoorigem))
        {
            $origem = new EstoqueMovimento();
            //$origem = $model->EstoqueMovimentoOrigem;

            $emOrigem = EstoqueMes::buscaOuCria(
                    $request->input('codprodutovariacao'), 
                    $request->input('codestoquelocal'), 
                    $model->EstoqueMes->EstoqueSaldo->fiscal, 
                    $model->data);

            $origem->codestoquemes = $emOrigem->codestoquemes;
            $origem->codestoquemovimentotipo = $model->EstoqueMovimentoTipo->codestoquemovimentotipoorigem;
            $origem->data = $model->data;
            $origem->entradaquantidade = $model->saidaquantidade;
            $origem->entradavalor = $model->saidavalor;
            $origem->saidaquantidade = $model->entradaquantidade;
            $origem->saidavalor = $model->entradavalor ; 
            $origem->manual = true;
            $origem->save();
            $emOrigem = $origem->EstoqueMes;
            $model->codestoquemovimentoorigem = $origem->codestoquemovimento;
        }   
        
        $model->manual = TRUE;
        $model->save();
        
        $model->EstoqueMes->EstoqueSaldo->recalculaCustoMedio();
        
        if (isset($emOrigem))
            $emOrigem->EstoqueSaldo->recalculaCustoMedio();
        
        Session::flash('flash_create', 'Registro inserido.');
        return redirect("estoque-mes/$model->codestoquemes");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $model = EstoqueMovimento::find($id);
        return view('estoque-movimento.show', compact('model'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $model = EstoqueMovimento::findOrFail($id);

        return view('estoque-movimento.edit',  compact('model', 'tipos', 'options', 'el'));        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->converteDatas(['data' => $request->input('data')]);
        $this->converteNumericos([
            'entradaquantidade' => $request->input('entradaquantidade'),
            'saidaquantidade' => $request->input('saidaquantidade'),
            'entradavalor' => $request->input('entradavalor'),
            'saidavalor' => $request->input('saidavalor')
        ]);
        
        $model = EstoqueMovimento::findOrFail($id);
        $model->fill($request->all());
        if (!$model->validate()) {
            $this->throwValidationException($request, $model->_validator);
        }
        
        $em = EstoqueMes::buscaOuCria(
                $model->EstoqueMes->EstoqueSaldo->codprodutovariacao, 
                $model->EstoqueMes->EstoqueSaldo->codestoquelocal, 
                $model->EstoqueMes->EstoqueSaldo->fiscal, 
                $model->data);
        
        $model->codestoquemes = $em->codestoquemes;        
        
         //Cria registro de Origem
        if (!empty($model->EstoqueMovimentoTipo->codestoquemovimentotipoorigem))
        {
            $origem = $model->EstoqueMovimentoOrigem;

            $emOrigem = EstoqueMes::buscaOuCria(
                    $request->input('codprodutovariacao'), 
                    $request->input('codestoquelocal'), 
                    $model->EstoqueMes->EstoqueSaldo->fiscal, 
                    $model->data);
            
            if ($origem->codestoquemes != $emOrigem->codestoquemes)
                $emOrigemAnterior = $origem->EstoqueMes;

            $origem->codestoquemes = $emOrigem->codestoquemes;
            $origem->codestoquemovimentotipo = $model->EstoqueMovimentoTipo->codestoquemovimentotipoorigem;
            $origem->data = $model->data;
            $origem->entradaquantidade = $model->saidaquantidade;
            $origem->entradavalor = $model->saidavalor;
            $origem->saidaquantidade = $model->entradaquantidade;
            $origem->saidavalor = $model->entradavalor ; 
            $origem->manual = true;
            $origem->save();
            
            $origem = EstoqueMovimento::find($origem->codestoquemovimento);
            $emOrigem = $origem->EstoqueMes;
            
            $model->codestoquemovimentoorigem = $origem->codestoquemovimento;
        }         
        
        
        $model->save();
        $model->EstoqueMes->EstoqueSaldo->recalculaCustoMedio();
        
        if (isset($emOrigemAnterior))
            $emOrigemAnterior->EstoqueSaldo->recalculaCustoMedio();

        if (isset($emOrigem))
            $emOrigem->EstoqueSaldo->recalculaCustoMedio();
        
        Session::flash('flash_update', 'Registro atualizado.');
        return redirect("estoque-mes/$model->codestoquemes");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        try{
            $model = EstoqueMovimento::find($id);
            if(!empty($model->codestoquemovimentoorigem))
            {
                $origem = $model->EstoqueMovimentoOrigem;
                
                $model->delete();
                $model->EstoqueMes->EstoqueSaldo->recalculaCustoMedio();
                
                $origem->delete();
                $origeml->EstoqueMes->EstoqueSaldo->recalculaCustoMedio();
            } else {
                $filha = $model->EstoqueMovimentoS->first();
                if(!empty($filha)) {
                    $filha->delete();
                    $filha->EstoqueMes->EstoqueSaldo->recalculaCustoMedio();
                    
                    $model->delete();
                    $model->EstoqueMes->EstoqueSaldo->recalculaCustoMedio();
                } else {
                    $model->delete();
                    $model->EstoqueMes->EstoqueSaldo->recalculaCustoMedio();
                }
            }
            Session::flash('flash_delete', 'Registro deletado!');
            return redirect("estoque-mes/$model->codestoquemes");
        }
        catch(\Exception $e){
            return view('errors.fk');
        }    
    }    
}
