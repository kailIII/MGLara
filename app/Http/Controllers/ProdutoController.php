<?php

namespace MGLara\Http\Controllers;

use Illuminate\Http\Request;

use MGLara\Http\Requests;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use MGLara\Http\Controllers\Controller;
use MGLara\Models\Produto;
use MGLara\Models\ProdutoBarra;
use MGLara\Models\ProdutoVariacao;
use MGLara\Models\NegocioProdutoBarra;
use MGLara\Models\NotaFiscalProdutoBarra;
use MGLara\Models\TipoProduto;

class ProdutoController extends Controller
{
    
    public function __construct()
    {
        $this->datas = [];
        $this->numericos = [];
        $this->middleware('parametros', ['only' => ['index', 'show']]);
    }     

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        
        if (!$request->session()->has('produto.index')) 
            $request->session()->put('produto.index.inativo', '1');
        
        $parametros = $request->session()->get('produto.index');        
        $model = Produto::search($parametros);
        return view('produto.index', compact('model'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $model = new Produto;
        $model->codtipoproduto = TipoProduto::MERCADORIA;
        return view('produto.create', compact('model'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->converteDatas(['inativo' => $request->input('inativo')], 'd/m/Y');
        $this->converteNumericos(['preco' => $request->input('preco')]);
        
        $model = new Produto($request->all());
        
        DB::beginTransaction();
        
        if (!$model->validate())
            $this->throwValidationException($request, $model->_validator);
        
        try {
            if (!$model->save())
                throw new Exception ('Erro ao Criar Produto!');
            
            $pv = new ProdutoVariacao();
            $pv->codproduto = $model->codproduto;
                    
            if (!$pv->save())
                throw new Exception ('Erro ao Criar Variação!');
            
            $pb = new ProdutoBarra();
            $pb->codproduto = $model->codproduto;
            $pb->codprodutovariacao = $pv->codprodutovariacao;
            $pb->barras = str_pad($model->codproduto, 6, '0', STR_PAD_LEFT);
                    
            if (!$pb->save())
                throw new Exception ('Erro ao Criar Barras!');
            
            DB::commit();
            Session::flash('flash_success', "Produto '{$model->produto}' criado!");
            return redirect("produto/$model->codproduto");               
            
        } catch (Exception $ex) {
            DB::rollBack();
            $this->throwValidationException($request, $model->_validator);              
        }
     
    }

    public function show(Request $request, $id)
    {
        /*
        DB::enableQueryLog();
        */
        
        $model = Produto::find($id);
        /*
        $npbs = NegocioProdutoBarra::search(
            $id,
            $request->get('npb_lancamento_de'),
            $request->get('npb_lancamento_ate'),
            $request->get('npb_codfilial'),
            $request->get('npb_codnaturezaoperacao'),
            $request->get('npb_codpessoa')
        );
        $nfpbs = NotaFiscalProdutoBarra::search(
            $id,
            $request->get('nfpb_saida_de'),
            $request->get('nfpb_saida_ate'),
            $request->get('nfpb_codfilial'),
            $request->get('nfpb_codnaturezaoperacao'),
            $request->get('nfpb_codpessoa')
        );
         * 
         */
        $ret = view('produto.show', compact('model', 'nfpbs', 'npbs'));
        
        /*
        $queries = DB::getQueryLog();

         echo '<hr><h1>queries</h1>';
        dd($queries);
        */
        return $ret;
    }


    public function edit($id)
    {
        $model = Produto::findOrFail($id);
        return view('produto.edit',  compact('model'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $this->converteDatas(['inativo' => $request->input('inativo')], 'd/m/Y');
        $this->converteNumericos(['preco' => $request->input('preco')]);
        
        $model = Produto::findOrFail($id);
        $model->fill($request->all());
        
        if ($request->input('importado') == 1) {
            $model->importado = TRUE;
        } else {
            $model->importado = FALSE;
        }

        if ($request->input('site') == 1) {
            $model->site = TRUE;
        } else {
            $model->site = FALSE;
        }

        if (!$model->validate())
            $this->throwValidationException($request, $model->_validator);
        $model->save();
        Session::flash('flash_success', "Produto '{$model->produto}' alterado!");
        return redirect("produto/$model->codproduto");           
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try{
            Produto::find($id)->delete();
            $ret = ['resultado' => true, 'mensagem' => 'Produto excluído com sucesso!'];
        }
        catch(\Exception $e){
            $ret = ['resultado' => false, 'mensagem' => 'Erro ao excluir produto!', 'exception' => $e];
        }
        return json_encode($ret);
    }    
    
    
    public function buscaPorBarras(Request $request)
    {
        $barra = ProdutoBarra::buscaPorBarras($request->get('barras'));
        return response()->json($barra);
    }

        public function recalculaMovimentoEstoque($id)
    {
        $model = Produto::findOrFail($id);
        $ret = $model->recalculaMovimentoEstoque();
        return json_encode($ret);
    }
    
    /**
     * Recalcula preço médio dos estoques
     * 
     * @param bigint $id
     * @return \Illuminate\Http\Response
     * 
     */
    public function recalculaCustoMedio($id)
    {
        $model = Produto::findOrFail($id);
        $ret = $model->recalculaCustoMedio();
        return json_encode($ret);
    }
    
    /**
     * Tenta cobrir estoque negativo, transferindo entre EstoqueLocal
     * 
     * @param bigint $id
     * @return \Illuminate\Http\Response
     * 
     */
    public function cobreEstoqueNegativo($id = null)
    {

        //echo '<meta http-equiv="refresh" content="1; URL=' . url('produto/cobre-estoque-negativo') . '">';
        $codprodutos = [];
        $pular = 0;
        if (isset($_GET['pular']))
            $pular = $_GET['pular'];
        
        if (empty($id))
        {
            
            $itens = 2;
            if (isset($_GET['itens']))
                $itens = $_GET['itens'];
            /*
            $sql = "
                    select distinct(es.codproduto) 
                    from tblestoquesaldo es
                    where es.fiscal
                    and es.saldoquantidade < 0
                    and es.codproduto in (select distinct es2.codproduto from tblestoquesaldo es2 where es2.fiscal and es2.saldoquantidade > 0)
                    order by es.codproduto
                    limit $itens
                    offset $pular
                    ";
            */
            
            $sql = "
                    select distinct(es.codproduto) 
                    from tblestoquesaldo es
                    where es.fiscal
                    and es.saldoquantidade < 0
                    order by es.codproduto
                    limit $itens
                    offset $pular
                    ";
            
            $prods = DB::select($sql);
            
            foreach($prods as $prod)
                $codprodutos[] = $prod->codproduto;
            
        }
        else
        {
            $codprodutos[] = $id;
        }
        
        $ret = [];
        foreach ($codprodutos as $codproduto)
        {
            $model = Produto::findOrFail($codproduto);
            $ret[$codproduto] = $model->cobreEstoqueNegativo();
            if (sizeof($ret[$codproduto]) == 0)
                $pular++;
        }
        
        if (sizeof($codprodutos)>1)
            echo '<meta http-equiv="refresh" content="1; URL=' . url('produto/cobre-estoque-negativo') . '?pular=' . $pular . '">';
        
        return json_encode($ret);
    }
    
    public function ajax(Request $request)
    //public function ajax($texto, $inativo = false, $limite = 20, $pagina = 1) 
    {
        $pagina = $request->get('page');
        $limite = $request->get('per_page');
        $inativo = $request->get('inativo');
        // limpa texto
        $ordem = (strstr($request->get('q'), '$'))?'preco ASC, descricao ASC':'descricao ASC, preco ASC';
        $texto = str_replace('$', '', $request->get('q'));
        $texto  = str_replace(' ', '%', trim($request->get('q')));

        // corrige pagina se veio sujeira
        if ($pagina < 1) $pagina = 1;

        // calcula de onde continuar a consulta
        $offset = ($pagina-1)*$limite;

        // inicializa array com resultados
        $resultados = array();

        // se o texto foi preenchido
        #if (strlen($texto)>=3)
        #{
            $sql = "SELECT codprodutobarra as id, codproduto, barras, descricao, sigla, preco, marca, referencia 
                          FROM vwProdutoBarra 
                         WHERE codProdutoBarra is not null ";

            #if (!$inativo) {
            #    $sql .= "AND Inativo is null ";
            #}

        $sql .= " AND (";

            // Verifica se foi digitado um valor e procura pelo preco
        #    If ((Yii::app()->format->formatNumber(Yii::app()->format->unformatNumber($texto)) == $texto)
        #            && (strpos($texto, ",") != 0)
        #            && ((strlen($texto) - strpos($texto, ",")) == 3)) 
        #    {
        #            $sql .= "preco = :preco";
        #            $params = array(
        #                    ':preco'=>Yii::app()->format->unformatNumber($texto),
        #                    );
        #}
            
            //senao procura por barras, descricao, marca e referencia
            #else
            #{
                    $sql .= "barras ilike '%$texto%' ";
                    $sql .= "OR descricao ilike '%$texto%' ";
                    $sql .= "OR marca ilike '%$texto%' ";
                    $sql .= "OR referencia ilike '%$texto%' ";
                    
                    /*$params = array(
                        ':texto'=>'%'.$texto.'%',
                    );*/
            #}

            //ordena
            #$sql .= ") ORDER BY $ordem LIMIT $limite OFFSET $offset";
            $sql .= ") ORDER BY $ordem LIMIT $limite OFFSET $offset";

            #$command = Yii::app()->db->createCommand($sql);
            #$command->params = $params;

            #$resultados = $command->queryAll();

            
            $resultados = DB::select($sql);
            
            for ($i=0; $i<sizeof($resultados);$i++)
            {
                    $resultados[$i]->codproduto = \formataCodigo($resultados[$i]->codproduto, 6);
                    $resultados[$i]->preco = \formataNumero($resultados[$i]->preco);
                    if (empty($resultados[$i]->referencia))
                            $resultados[$i]->referencia = "-";
            }

            return response()->json($resultados);
//            json_encode([
//                    'mais' => count($resultados)==$limite?true:false, 
//                    'pagina' => (int) $pagina, 
//                    'itens' => $resultados
//                ]
//            );            
            
        } 

        public function ajaxProduto(Request $request) 
        {
            if($request->get('q')) {
                $pagina = $request->get('page');
                $limite = $request->get('per_page');
                $inativo = $request->get('inativo');
                // limpa texto
                $ordem = (strstr($request->get('q'), '$'))?'produto ASC':'produto ASC';
                $texto = str_replace('$', '', $request->get('q'));
                $texto  = str_replace(' ', '%', trim($request->get('q')));

                // corrige pagina se veio sujeira
                if ($pagina < 1) $pagina = 1;

                // calcula de onde continuar a consulta
                $offset = ($pagina-1)*$limite;

                // inicializa array com resultados
                $resultados = array();     

                $sql = "SELECT codproduto as id, produto, referencia, preco FROM tblproduto 
                            WHERE produto ilike '%$texto%'";
                $sql .= " ORDER BY produto LIMIT $limite OFFSET $offset";
                $resultados = DB::select($sql);
                return response()->json($resultados);
            } elseif($request->get('id')) {
                $query = DB::table('tblproduto')
                        ->where('codproduto', '=', $request->get('id'))
                        ->select('codproduto as id', 'produto', 'referencia', 'preco')
                        ->get();
                return response()->json($query);
            }
        }
        
        public function ajaxDescricao(Request $request) 
        {
            $sql = Produto::produto($request->get('q'))
                    ->where('codsubgrupoproduto', $request->get('codsubgrupoproduto'))
                    ->select('produto')
                    ->paginate(20);
            
            return response()->json($sql);
        }

    public function inativo(Request $request)
    {
        $model = Produto::find($request->get('codproduto'));
        if($request->get('acao') == 'ativar')
            $model->inativo = null;
        else
            $model->inativo = Carbon::now();
        
        $model->save();
    }    
        
        
    public function estoqueSaldo(Request $request) 
    {
        $query = DB::table('tblestoquesaldo')
            ->join('tblestoquelocalprodutovariacao', 'tblestoquelocalprodutovariacao.codestoquelocalprodutovariacao', '=', 'tblestoquesaldo.codestoquelocalprodutovariacao')
            ->where('codproduto', '=', $request->get('codproduto'))
            ->select('customedio', 'saldovalor', 'saldoquantidade');

        if($request->get('codestoquelocal')) $query->where('tblestoquelocalprodutovariacao.codestoquelocal', '=', $request->get('codestoquelocal'));
        if($request->get('fiscal') == 1) 
            $query->where('fiscal', '=', true);
        else 
            $query->where('fiscal', '=', false);
        $resultado = $query->get();

        return response()->json($resultado);
    }
        
}
